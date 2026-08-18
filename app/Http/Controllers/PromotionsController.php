<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\View\View;

class PromotionsController extends Controller
{
    public function index(): View
    {
        $products = Product::query()
            ->active()
            ->whereHas('discount')
            ->with('category', 'discount')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (Product $product): bool => $product->hasDiscount())
            ->values();

        return view('products.promotions', compact('products'));
    }
}
