<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Carrier;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

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

    public function updateTracking(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'tracking_carrier_id' => ['nullable', Rule::exists('carriers', 'id')],
        ]);

        $order->update($validated);

        return back()->with('status', 'Tracking details saved.');
    }
}
