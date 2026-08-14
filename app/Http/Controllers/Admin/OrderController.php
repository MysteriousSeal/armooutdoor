<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderBillingAddressRequest;
use App\Http\Requests\Admin\UpdateOrderShippingAddressRequest;
use App\Models\Carrier;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\PackageType;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'search' => $search,
        ]);
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
