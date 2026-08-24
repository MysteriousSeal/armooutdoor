<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StockMovementReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkOrderActionRequest;
use App\Http\Requests\Admin\StoreManualOrderRequest;
use App\Http\Requests\Admin\UpdateOrderBillingAddressRequest;
use App\Http\Requests\Admin\UpdateOrderShippingAddressRequest;
use App\Models\AdminActivityLog;
use App\Models\Carrier;
use App\Models\CompanySetting;
use App\Models\Marketplace;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PackageType;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingSetting;
use App\Models\User;
use App\Services\OrderStockAllocator;
use App\Support\Csv;
use App\Support\StockContext;
use App\Support\StripeDashboard;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function __construct(private readonly OrderStockAllocator $allocator) {}

    public function index(Request $request): View
    {
        $filters = $this->orderFilters($request);

        $orders = $this->filteredOrdersQuery($filters)
            ->with('user', 'statusHistories')
            ->withCount('items')
            ->latest()
            // paginate() plutôt que simplePaginate() : il faut le nombre de
            // pages pour les numéroter, et le total pour l'annoncer.
            ->paginate(20)
            ->withQueryString();

        $this->backfillMissingPaymentFees($orders->getCollection());

        // Money and the order count behind it cover archived orders too: the
        // sale happened, and hiding a row from the working list is no reason
        // to unmake it. Test orders are the only ones left out, since those
        // sales never happened at all.
        $salesOrders = Order::query()->excludingTest()->where('status', '!=', 'draft');

        // The operational counts stay on the working list, where archiving is
        // exactly the way to say "done with this one".
        $openOrders = Order::query()->whereNull('archived_at')->excludingTest()->where('status', '!=', 'draft');

        $amountCents = (clone $salesOrders)->sum('total_cents');
        $shippingCostCents = (clone $salesOrders)->sum('shipping_paid_cents');
        $commissionCostCents = (clone $salesOrders)->sum('marketplace_commission_cents');
        $paymentFeeCents = (clone $salesOrders)->sum('payment_fee_cents');
        $totalCostsCents = $shippingCostCents + $commissionCostCents + $paymentFeeCents;

        $percentOf = fn (int $part, int $whole): ?float => $whole > 0 ? round($part / $whole * 100, 2) : null;

        return view('admin.orders.index', [
            'orders' => $orders,
            'tab' => $filters['tab'],
            'kpis' => [
                'order_count' => (clone $salesOrders)->count(),
                'amount_cents' => $amountCents,
                'shipping_cost_cents' => $shippingCostCents,
                'shipping_cost_pct_amount' => $percentOf($shippingCostCents, $amountCents),
                'shipping_cost_pct_costs' => $percentOf($shippingCostCents, $totalCostsCents),
                'commission_cost_cents' => $commissionCostCents,
                'commission_cost_pct_amount' => $percentOf($commissionCostCents, $amountCents),
                'commission_cost_pct_costs' => $percentOf($commissionCostCents, $totalCostsCents),
                'payment_fee_cents' => $paymentFeeCents,
                'payment_fee_pct_amount' => $percentOf($paymentFeeCents, $amountCents),
                'payment_fee_pct_costs' => $percentOf($paymentFeeCents, $totalCostsCents),
                'total_costs_cents' => $totalCostsCents,
                'total_costs_pct_amount' => $percentOf($totalCostsCents, $amountCents),
                'perceived_total_cents' => $amountCents - $totalCostsCents,
                'perceived_total_pct_amount' => $percentOf($amountCents - $totalCostsCents, $amountCents),
                // Ventilation par statut sur le même périmètre que le total :
                // les trois chiffres doivent pouvoir se recouper avec lui.
                'shipped_count' => (clone $salesOrders)->where('status', 'shipped')->count(),
                'delivered_count' => (clone $salesOrders)->where('status', 'delivered')->count(),
                'refunded_count' => (clone $salesOrders)->where('status', 'refunded')->count(),
                'to_prepare_count' => (clone $openOrders)->whereIn('status', ['placed', 'preparing'])->count(),
                'missing_tracking_count' => (clone $openOrders)
                    ->where('status', 'shipped')
                    ->where(function ($query): void {
                        $query->whereNull('tracking_number')->orWhere('tracking_number', '');
                    })
                    ->count(),
            ],
            'orderCount' => Order::query()->whereNull('archived_at')->excludingTest()->where('status', '!=', 'draft')->count(),
            'draftCount' => Order::query()->excludingTest()->where('status', 'draft')->count(),
            'archivedCount' => Order::query()->whereNotNull('archived_at')->excludingTest()->where('status', '!=', 'draft')->count(),
            'testCount' => Order::query()->onlyTest()->count(),
            'search' => $filters['search'],
            'status' => $filters['status'],
            'marketplaceId' => $filters['marketplace_id'],
            'dateFrom' => $filters['date_from'],
            'dateTo' => $filters['date_to'],
            'marketplaces' => Marketplace::query()->orderBy('name')->get(),
            'statuses' => ['placed', 'preparing', 'shipped', 'delivered', 'refunded'],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->orderFilters($request);

        $orders = $this->filteredOrdersQuery($filters)->with('user')->latest()->get();

        return Csv::download(
            'orders-'.now()->format('Y-m-d').'.csv',
            // Shipping is the carrier's price; a discount code can waive it
            // without changing that, so the waiver needs its own column for
            // the row to reconcile: subtotal - discount + shipping
            // - free delivery = total.
            ['Number', 'Date', 'Customer', 'Email', 'Status', 'Archived', 'Marketplace', 'Carrier', 'Subtotal', 'Shipping', 'Discount', 'Free delivery', 'Total'],
            $orders->map(fn (Order $order): array => [
                $order->number,
                $order->created_at->format('Y-m-d H:i'),
                $order->user?->name ?? '',
                $order->user?->email ?? '',
                $order->status,
                $order->isArchived() ? 'yes' : 'no',
                $order->marketplace_name ?: '',
                $order->carrierName(),
                number_format($order->subtotal_cents / 100, 2, '.', ''),
                number_format($order->shipping_cents / 100, 2, '.', ''),
                number_format($order->discount_cents / 100, 2, '.', ''),
                number_format(($order->shipping_discount_cents ?? 0) / 100, 2, '.', ''),
                number_format($order->total_cents / 100, 2, '.', ''),
            ])
        );
    }

    /**
     * Self-heals orders whose payment fee wasn't captured at creation time
     * (Stripe's balance transaction can lag slightly behind payment
     * success) — fetched once here, on view, and persisted so it's never
     * queried again afterwards.
     *
     * @param  Collection<int, Order>  $orders
     */
    private function backfillMissingPaymentFees($orders): void
    {
        foreach ($orders as $order) {
            if ($order->payment_fee_cents !== null || $order->stripe_payment_intent_id === null) {
                continue;
            }

            $feeCents = StripeDashboard::fetchFeeCents($order->stripe_payment_intent_id);

            if ($feeCents !== null) {
                $order->update(['payment_fee_cents' => $feeCents]);
            }
        }
    }

    /**
     * @return array{tab: string, search: string, status: string, marketplace_id: ?int, date_from: string, date_to: string}
     */
    private function orderFilters(Request $request): array
    {
        return [
            'tab' => in_array($request->query('tab'), ['draft', 'archived', 'test'], true) ? $request->query('tab') : 'orders',
            'search' => trim((string) $request->query('search', '')),
            'status' => in_array($request->query('status'), ['placed', 'preparing', 'shipped', 'delivered', 'refunded'], true)
                ? $request->query('status')
                : '',
            'marketplace_id' => $request->filled('marketplace_id') ? (int) $request->query('marketplace_id') : null,
            'date_from' => (string) $request->query('date_from', ''),
            'date_to' => (string) $request->query('date_to', ''),
        ];
    }

    /**
     * @param  array{tab: string, search: string, status: string, marketplace_id: ?int, date_from: string, date_to: string}  $filters
     */
    private function filteredOrdersQuery(array $filters): Builder
    {
        // Drafts and orders are separated the same way in every tab, so the
        // tab counts and the KPI above them describe the same set. Archived
        // once swept up archived drafts too, which is why 4 + 62 never added
        // up to the 64 orders the KPI reported.
        //
        // The test tab holds every test order, archived or not, and the other
        // three hold none. So a test order is never in two tabs at once.
        return Order::query()
            ->when($filters['tab'] === 'test', fn ($query) => $query->onlyTest())
            ->when($filters['tab'] !== 'test', fn ($query) => $query->excludingTest())
            ->when($filters['tab'] === 'orders', fn ($query) => $query->whereNull('archived_at')->where('status', '!=', 'draft'))
            ->when($filters['tab'] === 'draft', fn ($query) => $query->where('status', 'draft'))
            ->when($filters['tab'] === 'archived', fn ($query) => $query->whereNotNull('archived_at')->where('status', '!=', 'draft'))
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['marketplace_id'] !== null, fn ($query) => $query->where('marketplace_id', $filters['marketplace_id']))
            ->when($filters['date_from'] !== '', fn ($query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] !== '', fn ($query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];
                $query->where(function ($query) use ($search): void {
                    $query->where('number', 'like', '%'.$search.'%')
                        ->orWhereHas('user', function ($query) use ($search): void {
                            $query->where('first_name', 'like', '%'.$search.'%')
                                ->orWhere('last_name', 'like', '%'.$search.'%')
                                ->orWhere('email', 'like', '%'.$search.'%');
                        });
                });
            });
    }

    public function create(): View
    {
        return view('admin.orders.create', [
            'order' => null,
            'customers' => $this->customerOptions(),
            'products' => Product::query()->active()->with('variants')->orderBy('name')->get(),
            'carriers' => Carrier::query()->active()->get(),
            'marketplaces' => Marketplace::query()->orderBy('name')->get(),
        ]);
    }

    public function edit(Order $order): View
    {
        abort_unless($order->isDraft(), 404);

        $order->load('items');

        return view('admin.orders.create', [
            'order' => $order,
            'customers' => $this->customerOptions(),
            'products' => Product::query()->active()->with('variants')->orderBy('name')->get(),
            'carriers' => Carrier::query()->active()->get(),
            'marketplaces' => Marketplace::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreManualOrderRequest $request): RedirectResponse
    {
        return $this->handleManualOrderSave($request, null);
    }

    public function update(StoreManualOrderRequest $request, Order $order): RedirectResponse
    {
        abort_unless($order->isDraft(), 404);

        return $this->handleManualOrderSave($request, $order);
    }

    private function handleManualOrderSave(StoreManualOrderRequest $request, ?Order $order): RedirectResponse
    {
        $wasNew = $order === null;
        $finalize = $request->input('action') === 'placed';

        try {
            $savedOrder = $this->saveManualOrder($request, $order);
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() === 'stock') {
                return back()->withInput()->with('status', 'One of the selected products no longer has enough stock.');
            }

            throw $exception;
        }

        $message = match (true) {
            ! $finalize => 'Draft saved.',
            $wasNew => 'Manual order created.',
            default => 'Draft finalized into an order.',
        };

        return redirect()
            ->route('admin.orders.show', $savedOrder)
            ->with('status', $message);
    }

    private function customerOptions()
    {
        return User::query()
            ->where('is_admin', false)
            ->where('external', false)
            ->with('addresses')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    private function resolveCustomer(StoreManualOrderRequest $request, ?Order $order): User
    {
        if ($request->input('customer_mode') === 'existing') {
            return User::query()->findOrFail($request->input('customer_id'));
        }

        $attributes = [
            'first_name' => $request->input('new_customer_first_name'),
            'last_name' => $request->input('new_customer_last_name'),
            'email' => $request->input('new_customer_email'),
            'external' => true,
        ];

        $existingExternal = $order?->user?->external === true ? $order->user : null;

        if ($existingExternal !== null) {
            $existingExternal->update($attributes);

            return $existingExternal;
        }

        return User::query()->create([...$attributes, 'password' => Str::random(32)]);
    }

    /**
     * @throws \RuntimeException with message "stock" when a product no longer has enough stock to finalize
     */
    protected function saveManualOrder(StoreManualOrderRequest $request, ?Order $order): Order
    {
        $items = $request->validItems();
        $carrier = Carrier::query()->where('active', true)->findOrFail($request->input('carrier_id'));
        $finalize = $request->input('action') === 'placed';

        $customer = $this->resolveCustomer($request, $order);

        $shippingSnapshot = [
            'label' => null,
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'line1' => $request->input('line1'),
            'line2' => $request->input('line2'),
            'postal_code' => $request->input('postal_code'),
            'city' => $request->input('city'),
            'country' => $request->input('country'),
            'phone' => $request->input('phone'),
        ];

        $billingSnapshot = [
            'label' => null,
            'first_name' => $request->input('billing_first_name'),
            'last_name' => $request->input('billing_last_name'),
            'line1' => $request->input('billing_line1'),
            'line2' => $request->input('billing_line2'),
            'postal_code' => $request->input('billing_postal_code'),
            'city' => $request->input('billing_city'),
            'country' => $request->input('billing_country'),
            'phone' => $request->input('billing_phone'),
        ];

        // Un point relais ne se déduit pas de l'adresse d'expédition : celle-ci
        // porte le nom du commerce, pas son identité de point de retrait.
        $relaySnapshot = $carrier->isRelay() ? $request->relaySnapshot() : null;

        $allocator = $this->allocator;

        $shippingPrice = $request->input('shipping_price');
        $marketplace = $request->input('marketplace_id')
            ? Marketplace::query()->find($request->input('marketplace_id'))
            : null;
        $discountType = $request->input('discount_type');
        $discountValue = $request->input('discount_value');

        return DB::transaction(function () use ($order, $customer, $carrier, $shippingSnapshot, $billingSnapshot, $relaySnapshot, $items, $shippingPrice, $marketplace, $discountType, $discountValue, $finalize, $allocator): Order {
            $productsQuery = Product::query()->whereIn('id', $items->pluck('product_id'));
            $products = $finalize
                ? $productsQuery->lockForUpdate()->get()->keyBy('id')
                : $productsQuery->get()->keyBy('id');

            $variantIds = $items->pluck('variant_id')->filter();
            $variants = collect();

            if ($variantIds->isNotEmpty()) {
                $variantsQuery = ProductVariant::query()->whereIn('id', $variantIds);
                $variants = $finalize
                    ? $variantsQuery->lockForUpdate()->get()->keyBy('id')
                    : $variantsQuery->get()->keyBy('id');
            }

            $subtotal = 0;
            $weightGrams = 0;

            foreach ($items as $item) {
                $product = $products->get($item['product_id']);
                $variant = $item['variant_id'] ? $variants->get($item['variant_id']) : null;
                if ($product === null) {
                    throw new \RuntimeException('stock');
                }

                // La même règle que celle qui prendra le stock plus bas : les
                // deux ne doivent pas pouvoir se contredire.
                if ($finalize && ! $allocator->canAllocate($product, $variant, $item['quantity'], allowBackorder: false)) {
                    throw new \RuntimeException('stock');
                }

                $subtotal += $item['unit_price_cents'] * $item['quantity'];
                $weightGrams += ($product->weight_grams ?? 0) * $item['quantity'];
            }

            $shipping = filled($shippingPrice)
                ? (int) round(((float) $shippingPrice) * 100)
                : ShippingSetting::current()->effectivePriceCents($carrier, $subtotal, $weightGrams);

            $discountCents = 0;
            $discountSnapshot = null;

            if (filled($discountType) && filled($discountValue)) {
                $value = $discountType === 'percentage'
                    ? (int) round((float) $discountValue)
                    : (int) round(((float) $discountValue) * 100);

                $discountCents = $discountType === 'percentage'
                    ? (int) round($subtotal * $value / 100)
                    : min($subtotal, $value);

                $discountSnapshot = ['code' => null, 'type' => $discountType, 'value' => $value];
            }

            $attributes = [
                'is_manual' => true,
                'user_id' => $customer->id,
                'status' => $finalize ? 'placed' : 'draft',
                'address_snapshot' => $shippingSnapshot,
                'billing_address_snapshot' => $billingSnapshot,
                'carrier_id' => $carrier->id,
                'carrier_method' => $carrier->method,
                'carrier_snapshot' => $carrier->toSnapshot(),
                'relay_snapshot' => $relaySnapshot,
                'subtotal_cents' => $subtotal,
                'shipping_cents' => $shipping,
                'discount_code_id' => null,
                'discount_code_snapshot' => $discountSnapshot,
                'discount_cents' => $discountCents,
                'total_cents' => $subtotal - $discountCents + $shipping,
                'payment_method' => 'card',
                'marketplace_id' => $marketplace?->id,
                'marketplace_name' => $marketplace?->name,
                'marketplace_note' => $marketplace?->note,
            ];

            if ($order === null) {
                $savedOrder = Order::query()->create([...$attributes, 'number' => Order::generateNumber()]);
            } else {
                $wasDraft = $order->isDraft();
                $order->update($attributes);
                $savedOrder = $order;

                if ($finalize && $wasDraft) {
                    $savedOrder->statusHistories()->create(['status' => 'placed']);
                }

                $savedOrder->items()->delete();
            }

            foreach ($items as $item) {
                $product = $products->get($item['product_id']);
                $variant = $item['variant_id'] ? $variants->get($item['variant_id']) : null;

                // Une commande saisie à la main refuse ce qu'elle ne peut pas
                // couvrir : la validation l'a déjà dit, et rien ici ne doit
                // mettre l'acheteur en réassort à son insu.
                $allocation = $finalize
                    ? $allocator->allocate($product, $variant, $item['quantity'], allowBackorder: false, reason: StockMovementReason::ManualOrder, order: $savedOrder)
                    : null;

                OrderItem::query()->create([
                    'order_id' => $savedOrder->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'product_slug' => $product->slug,
                    'name' => $product->name,
                    'variant_label' => $variant?->label(),
                    'sku' => $variant?->sku ?? $product->sku,
                    'image' => $product->image,
                    'was_backordered' => $allocation?->backordered ?? false,
                    'supplier_lead_time_days' => $allocation?->supplierLeadTimeDays,
                    'unit_price_cents' => $item['unit_price_cents'],
                    'quantity' => $item['quantity'],
                    'line_cents' => $item['unit_price_cents'] * $item['quantity'],
                ]);
            }

            return $savedOrder;
        });
    }

    public function show(Order $order): View
    {
        return view('admin.orders.show', $this->showData($order));
    }

    /**
     * @return array<string, mixed>
     */
    private function showData(Order $order): array
    {
        $order->load(['user', 'items.product.discount', 'items.product.supplier', 'items.variant', 'statusHistories']);

        $this->backfillMissingPaymentFees(collect([$order]));

        return [
            'order' => $order,
            'carriers' => Carrier::query()->orderBy('sort_order')->get(),
            'packageTypes' => PackageType::query()->orderBy('name')->get(),
            'stripePaymentIntentUrl' => $order->stripe_payment_intent_id ? StripeDashboard::paymentIntentUrl($order->stripe_payment_intent_id) : null,
            'stripeCustomerUrl' => $order->stripe_customer_id ? StripeDashboard::customerUrl($order->stripe_customer_id) : null,
        ];
    }

    /**
     * A status change rewrites the badge, the action buttons, the downloads,
     * the timeline and the confirm modals. Rather than rebuilding any of that
     * in JavaScript — where it would drift from the Blade — the server
     * re-renders the page and the client swaps those regions out of it.
     */
    private function statusChangeResponse(Request $request, Order $order, string $message): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'status' => $order->status,
                'html' => view('admin.orders.show', $this->showData($order->fresh()))->render(),
            ]);
        }

        return back()->with('status', $message);
    }

    public function prepare(Request $request, Order $order): RedirectResponse|JsonResponse
    {
        abort_if($order->isDraft(), 404);

        $order->markStatus('preparing');
        AdminActivityLog::record('order.preparing', $order, 'Marked order '.$order->number.' as being prepared');

        return $this->statusChangeResponse($request, $order, 'Order marked as being prepared.');
    }

    public function deliver(Request $request, Order $order): RedirectResponse|JsonResponse
    {
        abort_if($order->isDraft(), 404);

        $order->markStatus('delivered');
        AdminActivityLog::record('order.delivered', $order, 'Marked order '.$order->number.' as delivered');

        return $this->statusChangeResponse($request, $order, 'Order marked as delivered.');
    }

    public function ship(Request $request, Order $order): RedirectResponse|JsonResponse
    {
        abort_if($order->isDraft(), 404);

        $order->markStatus('shipped');
        AdminActivityLog::record('order.shipped', $order, 'Marked order '.$order->number.' as shipped');

        return $this->statusChangeResponse($request, $order, 'Order marked as shipped.');
    }

    public function refund(Request $request, Order $order): RedirectResponse|JsonResponse
    {
        abort_if($order->isDraft() || $order->status === 'refunded', 403);

        $order->markStatus('refunded');
        AdminActivityLog::record('order.refunded', $order, 'Marked order '.$order->number.' as refunded');

        return $this->statusChangeResponse($request, $order, 'Order marked as refunded.');
    }

    public function archive(Order $order): RedirectResponse
    {
        abort_unless($order->canBeArchived(), 403);

        $order->archive();
        AdminActivityLog::record('order.archived', $order, 'Archived order '.$order->number);

        return back()->with('status', 'Order archived.');
    }

    public function unarchive(Order $order): RedirectResponse
    {
        abort_unless($order->canBeArchived(), 403);

        $order->unarchive();
        AdminActivityLog::record('order.unarchived', $order, 'Unarchived order '.$order->number);

        return back()->with('status', 'Order unarchived.');
    }

    public function destroy(Order $order): RedirectResponse
    {
        // Only drafts. Anything further along is a record of something that
        // happened, and those are archived rather than destroyed.
        abort_unless($order->canBeDeleted(), 403);

        // Logged before the delete, so the entry still carries the number.
        AdminActivityLog::record('order.deleted', $order, 'Deleted draft order '.$order->number);
        $order->delete();

        return redirect()
            ->route('admin.orders.index', ['tab' => 'draft'])
            ->with('status', 'Draft deleted.');
    }

    public function bulkDestroy(BulkOrderActionRequest $request): RedirectResponse
    {
        $orders = Order::query()
            ->whereIn('id', $request->validated('order_ids'))
            ->where('status', 'draft')
            ->get();

        foreach ($orders as $order) {
            AdminActivityLog::record('order.deleted', $order, 'Deleted draft order '.$order->number);
            $order->delete();
        }

        return back()->with('status', $this->bulkStatus($orders->count(), 'deleted', 'delete'));
    }

    public function markTest(Order $order): RedirectResponse
    {
        abort_unless($order->canBeMarkedAsTest(), 403);

        $order->markAsTest();
        AdminActivityLog::record('order.marked_test', $order, 'Marked order '.$order->number.' as a test order');

        return back()->with('status', 'Order marked as a test order.');
    }

    public function unmarkTest(Order $order): RedirectResponse
    {
        abort_unless($order->canBeMarkedAsTest(), 403);

        $order->unmarkAsTest();
        AdminActivityLog::record('order.unmarked_test', $order, 'Unmarked order '.$order->number.' as a test order');

        return back()->with('status', 'Order is no longer a test order.');
    }

    public function bulkMarkTest(BulkOrderActionRequest $request): RedirectResponse
    {
        $orders = Order::query()
            ->whereIn('id', $request->validated('order_ids'))
            ->excludingTest()
            ->where('status', '!=', 'draft')
            ->get();

        foreach ($orders as $order) {
            $order->markAsTest();
            AdminActivityLog::record('order.marked_test', $order, 'Marked order '.$order->number.' as a test order');
        }

        return back()->with('status', $this->bulkStatus($orders->count(), 'marked as test', 'mark as test'));
    }

    public function bulkUnmarkTest(BulkOrderActionRequest $request): RedirectResponse
    {
        $orders = Order::query()
            ->whereIn('id', $request->validated('order_ids'))
            ->onlyTest()
            ->where('status', '!=', 'draft')
            ->get();

        foreach ($orders as $order) {
            $order->unmarkAsTest();
            AdminActivityLog::record('order.unmarked_test', $order, 'Unmarked order '.$order->number.' as a test order');
        }

        return back()->with('status', $this->bulkStatus($orders->count(), 'unmarked as test', 'unmark as test'));
    }

    public function bulkArchive(BulkOrderActionRequest $request): RedirectResponse
    {
        // Already-archived rows are skipped rather than refused: someone else
        // may have archived one while this page sat open, and that is no
        // reason to fail the whole batch.
        $orders = Order::query()
            ->whereIn('id', $request->validated('order_ids'))
            ->whereNull('archived_at')
            ->where('status', '!=', 'draft')
            ->get();

        foreach ($orders as $order) {
            $order->archive();
            AdminActivityLog::record('order.archived', $order, 'Archived order '.$order->number);
        }

        return back()->with('status', $this->bulkStatus($orders->count(), 'archived', 'archive'));
    }

    public function bulkUnarchive(BulkOrderActionRequest $request): RedirectResponse
    {
        $orders = Order::query()
            ->whereIn('id', $request->validated('order_ids'))
            ->whereNotNull('archived_at')
            ->where('status', '!=', 'draft')
            ->get();

        foreach ($orders as $order) {
            $order->unarchive();
            AdminActivityLog::record('order.unarchived', $order, 'Unarchived order '.$order->number);
        }

        return back()->with('status', $this->bulkStatus($orders->count(), 'unarchived', 'unarchive'));
    }

    /**
     * Counts what actually changed, not what was submitted — otherwise the
     * message overstates the result whenever a row was skipped.
     */
    private function bulkStatus(int $count, string $past, string $infinitive): string
    {
        if ($count === 0) {
            return 'Nothing to '.$infinitive.'.';
        }

        return $count.' order'.($count === 1 ? '' : 's').' '.$past.'.';
    }

    public function invoice(Order $order): Response
    {
        abort_unless($order->invoiceIsAvailable(), 404);

        $order->load('items.product', 'items.variant');

        $pdf = Pdf::loadView('admin.orders.invoice-pdf', [
            'order' => $order,
            'company' => CompanySetting::current(),
        ])->setPaper('a4');

        return $pdf->download('facture-'.$order->number.'.pdf');
    }

    public function deliverySlip(Order $order): Response
    {
        abort_if($order->isDraft(), 404);

        $order->load('items.product', 'items.variant');

        $pdf = Pdf::loadView('admin.orders.delivery-slip-pdf', [
            'order' => $order,
            'company' => CompanySetting::current(),
        ])->setPaper('a4');

        return $pdf->download('bdl-'.$order->number.'.pdf');
    }

    public function updateTracking(Request $request, Order $order): RedirectResponse
    {
        abort_if($order->isDraft(), 404);

        $validated = $request->validate([
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'tracking_carrier_id' => ['nullable', Rule::exists('carriers', 'id')],
            'package_type_id' => ['nullable', Rule::exists('package_types', 'id')],
        ]);

        $packageType = $validated['package_type_id'] ?? null
            ? PackageType::query()->find($validated['package_type_id'])
            : null;

        $order->update([
            ...$validated,
            'package_type_name' => $packageType?->name,
        ]);
        AdminActivityLog::record('order.tracking_updated', $order, 'Updated tracking for order '.$order->number);

        return back()->with('status', 'Tracking details saved.');
    }

    public function updateMarketplaceCommission(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->is_manual && $order->marketplace_id, 404);

        $validated = $request->validate([
            'marketplace_commission' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
        ]);

        $commission = $validated['marketplace_commission'] ?? null;

        $order->update([
            'marketplace_commission_cents' => $commission === null || $commission === ''
                ? null
                : (int) round(((float) $commission) * 100),
        ]);

        return back()->with('status', 'Marketplace commission saved.');
    }

    public function updateShippingPaid(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->is_manual && $order->marketplace_id, 404);

        $validated = $request->validate([
            'shipping_paid' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
        ]);

        $shippingPaid = $validated['shipping_paid'] ?? null;

        $order->update([
            'shipping_paid_cents' => $shippingPaid === null || $shippingPaid === ''
                ? null
                : (int) round(((float) $shippingPaid) * 100),
        ]);

        return back()->with('status', 'Shipping paid saved.');
    }

    /**
     * Turn a draft into a real order without passing through the edit form.
     *
     * The draft already holds everything the order needs; the only thing left
     * to do is what the edit form's "finalize" does — take the stock and start
     * the status history. Stock is allowed to go negative: the sale happened on
     * the marketplace whatever the shelf says, and refusing here would only
     * stop the shop from recording it.
     */
    public function validateDraft(Order $order): RedirectResponse
    {
        abort_unless($order->isDraft(), 404);

        DB::transaction(function () use ($order): void {
            StockContext::during(StockMovementReason::DraftValidated, function () use ($order): void {
                foreach ($order->items as $item) {
                    if ($item->product_variant_id !== null) {
                        ProductVariant::query()->whereKey($item->product_variant_id)->first()?->decrement('quantity', $item->quantity);
                        $item->product?->reconcileQuantity();
                    } else {
                        $item->product?->decrement('quantity', $item->quantity);
                    }
                }
            }, subject: $order);

            $order->update(['status' => 'placed']);
            $order->statusHistories()->create(['status' => 'placed']);
        });

        AdminActivityLog::record('order.draft_validated', $order, 'Validated draft order '.$order->number);

        return back()->with('status', 'Draft validated into an order.');
    }

    public function updateShippingAddress(UpdateOrderShippingAddressRequest $request, Order $order): RedirectResponse
    {
        abort_unless($order->addressIsEditable(), 403);

        $order->update(['address_snapshot' => ['label' => null, ...$request->validated()]]);
        AdminActivityLog::record('order.shipping_address_updated', $order, 'Updated shipping address for order '.$order->number);

        return back()->with('status', 'Shipping address updated for this order.');
    }

    public function updateBillingAddress(UpdateOrderBillingAddressRequest $request, Order $order): RedirectResponse
    {
        abort_unless($order->addressIsEditable(), 403);

        $order->update(['billing_address_snapshot' => ['label' => null, ...$request->validated()]]);
        AdminActivityLog::record('order.billing_address_updated', $order, 'Updated billing address for order '.$order->number);

        return back()->with('status', 'Billing address updated for this order.');
    }
}
