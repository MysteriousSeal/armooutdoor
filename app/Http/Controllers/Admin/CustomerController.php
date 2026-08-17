<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $tab = in_array($request->query('tab'), ['with-orders', 'no-orders'], true)
            ? $request->query('tab')
            : 'all';

        $shopCustomers = fn () => User::query()->where('is_admin', false)->where('external', false);
        $countedOrders = fn ($query) => $query->whereNull('archived_at')->where('status', '!=', 'draft');
        $spentOrders = fn ($query) => $query->whereNull('archived_at')->whereNotIn('status', ['draft', 'refunded']);

        $customers = $shopCustomers()
            ->withCount([
                'orders' => $countedOrders,
                'addresses',
            ])
            ->withSum(['orders as spent_cents' => $spentOrders], 'total_cents')
            ->withMax(['orders as last_order_at' => $countedOrders], 'created_at')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->when($tab === 'with-orders', fn ($query) => $query->whereHas('orders', $countedOrders))
            ->when($tab === 'no-orders', fn ($query) => $query->whereDoesntHave('orders', $countedOrders))
            ->latest()
            ->simplePaginate(20)
            ->withQueryString();

        $customerCount = $shopCustomers()->count();
        $withOrdersCount = $shopCustomers()->whereHas('orders', $countedOrders)->count();

        return view('admin.customers.index', [
            'customers' => $customers,
            'customerCount' => $customerCount,
            'withOrdersCount' => $withOrdersCount,
            'noOrdersCount' => $customerCount - $withOrdersCount,
            'newCustomers30d' => $shopCustomers()->where('created_at', '>=', now()->subDays(30))->count(),
            'search' => $search,
            'tab' => $tab,
        ]);
    }

    public function show(User $customer): View
    {
        abort_if($customer->is_admin, 404);

        $customer->load('addresses');

        return view('admin.customers.show', [
            'customer' => $customer,
            'orders' => $customer->orders()->whereNull('archived_at')->withCount('items')->get(),
            'spentCents' => (int) $customer->orders()
                ->whereNull('archived_at')
                ->whereNotIn('status', ['draft', 'refunded'])
                ->sum('total_cents'),
        ]);
    }
}
