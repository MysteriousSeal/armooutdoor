<?php

namespace App\Http\Controllers;

use App\Models\Carrier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingSetting;
use App\Support\Cart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CartController extends Controller
{
    public function show(Cart $cart): View
    {
        $shippingSetting = ShippingSetting::current();
        $subtotalCents = $cart->totalCents();
        $weightGrams = $cart->totalWeightGrams();

        $cheapestShippingCents = Carrier::query()->active()->get()
            ->filter(fn (Carrier $carrier): bool => $cart->allowsCarrier($carrier))
            ->map(fn (Carrier $carrier): int => $shippingSetting->effectivePriceCents($carrier, $subtotalCents, $weightGrams))
            ->min();

        return view('cart.show', [
            'lines' => $cart->lines(),
            'total' => $cart->formattedTotal(),
            'itemCount' => $cart->quantity(),
            'freeShippingUnlocked' => $shippingSetting->isUnlockedBy($subtotalCents),
            'cheapestShippingCents' => $cheapestShippingCents,
        ]);
    }

    public function add(Request $request, Cart $cart): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => [
                'nullable',
                'integer',
                Rule::exists('product_variants', 'id')
                    ->where('product_id', $request->input('product_id'))
                    ->where('is_active', true),
            ],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:'.Cart::MAX_QUANTITY],
        ]);

        $product = Product::query()->with('variants')->findOrFail($validated['product_id']);

        if (! $product->is_active) {
            abort(404);
        }

        if ($product->hasVariants() && empty($validated['variant_id'])) {
            if ($request->wantsJson()) {
                return response()->json(['message' => __('store.select_variant_required')], 422);
            }

            return back()->withErrors(['variant_id' => __('store.select_variant_required')]);
        }

        $variant = isset($validated['variant_id'])
            ? $product->variants->firstWhere('id', $validated['variant_id'])
            : null;

        if (! ($variant?->inStock() ?? $product->inStock())) {
            if ($request->wantsJson()) {
                return response()->json(['message' => __('store.out_of_stock')], 422);
            }

            return back()->with('status', __('store.out_of_stock'));
        }

        $wanted = $validated['quantity'] ?? 1;
        $alreadyInCart = $cart->quantityOf($product, $variant);
        $maxPurchasable = $variant?->maxPurchasable() ?? $product->maxPurchasable();
        $allowed = max(0, $maxPurchasable - $alreadyInCart);

        if ($allowed < 1) {
            $message = __('store.stock_limit', ['count' => $variant?->quantity ?? $product->quantity]);

            if ($request->wantsJson()) {
                return response()->json([
                    'code' => 'stock_limit',
                    'message' => $message,
                ], 422);
            }

            return back()->with('status', $message);
        }

        $addedQuantity = min($wanted, $allowed);
        $cart->add($product, $addedQuantity, $variant);

        if ($request->wantsJson()) {
            $product->loadMissing('discount');
            $hasDiscount = ($variant === null || $variant->price_cents === null) && $product->hasDiscount();

            return response()->json([
                'product' => [
                    'name' => $product->localizedName(),
                    'image' => $variant?->imageUrl() ?? $product->thumbnailUrl(),
                    'price' => $variant ? format_euros($variant->effectivePriceCents()) : $product->formattedPrice(),
                    'original_price' => $hasDiscount ? $product->formattedOriginalPrice() : null,
                    'variant' => $variant?->label() ?: null,
                    'url' => localized_route('products.show', ['product' => $product->slug]),
                ],
                'quantity' => $addedQuantity,
                'cartCount' => $cart->quantity(),
                'cartUrl' => localized_route('cart.show'),
            ]);
        }

        return back()->with('status', __('store.added_to_cart', [
            'product' => $product->localizedName(),
        ]));
    }

    public function update(Request $request, Cart $cart, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('product_id', $product->id)],
            'quantity' => ['required', 'integer', 'min:0', 'max:'.Cart::MAX_QUANTITY],
        ]);

        $variant = isset($validated['variant_id'])
            ? ProductVariant::query()->find($validated['variant_id'])
            : null;

        $cart->update($product, $validated['quantity'], $variant);

        $status = $validated['quantity'] === 0
            ? __('store.removed_from_cart', ['product' => $product->localizedName()])
            : __('store.cart_updated');

        return back()->with('status', $status);
    }

    public function destroy(Request $request, Cart $cart, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('product_id', $product->id)],
        ]);

        $variant = isset($validated['variant_id'])
            ? ProductVariant::query()->find($validated['variant_id'])
            : null;

        $cart->remove($product, $variant);

        return back()->with('status', __('store.removed_from_cart', [
            'product' => $product->localizedName(),
        ]));
    }
}
