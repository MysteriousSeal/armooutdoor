<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Collection;

class HomepageCatalog
{
    /**
     * @return Collection<int, Product>
     */
    public static function featured(int $limit = 4): Collection
    {
        $roots = Category::query()
            ->whereNull('parent_id')
            ->with([
                'products' => fn ($query) => $query->active(),
                'children.products' => fn ($query) => $query->active(),
            ])
            ->orderBy('sort_order')
            ->get();

        return $roots
            ->map(function (Category $root): ?Product {
                $pool = $root->products
                    ->concat($root->children->flatMap(fn (Category $child) => $child->products))
                    ->unique('id')
                    ->values();

                if ($pool->isEmpty()) {
                    return null;
                }

                return $pool
                    ->sortBy([
                        fn (Product $product): int => -self::score($product),
                        ['sort_order', 'asc'],
                        ['id', 'asc'],
                    ])
                    ->first();
            })
            ->filter()
            ->take($limit)
            ->values();
    }

    /**
     * @param  Collection<int, Product>  $featured
     * @return Collection<int, Product>
     */
    public static function more(Collection $featured, int $limit = 8): Collection
    {
        return Product::query()
            ->active()
            ->with('category')
            ->whereNotIn('id', $featured->modelKeys())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    private static function score(Product $product): int
    {
        $score = 0;

        if ($product->image !== '') {
            $score += 20;
        }

        $cents = $product->price_cents;

        $score += match (true) {
            $cents >= 1500 && $cents <= 12000 => 8,
            $cents >= 800 && $cents < 1500 => 5,
            $cents > 12000 => 4,
            default => 2,
        };

        $score += min((int) floor(mb_strlen($product->localizedDescriptionText()) / 50), 8);

        return $score;
    }
}
