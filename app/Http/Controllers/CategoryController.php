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

        $listingProducts = $category->listingProducts();
        $filterGroups = $this->filterGroups($listingProducts);
        $selectedFilters = $this->selectedFilters($request, $filterGroups);

        $products = $this->sortedProducts(
            $this->filteredProducts($listingProducts, $selectedFilters),
            $sort
        );

        return view('categories.show', compact('category', 'products', 'sort', 'filterGroups', 'selectedFilters'));
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return array<string, list<array{value: string, count: int}>>
     */
    private function filterGroups(Collection $products): array
    {
        $groups = [];

        foreach ($products as $product) {
            foreach ($product->filter_attributes ?? [] as $attribute) {
                $label = $attribute['label'] ?? '';
                $value = $attribute['value'] ?? '';

                if ($label === '' || $value === '') {
                    continue;
                }

                $groups[$label][$value] = ($groups[$label][$value] ?? 0) + 1;
            }
        }

        return collect($groups)
            ->map(fn (array $values): array => collect($values)
                ->sortKeys(SORT_NATURAL)
                ->map(fn (int $count, string $value): array => ['value' => $value, 'count' => $count])
                ->values()
                ->all())
            ->all();
    }

    /**
     * @param  array<string, list<array{value: string, count: int}>>  $filterGroups
     * @return array<string, string>
     */
    private function selectedFilters(Request $request, array $filterGroups): array
    {
        $requested = (array) $request->query('filter', []);
        $selected = [];

        foreach ($filterGroups as $label => $values) {
            $value = $requested[$label] ?? null;
            $knownValues = array_column($values, 'value');

            if (is_string($value) && $value !== '' && in_array($value, $knownValues, true)) {
                $selected[$label] = $value;
            }
        }

        return $selected;
    }

    /**
     * @param  Collection<int, Product>  $products
     * @param  array<string, string>  $selectedFilters
     * @return Collection<int, Product>
     */
    private function filteredProducts(Collection $products, array $selectedFilters): Collection
    {
        if ($selectedFilters === []) {
            return $products;
        }

        return $products->filter(function (Product $product) use ($selectedFilters): bool {
            $values = collect($product->filter_attributes ?? [])->pluck('value', 'label');

            foreach ($selectedFilters as $label => $value) {
                if (($values[$label] ?? null) !== $value) {
                    return false;
                }
            }

            return true;
        })->values();
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
