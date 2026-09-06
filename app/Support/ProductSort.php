<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Http\Request;
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
     * Where a listing should send a visitor who asked for the order it was
     * already in.
     *
     * The selector is a plain form, so choosing « Pertinence » submits it like
     * any other value and lands on a second address holding the first one's
     * page. Rather than let both be crawled and rely on the canonical to sort
     * them out afterwards, the default order goes back to the bare listing and
     * keeps whatever else the URL was carrying.
     *
     * @return string|null null when the address is already the right one
     */
    public static function redirectUrl(Request $request): ?string
    {
        if ($request->query('sort') !== self::DEFAULT) {
            return null;
        }

        $query = $request->except('sort');

        return $request->url().($query === [] ? '' : '?'.http_build_query($query));
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
