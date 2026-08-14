<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(): View
    {
        $orders = request()->user()->orders()->withCount('items')->get();

        return view('orders.index', compact('orders'));
    }

    public function show(Order $order): View
    {
        abort_unless($order->user_id === request()->user()->id, 404);

        $order->load(['items', 'statusHistories', 'trackingCarrier']);

        return view('orders.show', compact('order'));
    }
}
