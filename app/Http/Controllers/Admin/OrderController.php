<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreManualOrderRequest;
use App\Http\Requests\Admin\UpdateOrderBillingAddressRequest;
use App\Http\Requests\Admin\UpdateOrderShippingAddressRequest;
use App\Models\Carrier;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PackageType;
use App\Models\Product;
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

        $orders = Order::query()
            ->with('user')
            ->withCount('items')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('number', 'like', '%'.$search.'%')
                        ->orWhereHas('user', function ($query) use ($search): void {
                            $query->where('name', 'like', '%'.$search.'%')
                                ->orWhere('email', 'like', '%'.$search.'%');
                        });
                });
            })
            ->latest()
            ->simplePaginate(20)
            ->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'orderCount' => Order::query()->count(),
            'toPrepareCount' => Order::query()->whereIn('status', ['placed', 'preparing'])->count(),
            'missingTrackingCount' => Order::query()
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
            'customers' => User::query()
                ->where('is_admin', false)
                ->where('external', false)
                ->orderBy('name')
                ->get(),
            'products' => Product::query()->active()->orderBy('name')->get(),
            'carriers' => Carrier::query()->active()->get(),
        ]);
    }

    public function store(StoreManualOrderRequest $request): RedirectResponse
    {
        $items = $request->validItems();
        $carrier = Carrier::query()->where('active', true)->findOrFail($request->input('carrier_id'));

        $customer = $request->input('customer_mode') === 'existing'
            ? User::query()->findOrFail($request->input('customer_id'))
            : User::query()->create([
                'name' => $request->input('new_customer_name'),
                'email' => $request->input('new_customer_email'),
                'password' => Str::random(32),
                'external' => true,
            ]);

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

        $billingSnapshot = $request->boolean('billing_same_as_shipping')
            ? $shippingSnapshot
            : [
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

        try {
            $order = DB::transaction(function () use ($customer, $carrier, $shippingSnapshot, $billingSnapshot, $items): Order {
                $products = Product::query()
                    ->whereIn('id', $items->pluck('product_id'))
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $subtotal = 0;

                foreach ($items as $item) {
                    $product = $products->get($item['product_id']);

                    if ($product === null || $product->quantity < $item['quantity']) {
                        throw new \RuntimeException('stock');
                    }

                    $subtotal += $product->price_cents * $item['quantity'];
                }

                $shipping = ShippingSetting::current()->effectivePriceCents($carrier, $subtotal);

                $order = Order::query()->create([
                    'number' => Order::generateNumber(),
                    'user_id' => $customer->id,
                    'status' => 'placed',
                    'address_snapshot' => $shippingSnapshot,
                    'billing_address_snapshot' => $billingSnapshot,
                    'carrier_id' => $carrier->id,
                    'carrier_method' => $carrier->method,
                    'carrier_snapshot' => $carrier->toSnapshot(),
                    'subtotal_cents' => $subtotal,
                    'shipping_cents' => $shipping,
                    'total_cents' => $subtotal + $shipping,
                    'payment_method' => 'card',
                ]);

                foreach ($items as $item) {
                    $product = $products->get($item['product_id']);
                    $product->decrement('quantity', $item['quantity']);

                    OrderItem::query()->create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_slug' => $product->slug,
                        'name' => $product->name,
                        'image' => $product->image,
                        'unit_price_cents' => $product->price_cents,
                        'quantity' => $item['quantity'],
                        'line_cents' => $product->price_cents * $item['quantity'],
                    ]);
                }

                return $order;
            });
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() === 'stock') {
                return back()->withInput()->with('status', 'One of the selected products no longer has enough stock.');
            }

            throw $exception;
        }

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('status', 'Manual order created.');
    }

    public function show(Order $order): View
    {
        $order->load(['user', 'items.product', 'statusHistories']);

        return view('admin.orders.show', [
            'order' => $order,
            'carriers' => Carrier::query()->orderBy('sort_order')->get(),
            'packageTypes' => PackageType::query()->orderBy('name')->get(),
        ]);
    }

    public function prepare(Order $order): RedirectResponse
    {
        $order->markStatus('preparing');

        return back()->with('status', 'Order marked as being prepared.');
    }

    public function ship(Order $order): RedirectResponse
    {
        $order->markStatus('shipped');

        return back()->with('status', 'Order marked as shipped.');
    }

    public function refund(Order $order): RedirectResponse
    {
        abort_if($order->status === 'refunded', 403);

        $order->markStatus('refunded');

        return back()->with('status', 'Order marked as refunded.');
    }

    public function invoice(Order $order): Response
    {
        abort_unless($order->invoiceIsAvailable(), 404);

        $order->load('items.product');

        $pdf = Pdf::loadView('admin.orders.invoice-pdf', [
            'order' => $order,
            'company' => CompanySetting::current(),
        ])->setPaper('a4');

        return $pdf->download('invoice-'.$order->number.'.pdf');
    }

    public function updateTracking(Request $request, Order $order): RedirectResponse
    {
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
