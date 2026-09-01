<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\Conversation;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Support\Csv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->customerFilters($request);
        $countedOrders = $this->countedOrdersScope();

        $customers = $this->filteredCustomersQuery($filters)
            ->withCount([
                'orders' => $countedOrders,
                'addresses',
            ])
            ->withSum(['orders as spent_cents' => $this->spentOrdersScope()], 'total_cents')
            ->withMax(['orders as last_order_at' => $countedOrders], 'created_at')
            ->latest()
            ->simplePaginate(20)
            ->withQueryString();

        // Counted without the banned, like the lists they sit above.
        $customerCount = $this->shopCustomers()->whereNull('banned_at')->count();
        $withOrdersCount = $this->shopCustomers()->whereNull('banned_at')->whereHas('orders', $countedOrders)->count();

        return view('admin.customers.index', [
            'customers' => $customers,
            'customerCount' => $customerCount,
            'withOrdersCount' => $withOrdersCount,
            'noOrdersCount' => $customerCount - $withOrdersCount,
            'bannedCount' => $this->shopCustomers()->whereNotNull('banned_at')->count(),
            'newCustomers30d' => $this->shopCustomers()->where('created_at', '>=', now()->subDays(30))->count(),
            'search' => $filters['search'],
            'tab' => $filters['tab'],
            'dateFrom' => $filters['date_from'],
            'dateTo' => $filters['date_to'],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->customerFilters($request);
        $countedOrders = $this->countedOrdersScope();

        $customers = $this->filteredCustomersQuery($filters)
            ->withCount(['orders' => $countedOrders, 'addresses'])
            ->withSum(['orders as spent_cents' => $this->spentOrdersScope()], 'total_cents')
            ->latest()
            ->get();

        return Csv::download(
            'customers-'.now()->format('Y-m-d').'.csv',
            ['Name', 'Email', 'Orders', 'Spent', 'Addresses', 'Joined'],
            $customers->map(fn (User $customer): array => [
                $customer->name,
                $customer->email,
                $customer->orders_count,
                number_format(((int) $customer->spent_cents) / 100, 2, '.', ''),
                $customer->addresses_count,
                $customer->created_at->format('Y-m-d'),
            ])
        );
    }

    private function shopCustomers()
    {
        return User::query()->where('is_admin', false)->where('external', false);
    }

    private function countedOrdersScope(): \Closure
    {
        // excludingTest() here reaches the list's order count, its last-order
        // date, the with/without-orders tabs and the CSV export at once —
        // without it the list credits a customer with orders their own
        // profile page says they never placed.
        return fn ($query) => $query->excludingTest()->where('status', '!=', 'draft');
    }

    private function spentOrdersScope(): \Closure
    {
        return fn ($query) => $query->excludingTest()->whereNotIn('status', ['draft', 'refunded']);
    }

    /**
     * @return array{tab: string, search: string, date_from: string, date_to: string}
     */
    private function customerFilters(Request $request): array
    {
        return [
            'tab' => in_array($request->query('tab'), ['with-orders', 'no-orders', 'banned'], true)
                ? $request->query('tab')
                : 'all',
            'search' => trim((string) $request->query('search', '')),
            'date_from' => (string) $request->query('date_from', ''),
            'date_to' => (string) $request->query('date_to', ''),
        ];
    }

    /**
     * @param  array{tab: string, search: string, date_from: string, date_to: string}  $filters
     */
    private function filteredCustomersQuery(array $filters)
    {
        $countedOrders = $this->countedOrdersScope();

        return $this->shopCustomers()
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];
                $query->where(function ($query) use ($search): void {
                    $query->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['date_from'] !== '', fn ($query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] !== '', fn ($query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when($filters['tab'] === 'with-orders', fn ($query) => $query->whereHas('orders', $countedOrders))
            ->when($filters['tab'] === 'no-orders', fn ($query) => $query->whereDoesntHave('orders', $countedOrders))
            // Banned customers live only on their own tab: they are no longer
            // customers being browsed, they are a record being kept.
            ->when($filters['tab'] === 'banned', fn ($query) => $query->whereNotNull('banned_at'))
            ->when($filters['tab'] !== 'banned', fn ($query) => $query->whereNull('banned_at'));
    }

    public function show(User $customer): View
    {
        abort_if($customer->is_admin, 404);

        $customer->markViewedByAdmin();
        $customer->load('addresses');
        // Archived orders are listed and counted here, so the rows and the
        // total describe the same set. Test orders are listed but not counted,
        // which is why the page says so next to the figure.
        $orders = $customer->orders()->withCount('items')->get();
        $paidOrders = $orders->whereNotIn('status', ['draft', 'refunded'])->where('test_marked_at', null);
        $spentCents = (int) $paidOrders->sum('total_cents');

        return view('admin.customers.show', [
            'customer' => $customer,
            // The verdict only. Opening a document happens on one screen, and
            // this is not it.
            'identityStatus' => $customer->identityStatus(),
            // Which of this customer's orders needed a proof. One query for
            // the panel rather than loading every item's product to ask.
            'ageRestrictedOrderIds' => OrderItem::query()
                ->whereIn('order_id', $orders->pluck('id'))
                ->whereIn('product_id', Product::query()->where('age_restricted', true)->select('id'))
                ->distinct()
                ->pluck('order_id')
                ->all(),
            'orders' => $orders,
            'spentCents' => $spentCents,
            // The list below shows test orders and the total does not, so the
            // page has to say why rather than leave the gap to be worked out.
            'testOrderCount' => $orders->where('test_marked_at', '!=', null)->count(),
            'averageOrderCents' => $paidOrders->isNotEmpty()
                ? (int) round($spentCents / $paidOrders->count())
                : 0,
            'wishlistItems' => $customer->wishlistItems()->with('product')->latest()->get(),
            'discountCodes' => $customer->discountCodes()->withCount('orders')->get(),
            'reviews' => ProductReview::query()
                ->where('user_id', $customer->id)
                ->with('product')
                ->latest()
                ->get(),
            // By account or by email: a customer may have written before
            // signing up, and those messages belong on this page too.
            'conversations' => Conversation::query()
                ->where(fn ($query) => $query
                    ->where('user_id', $customer->id)
                    ->when($customer->email, fn ($inner) => $inner->orWhere('email', $customer->email)))
                ->with('latestMessage')
                ->latest('updated_at')
                ->get(),
        ]);
    }

    public function updateNotes(Request $request, User $customer): RedirectResponse
    {
        abort_if($customer->is_admin, 404);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $customer->update(['notes' => $validated['notes'] ?? null]);

        return back()->with('status', 'Notes saved.');
    }

    public function updateAccount(Request $request, User $customer): RedirectResponse
    {
        abort_if($customer->is_admin, 404);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($customer->id)],
        ]);

        $customer->update($validated);

        AdminActivityLog::record('customer.updated', $customer, 'Updated account details for '.$customer->name);

        return back()->with('status', 'Account details saved.');
    }

    public function sendResetLink(Request $request, User $customer): RedirectResponse|JsonResponse
    {
        abort_if($customer->is_admin, 404);

        $status = Password::sendResetLink(['email' => $customer->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            // The broker cools each account down for a minute between links;
            // that's a wait, not a failure, and the page should say which.
            $message = $status === Password::RESET_THROTTLED
                ? 'A link was already sent less than a minute ago. Wait before resending.'
                : 'Could not send the reset link.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->with('status', $message);
        }

        AdminActivityLog::record('customer.password_reset_sent', $customer, 'Sent a password reset link to '.$customer->name);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Password reset link sent.']);
        }

        return back()->with('status', 'Password reset link sent.');
    }

    /**
     * Bans the account: the login form refuses it, and every session it
     * already holds is cut on its next request. Orders, reviews and messages
     * already left stay — a ban stops the future, it doesn't rewrite the past.
     */
    public function ban(User $customer): RedirectResponse
    {
        abort_if($customer->is_admin, 404);

        if ($customer->isBanned()) {
            return back()->with('status', 'This account is already banned.');
        }

        $customer->banned_at = now();
        $customer->save();

        // Database-held sessions die right away rather than at the whim of
        // the middleware's next look; any other driver still gets caught by
        // the middleware.
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $customer->id)->delete();
        }

        AdminActivityLog::record('customer.banned', $customer, 'Banned '.$customer->name);

        return back()->with('status', 'Account banned.');
    }

    public function unban(User $customer): RedirectResponse
    {
        abort_if($customer->is_admin, 404);

        $customer->banned_at = null;
        $customer->save();

        AdminActivityLog::record('customer.unbanned', $customer, 'Lifted the ban on '.$customer->name);

        return back()->with('status', 'Ban lifted.');
    }
}
