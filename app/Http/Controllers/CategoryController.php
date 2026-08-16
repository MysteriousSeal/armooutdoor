<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public const SORTS = ['name', 'price-asc', 'price-desc'];

    public function show(Request $request, Category $category): View
    {
        $category->load([
            'parent.children.products' => fn ($query) => $query->active(),
            'children.products' => fn ($query) => $query->active(),
            'products' => fn ($query) => $query->active(),
            'products.category',
        ]);

        $sort = $request->query('sort', 'name');

        if (! in_array($sort, self::SORTS, true)) {
            $sort = 'name';
        }

        $products = $this->sortedProducts($category->listingProducts(), $sort);

        return view('categories.show', compact('category', 'products', 'sort'));
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return Collection<int, Product>
     */
    private function sortedProducts(Collection $products, string $sort): Collection
    {
        return match ($sort) {
            'price-asc' => $products->sortBy('price_cents')->values(),
            'price-desc' => $products->sortByDesc('price_cents')->values(),
            default => $products
                ->sortBy(fn (Product $product): string => mb_strtolower($product->localizedName()), SORT_NATURAL)
                ->values(),
        };
    }
}
