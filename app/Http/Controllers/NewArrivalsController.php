<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\View\View;

class NewArrivalsController extends Controller
{
    public const LIMIT = 60;

    public function index(): View
    {
        $products = Product::query()
            ->active()
            ->with('category', 'discount', 'variants.supplier')
            ->latest()
            ->limit(self::LIMIT)
            ->get();

        return view('products.new-arrivals', compact('products'));
    }
}
