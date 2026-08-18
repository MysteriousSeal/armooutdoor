<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function show(Product $product): View
    {
        abort_unless($product->is_active, 404);

        $product->load('category.parent', 'images', 'variants.supplier', 'reviews.user', 'discount', 'supplier');

        $related = Product::query()
            ->active()
            ->with('category', 'discount', 'variants.supplier')
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->orderBy('sort_order')
            ->limit(4)
            ->get();

        return view('products.show', compact('product', 'related'));
    }
}
