<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * How a listing orders itself.
 *
 * The category pages had this to themselves, privately, which is why the
 * catalogue page had no sort at all: the rules lived where only one page
 * could reach them. Named once here, both read the same list and neither
 * can drift from the other.
 */
class ProductSort
{
    /** @var list<string> */
    public const OPTIONS = ['relevance', 'name', 'price-asc', 'price-desc', 'newest'];

    public const DEFAULT = 'relevance';

    /** The asked-for order, or the default when it names nothing real. */
    public static function resolve(mixed $requested): string
    {
        return in_array($requested, self::OPTIONS, true) ? (string) $requested : self::DEFAULT;
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return Collection<int, Product>
     */
    public static function apply(Collection $products, string $sort): Collection
    {
        return match ($sort) {
            // Accents and case are the shopper's problem, not the sorter's:
            // « Étui » belongs beside « Etiquette », not after « Zip ».
            'name' => $products
                ->sortBy(fn (Product $product): string => mb_strtolower($product->localizedName()), SORT_NATURAL)
                ->values(),
            'price-asc' => $products->sortBy('price_cents')->values(),
            'price-desc' => $products->sortByDesc('price_cents')->values(),
            'newest' => $products->sortByDesc('created_at')->values(),
            'relevance' => ProductRelevance::sort($products),
            default => $products->values(),
        };
    }
}
