<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreManualOrderRequest;
use App\Http\Requests\Admin\UpdateOrderBillingAddressRequest;
use App\Http\Requests\Admin\UpdateOrderShippingAddressRequest;
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
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $tab = in_array($request->query('tab'), ['draft', 'archived'], true) ? $request->query('tab') : 'orders';

        $orders = Order::query()
            ->with('user')
            ->withCount('items')
            ->when($tab === 'archived', fn ($query) => $query->whereNotNull('archived_at'))
            ->when($tab !== 'archived', fn ($query) => $query->whereNull('archived_at'))
            ->when($tab === 'draft', fn ($query) => $query->where('status', 'draft'))
            ->when($tab === 'orders', fn ($query) => $query->where('status', '!=', 'draft'))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('number', 'like', '%'.$search.'%')
                        ->orWhereHas('user', function ($query) use ($search): void {
                            $query->where('first_name', 'like', '%'.$search.'%')
                                ->orWhere('last_name', 'like', '%'.$search.'%')
                                ->orWhere('email', 'like', '%'.$search.'%');
                        });
                });
            })
            ->latest()
            ->simplePaginate(20)
            ->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'tab' => $tab,
            'orderCount' => Order::query()->whereNull('archived_at')->where('status', '!=', 'draft')->count(),
            'draftCount' => Order::query()->whereNull('archived_at')->where('status', 'draft')->count(),
            'archivedCount' => Order::query()->whereNotNull('archived_at')->count(),
            'toPrepareCount' => Order::query()->whereNull('archived_at')->whereIn('status', ['placed', 'preparing'])->count(),
            'missingTrackingCount' => Order::query()
                ->whereNull('archived_at')
                ->where('status', 'shipped')
                ->where(function ($query): void {
                    $query->whereNull('tracking_number')->orWhere('tracking_number', '');
                })
                ->count(),
            'search' => $search,
        ]);
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

        $shippingPrice = $request->input('shipping_price');
        $marketplace = $request->input('marketplace_id')
            ? Marketplace::query()->find($request->input('marketplace_id'))
            : null;
        $discountType = $request->input('discount_type');
        $discountValue = $request->input('discount_value');

        return DB::transaction(function () use ($order, $customer, $carrier, $shippingSnapshot, $billingSnapshot, $items, $shippingPrice, $marketplace, $discountType, $discountValue, $finalize): Order {
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
                $availableQuantity = $variant?->quantity ?? $product?->quantity;

                if ($product === null || ($finalize && $availableQuantity < $item['quantity'])) {
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

                if ($finalize) {
                    if ($variant !== null) {
                        $variant->decrement('quantity', $item['quantity']);
                        $product->reconcileQuantity();
                    } else {
                        $product->decrement('quantity', $item['quantity']);
                    }
                }

                OrderItem::query()->create([
                    'order_id' => $savedOrder->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'product_slug' => $product->slug,
                    'name' => $product->name,
                    'variant_label' => $variant?->label(),
                    'sku' => $variant?->sku ?? $product->sku,
                    'image' => $product->image,
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
        $order->load(['user', 'items.product.discount', 'items.variant', 'statusHistories']);

        return view('admin.orders.show', [
            'order' => $order,
            'carriers' => Carrier::query()->orderBy('sort_order')->get(),
            'packageTypes' => PackageType::query()->orderBy('name')->get(),
        ]);
    }

    public function prepare(Order $order): RedirectResponse
    {
        abort_if($order->isDraft(), 404);

        $order->markStatus('preparing');

        return back()->with('status', 'Order marked as being prepared.');
    }

    public function ship(Order $order): RedirectResponse
    {
        abort_if($order->isDraft(), 404);

        $order->markStatus('shipped');

        return back()->with('status', 'Order marked as shipped.');
    }

    public function refund(Order $order): RedirectResponse
    {
        abort_if($order->isDraft() || $order->status === 'refunded', 403);

        $order->markStatus('refunded');

        return back()->with('status', 'Order marked as refunded.');
    }

    public function archive(Order $order): RedirectResponse
    {
        $order->archive();

        return back()->with('status', 'Order archived.');
    }

    public function unarchive(Order $order): RedirectResponse
    {
        $order->unarchive();

        return back()->with('status', 'Order unarchived.');
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

        return back()->with('status', 'Tracking details saved.');
    }

    public function updateShippingAddress(UpdateOrderShippingAddressRequest $request, Order $order): RedirectResponse
    {
        abort_unless($order->addressIsEditable(), 403);

        $order->update(['address_snapshot' => ['label' => null, ...$request->validated()]]);

        return back()->with('status', 'Shipping address updated for this order.');
    }

    public function updateBillingAddress(UpdateOrderBillingAddressRequest $request, Order $order): RedirectResponse
    {
        abort_unless($order->addressIsEditable(), 403);

        $order->update(['billing_address_snapshot' => ['label' => null, ...$request->validated()]]);

        return back()->with('status', 'Billing address updated for this order.');
    }
}
