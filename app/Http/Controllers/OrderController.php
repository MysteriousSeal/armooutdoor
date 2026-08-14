<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

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

    public function invoice(Order $order): Response
    {
        abort_unless($order->user_id === request()->user()->id, 404);
        abort_unless($order->invoiceIsAvailable(), 404);

        $order->load('items.product');

        $pdf = Pdf::loadView('admin.orders.invoice-pdf', [
            'order' => $order,
            'company' => CompanySetting::current(),
        ])->setPaper('a4');

        return $pdf->download('invoice-'.$order->number.'.pdf');
    }
}
