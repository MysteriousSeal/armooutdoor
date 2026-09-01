<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function index(): View
    {
        $user = request()->user();
        $orders = $user->orders()->whereNull('archived_at')->where('status', '!=', 'draft')->withCount('items')->get();

        // Which of these orders still wait on a proof of age. Resolved in two
        // queries for the whole page rather than one per row: a customer with
        // forty orders should not cost forty lookups to draw a badge.
        $identity = $user->identityStatus();

        $ageMark = match ($identity['state']) {
            'verified' => null,
            'pending' => 'pending',
            default => 'action',
        };

        $ordersNeedingProof = $ageMark === null
            ? collect()
            : $this->ordersAwaitingProof($orders);

        return view('orders.index', [
            'orders' => $orders,
            'ordersNeedingProof' => $ordersNeedingProof,
            'ageMark' => $ageMark,
        ]);
    }

    /**
     * The listed orders that hold a restricted article and have not left yet.
     *
     * Once a parcel has gone, a badge asks for something that can no longer
     * change anything about it.
     *
     * @param  Collection<int, Order>  $orders
     * @return Collection<int, int>
     */
    private function ordersAwaitingProof($orders)
    {
        $pending = $orders->whereIn('status', ['placed', 'preparing']);

        if ($pending->isEmpty()) {
            return collect();
        }

        return OrderItem::query()
            ->whereIn('order_id', $pending->pluck('id'))
            ->whereIn('product_id', Product::query()->where('age_restricted', true)->select('id'))
            ->distinct()
            ->pluck('order_id');
    }

    public function show(Order $order): View
    {
        abort_unless($order->user_id === request()->user()->id && ! $order->isDraft() && ! $order->isArchived(), 404);

        $order->load(['items.product.discount', 'items.variant', 'statusHistories', 'trackingCarrier']);

        // Only while the parcel is still here: once it has gone, asking for a
        // proof is telling somebody about a door that has already closed.
        $awaitingDispatch = in_array($order->status, ['placed', 'preparing'], true);

        $ageRestrictedNames = $awaitingDispatch
            ? Product::query()
                ->whereIn('id', $order->items->pluck('product_id')->filter())
                ->where('age_restricted', true)
                ->get()
                ->mapWithKeys(fn (Product $product): array => [$product->id => $product->localizedName()])
            : collect();

        return view('orders.show', [
            'order' => $order,
            'ageRestrictedNames' => $ageRestrictedNames,
            'identityStatus' => $order->user?->identityStatus(),
        ]);
    }

    public function invoice(Order $order): Response
    {
        abort_unless($order->user_id === request()->user()->id && ! $order->isArchived(), 404);
        abort_unless($order->invoiceIsAvailable(), 404);

        $order->load('items.product', 'items.variant');

        $pdf = Pdf::loadView('admin.orders.invoice-pdf', [
            'order' => $order,
            'company' => CompanySetting::current(),
        ])->setPaper('a4');

        return $pdf->download('facture-'.$order->number.'.pdf');
    }
}
