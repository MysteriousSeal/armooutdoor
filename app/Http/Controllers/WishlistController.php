<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\WishlistItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WishlistController extends Controller
{
    public function index(Request $request): View
    {
        $products = Product::query()
            ->active()
            ->whereHas('wishlistItems', fn ($query) => $query->where('user_id', $request->user()->id))
            ->with('category')
            ->get();

        return view('account.wishlist', [
            'products' => $products,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        WishlistItem::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'product_id' => $validated['product_id'],
        ]);

        $product = Product::query()->findOrFail($validated['product_id']);

        return back()->with('status', __('store.added_to_wishlist', [
            'product' => $product->localizedName(),
        ]));
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        WishlistItem::query()
            ->where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->delete();

        return back()->with('status', __('store.removed_from_wishlist', [
            'product' => $product->localizedName(),
        ]));
    }
}
