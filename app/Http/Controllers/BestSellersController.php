<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\View\View;

class BestSellersController extends Controller
{
    public const LIMIT = 60;

    public function index(): View
    {
        $soldQuantities = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', ['placed', 'preparing', 'shipped', 'delivered'])
            ->selectRaw('order_items.product_id, SUM(order_items.quantity) as total_quantity')
            ->groupBy('order_items.product_id')
            ->orderByDesc('total_quantity')
            ->limit(self::LIMIT)
            ->pluck('total_quantity', 'product_id');

        $products = Product::query()
            ->active()
            ->whereIn('id', $soldQuantities->keys())
            ->with('category', 'discount', 'variants.supplier')
            ->get()
            ->sortByDesc(fn (Product $product): int => $soldQuantities[$product->id])
            ->values();

        return view('products.best-sellers', compact('products'));
    }
}
