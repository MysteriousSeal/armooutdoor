<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class HomepageCatalog
{
    /**
     * Renvoie toujours une collection Eloquent : les appelants y chargent des
     * relations et en tirent des clés. Une catégorie racine sans produit fait
     * renvoyer null au map() ci-dessous, ce qui suffirait à dégrader le
     * résultat en collection de base — et la page d'accueil tombait alors en
     * 500 dès qu'une catégorie vide existait.
     *
     * @return EloquentCollection<int, Product>
     */
    /**
     * The products actually on offer today.
     *
     * `whereHas('discount')` finds the ones that carry a discount row; whether
     * it is running is decided in PHP, since a discount has a window and a row
     * outside its dates is not an offer. The same pair of steps the promotions
     * page takes, so the strip on the home page and the page it links to can
     * never disagree about what is on sale.
     *
     * @return Collection<int, Product>
     */
    public static function onSale(int $limit = 10): Collection
    {
        return Product::query()
            ->active()
            ->whereHas('discount')
            ->with('category', 'discount', 'variants.supplier')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (Product $product): bool => $product->hasDiscount())
            ->take($limit)
            ->values();
    }

    public static function featured(int $limit = 4): EloquentCollection
    {
        $roots = Category::query()
            ->whereNull('parent_id')
            ->with([
                'products' => fn ($query) => $query->active(),
                'products.variants.supplier',
                'children.products' => fn ($query) => $query->active(),
                'children.products.variants.supplier',
            ])
            ->orderBy('sort_order')
            ->get();

        $best = $roots
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

        return Product::query()->getModel()->newCollection($best->all());
    }

    /**
     * @param  Collection<int, Product>  $featured
     * @return Collection<int, Product>
     */
    public static function more(Collection $featured, int $limit = 8): Collection
    {
        return Product::query()
            ->active()
            ->with('category', 'variants.supplier')
            // pluck() plutôt que modelKeys() : une catégorie racine sans
            // produit fait renvoyer null au map() de featured(), ce qui
            // dégrade la collection Eloquent en collection de base — et
            // modelKeys() n'existe que sur la première. La page d'accueil
            // tombait alors en 500 dès qu'une catégorie vide existait.
            ->whereNotIn('id', $featured->pluck('id')->all())
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
