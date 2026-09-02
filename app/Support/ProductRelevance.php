<?php

namespace App\Support;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SiteVisit;
use Illuminate\Support\Collection;

/**
 * What a browsing customer should see first — there is no search query to
 * be relevant to, so relevance is business sense: what can be bought
 * leads, then what actually sells, then what gets looked at, then the
 * hand-ranking as the stable tiebreak. Two grouped queries for the whole
 * listing, whatever its size. Shared by the category pages and the
 * all-products page.
 */
class ProductRelevance
{
    /**
     * @param  Collection<int, Product>  $products
     * @return Collection<int, Product>
     */
    public static function sort(Collection $products): Collection
    {
        $ids = $products->pluck('id');

        // Units sold in the last year, only from orders that count as
        // revenue — a refunded or draft sale recommends nothing.
        $sales = OrderItem::query()
            ->whereIn('product_id', $ids)
            ->whereHas('order', fn ($query) => $query
                ->whereNotIn('status', ['refunded', 'draft'])
                ->whereNull('test_marked_at')
                ->where('created_at', '>=', now()->subYear()))
            ->selectRaw('product_id, sum(quantity) as aggregate')
            ->groupBy('product_id')
            ->pluck('aggregate', 'product_id');

        // Page views recorded by the site's own analytics. Bots are counted
        // too — telling them apart is a PHP verdict, and they crawl the
        // catalogue evenly enough not to reorder it.
        $views = SiteVisit::query()
            ->whereIn('product_id', $ids)
            ->selectRaw('product_id, count(*) as aggregate')
            ->groupBy('product_id')
            ->pluck('aggregate', 'product_id');

        $availabilityRank = [
            'in_stock' => 0,
            'low_stock' => 1,
            'restocking' => 2,
            'at_supplier' => 3,
            'out_of_stock' => 4,
        ];

        return $products
            ->sortBy(fn (Product $product): array => [
                $availabilityRank[$product->availabilityState()] ?? 5,
                -(int) ($sales[$product->id] ?? 0),
                -(int) ($views[$product->id] ?? 0),
                $product->sort_order,
                $product->id,
            ])
            ->values();
    }
}
