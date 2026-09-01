<?php

namespace App\Http\Controllers;

use App\Models\Carrier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingSetting;
use App\Support\Cart;
use App\Support\CartLine;
use App\Support\ShippingEstimate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CartController extends Controller
{
    public function show(Cart $cart): View
    {
        [$freeShippingUnlocked, $cheapestShippingCents] = $this->shippingEstimate($cart);
        $lines = $cart->lines();

        return view('cart.show', [
            'lines' => $lines,
            // Whether the basket holds anything reserved to adults, and where
            // the customer stands on proving they are one. It never blocks
            // the order: it says what will be asked before it is asked.
            'ageRestrictedLines' => $lines->filter(fn ($line): bool => (bool) $line->product->age_restricted)->values(),
            'identityStatus' => auth()->user()?->identityStatus(),
            'total' => $cart->formattedTotal(),
            'itemCount' => $cart->quantity(),
            'freeShippingUnlocked' => $freeShippingUnlocked,
            'cheapestShippingCents' => $cheapestShippingCents,
            'estimatedShippingDate' => $this->estimatedShippingDate($lines),
        ]);
    }

    /**
     * "If you ordered right now" — same 10am/weekend rules as a placed
     * order, using the current moment as the reference instead of a
     * fixed order date, plus each backordered line's own supplier lead
     * time. Null when the cart is empty.
     */
    private function estimatedShippingDate(Collection $lines): ?Carbon
    {
        if ($lines->isEmpty()) {
            return null;
        }

        $now = now();
        $candidates = collect([ShippingEstimate::standard($now)]);

        foreach ($lines as $line) {
            $source = $line->variant ?? $line->product;

            if (! $source->inStock() && $source->isBackorderable() && $source->supplier?->lead_time_days !== null) {
                $candidates->push(ShippingEstimate::backordered($now, $source->supplier->lead_time_days));
            }
        }

        return $candidates->max();
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

        $source = $variant ?? $product;

        if (! ($source->inStock() || $source->isBackorderable())) {
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

    public function update(Request $request, Cart $cart, Product $product): RedirectResponse|JsonResponse
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

        if ($request->wantsJson()) {
            $lines = $cart->lines();
            $line = $lines->first(
                fn (CartLine $l): bool => $l->product->id === $product->id && $l->variant?->id === $variant?->id
            );

            [$freeShippingUnlocked, $cheapestShippingCents] = $this->shippingEstimate($cart);
            $shippingIsFree = $freeShippingUnlocked || $cheapestShippingCents === 0;
            $shippingVisible = $shippingIsFree || $cheapestShippingCents !== null;
            $estimatedShippingDate = $this->estimatedShippingDate($lines);

            return response()->json([
                'removed' => $line === null,
                'quantity' => $line?->quantity,
                'unitPrice' => $line?->formattedUnitPrice(),
                'lineTotal' => $line?->formattedLineTotal(),
                'itemCount' => $cart->quantity(),
                'itemCountLabel' => $cart->quantity() > 0
                    ? trans_choice('store.cart_count', $cart->quantity(), ['count' => $cart->quantity()])
                    : null,
                'isEmpty' => $cart->isEmpty(),
                'subtotal' => $cart->formattedTotal(),
                'shippingVisible' => $shippingVisible,
                'shippingIsFree' => $shippingIsFree,
                'shippingValueText' => $shippingIsFree
                    ? __('store.shipping_free')
                    : ($cheapestShippingCents !== null ? __('store.shipping_from_amount', ['price' => format_euros($cheapestShippingCents)]) : null),
                'estimatedShippingDate' => $estimatedShippingDate?->toDateString(),
                'estimatedShippingDateText' => $estimatedShippingDate?->translatedFormat('d F Y'),
                'message' => $status,
            ]);
        }

        return back()->with('status', $status);
    }

    public function destroy(Request $request, Cart $cart, Product $product): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('product_id', $product->id)],
        ]);

        $variant = isset($validated['variant_id'])
            ? ProductVariant::query()->find($validated['variant_id'])
            : null;

        $cart->remove($product, $variant);

        $message = __('store.removed_from_cart', ['product' => $product->localizedName()]);

        if ($request->wantsJson()) {
            [$freeShippingUnlocked, $cheapestShippingCents] = $this->shippingEstimate($cart);
            $shippingIsFree = $freeShippingUnlocked || $cheapestShippingCents === 0;
            $shippingVisible = $shippingIsFree || $cheapestShippingCents !== null;
            $estimatedShippingDate = $this->estimatedShippingDate($cart->lines());

            return response()->json([
                'removed' => true,
                'itemCount' => $cart->quantity(),
                'itemCountLabel' => $cart->quantity() > 0
                    ? trans_choice('store.cart_count', $cart->quantity(), ['count' => $cart->quantity()])
                    : null,
                'isEmpty' => $cart->isEmpty(),
                'subtotal' => $cart->formattedTotal(),
                'shippingVisible' => $shippingVisible,
                'shippingIsFree' => $shippingIsFree,
                'shippingValueText' => $shippingIsFree
                    ? __('store.shipping_free')
                    : ($cheapestShippingCents !== null ? __('store.shipping_from_amount', ['price' => format_euros($cheapestShippingCents)]) : null),
                'estimatedShippingDate' => $estimatedShippingDate?->toDateString(),
                'estimatedShippingDateText' => $estimatedShippingDate?->translatedFormat('d F Y'),
                'message' => $message,
            ]);
        }

        return back()->with('status', $message);
    }

    /**
     * @return array{0: bool, 1: ?int} [freeShippingUnlocked, cheapestShippingCents]
     */
    private function shippingEstimate(Cart $cart): array
    {
        $shippingSetting = ShippingSetting::current();
        $subtotalCents = $cart->totalCents();
        $weightGrams = $cart->totalWeightGrams();

        $cheapestShippingCents = Carrier::query()->active()->get()
            ->filter(fn (Carrier $carrier): bool => $cart->allowsCarrier($carrier))
            // Un transporteur que le poids disqualifie ne peut pas fournir
            // le « à partir de » : son prix serait celui d'un choix grisé.
            ->filter(fn (Carrier $carrier): bool => $carrier->carriesWeight($weightGrams))
            ->map(fn (Carrier $carrier): int => $shippingSetting->effectivePriceCents($carrier, $subtotalCents, $weightGrams))
            ->min();

        return [$shippingSetting->isUnlockedBy($subtotalCents), $cheapestShippingCents];
    }
}
