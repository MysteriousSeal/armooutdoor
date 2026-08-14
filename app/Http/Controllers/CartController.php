<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ShippingSetting;
use App\Support\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function show(Cart $cart): View
    {
        return view('cart.show', [
            'lines' => $cart->lines(),
            'total' => $cart->formattedTotal(),
            'itemCount' => $cart->quantity(),
            'freeShippingUnlocked' => ShippingSetting::current()->isUnlockedBy($cart->totalCents()),
        ]);
    }

    public function add(Request $request, Cart $cart): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:'.Cart::MAX_QUANTITY],
        ]);

        $product = Product::query()->findOrFail($validated['product_id']);

        if (! $product->is_active) {
            abort(404);
        }

        if (! $product->inStock()) {
            return back()->with('status', __('store.out_of_stock'));
        }

        $wanted = $validated['quantity'] ?? 1;
        $alreadyInCart = $cart->quantityOf($product);
        $allowed = max(0, $product->maxPurchasable() - $alreadyInCart);

        if ($allowed < 1) {
            return back()->with('status', __('store.stock_limit', ['count' => $product->quantity]));
        }

        $cart->add($product, min($wanted, $allowed));

        return back()->with('status', __('store.added_to_cart', [
            'product' => $product->localizedName(),
        ]));
    }

    public function update(Request $request, Cart $cart, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:'.Cart::MAX_QUANTITY],
        ]);

        $cart->update($product, $validated['quantity']);

        $status = $validated['quantity'] === 0
            ? __('store.removed_from_cart', ['product' => $product->localizedName()])
            : __('store.cart_updated');

        return back()->with('status', $status);
    }

    public function destroy(Cart $cart, Product $product): RedirectResponse
    {
        $cart->remove($product);

        return back()->with('status', __('store.removed_from_cart', [
            'product' => $product->localizedName(),
        ]));
    }
}
