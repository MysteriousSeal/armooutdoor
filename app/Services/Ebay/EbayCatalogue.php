<?php

namespace App\Services\Ebay;

use App\Models\Product;
use App\Models\ProductVariant;

/** Our product and stock behind each eBay SKU. */
class EbayCatalogue
{
    /**
     * Two queries for any number of SKUs. A product's own SKU wins over a
     * variant that happens to share it.
     *
     * @param  list<string>  $skus
     * @return array<string, array{product: Product, quantity: int}>
     */
    public function forSkus(array $skus): array
    {
        if ($skus === []) {
            return [];
        }

        $found = [];

        foreach (Product::query()->whereIn('sku', $skus)->get() as $product) {
            $found[$product->sku] = ['product' => $product, 'quantity' => max(0, (int) $product->quantity)];
        }

        foreach (ProductVariant::query()->with('product')->whereIn('sku', $skus)->get() as $variant) {
            if ($variant->product !== null) {
                $found[$variant->sku] ??= ['product' => $variant->product, 'quantity' => max(0, (int) $variant->quantity)];
            }
        }

        return $found;
    }
}
