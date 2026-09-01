<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

/**
 * The shop's baskets and orders, in the shape Google Analytics reads them.
 *
 * GA4 has its own vocabulary for shopping — add_to_cart, begin_checkout,
 * purchase — and its own idea of what an item is. Anything else it records as
 * a plain count and leaves out of the reports that make the tool worth having:
 * no revenue, no funnel, no product performance.
 *
 * PostHog has no such opinions, so the shop keeps its own event names there
 * and the translation happens on the way out. This is the half of it that
 * cannot be done in the browser: prices and names live on the server.
 */
class AnalyticsItems
{
    /**
     * One product, as GA4 wants a product.
     *
     * @return array<string, mixed>
     */
    public static function forProduct(Product $product, int $quantity = 1, ?ProductVariant $variant = null): array
    {
        return array_filter([
            'item_id' => $product->sku ?: (string) $product->id,
            'item_name' => $product->localizedName(),
            'item_category' => $product->category?->localizedName(),
            'item_brand' => $product->brandName(),
            'item_variant' => $variant?->label(),
            'price' => self::amount($variant?->effectivePriceCents() ?? $product->effectivePriceCents()),
            'quantity' => $quantity,
        ], fn ($value): bool => $value !== null && $value !== '');
    }

    /**
     * A basket on its way to the checkout.
     *
     * @param  Collection<int, CartLine>  $lines
     * @return list<array<string, mixed>>
     */
    public static function forCart(Collection $lines): array
    {
        return $lines
            ->map(fn (CartLine $line): array => self::forProduct($line->product, $line->quantity, $line->variant))
            ->values()
            ->all();
    }

    /**
     * A completed order.
     *
     * Read from the order's own rows rather than from the products they point
     * at: a product renamed or repriced after the sale must not change what
     * the sale is reported to have been.
     *
     * @return list<array<string, mixed>>
     */
    public static function forOrder(Order $order): array
    {
        return $order->items
            ->map(fn ($item): array => array_filter([
                'item_id' => $item->sku ?: (string) $item->product_id,
                'item_name' => $item->name,
                'item_variant' => $item->variant_label,
                'price' => self::amount((int) $item->unit_price_cents),
                'quantity' => (int) $item->quantity,
            ], fn ($value): bool => $value !== null && $value !== ''))
            ->values()
            ->all();
    }

    private static function amount(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
