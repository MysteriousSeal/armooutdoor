<?php

namespace App\Support\Octopia;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

/**
 * How far a product is from being sendable to Cdiscount.
 *
 * Three things, in the order they block: a category template to be described
 * by, an EAN on every line Octopia would receive, and the answers that
 * category asks for. The product page shows them as a row of marks, so the
 * gap is seen from the sheet rather than from a refused import.
 */
class Readiness
{
    /** @return array<string, bool> */
    public static function checks(Product $product): array
    {
        $listing = $product->cdiscountListing;

        if ($listing?->template === null) {
            return ['Category' => false, 'EAN' => false, 'Fields' => false];
        }

        return [
            'Category' => true,
            'EAN' => self::hasCodes($product),
            'Fields' => self::hasAnswers($product),
        ];
    }

    public static function isReady(Product $product): bool
    {
        return ! in_array(false, self::checks($product), true);
    }

    /** Every line Octopia would receive carries its own EAN. */
    private static function hasCodes(Product $product): bool
    {
        $variants = self::activeVariants($product);

        if ($variants->isEmpty()) {
            return filled($product->gtin);
        }

        return $variants->every(fn (ProductVariant $variant): bool => filled($variant->gtin));
    }

    /**
     * The category's required attributes are answered: on the product, or on
     * each variant for the ones answered variant by variant.
     */
    private static function hasAnswers(Product $product): bool
    {
        $listing = $product->cdiscountListing;
        $values = $listing?->answers() ?? [];
        $perVariant = $listing?->perVariantCodes() ?? [];
        $variants = self::activeVariants($product);

        foreach ($listing?->template?->requiredAttributeFields() ?? [] as $field) {
            $code = $field['code'];

            if (! in_array($code, $perVariant, true)) {
                if (blank($values[$code] ?? null)) {
                    return false;
                }

                continue;
            }

            // Answered per variant: a variant saying nothing falls back to
            // the product's own answer, so one of the two must be there.
            foreach ($variants as $variant) {
                if (blank($listing->answersFor($variant)[$code] ?? null)) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @return Collection<int, ProductVariant> */
    private static function activeVariants(Product $product)
    {
        return $product->variants->where('is_active', true);
    }
}
