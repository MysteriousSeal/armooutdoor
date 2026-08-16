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
            'products.discount',
            'children.products.discount',
        ]);

        $sort = $request->query('sort', 'name');

        if (! in_array($sort, self::SORTS, true)) {
            $sort = 'name';
        }

        $listingProducts = $category->listingProducts();
        $availableFilterValues = $this->availableFilterValues($listingProducts);
        $selectedFilters = $this->selectedFilters($request, $availableFilterValues);

        $products = $this->sortedProducts(
            $this->filteredProducts($listingProducts, $selectedFilters),
            $sort
        );

        $filterGroups = $this->facetedFilterGroups($listingProducts, $availableFilterValues, $selectedFilters);

        return view('categories.show', compact('category', 'products', 'sort', 'filterGroups', 'selectedFilters'));
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return array<string, list<string>>
     */
    private function availableFilterValues(Collection $products): array
    {
        $groups = [];

        foreach ($products as $product) {
            foreach ($product->filter_attributes ?? [] as $attribute) {
                $label = $attribute['label'] ?? '';
                $value = $attribute['value'] ?? '';

                if ($label === '' || $value === '') {
                    continue;
                }

                $groups[$label][$value] = true;
            }
        }

        return collect($groups)
            ->map(fn (array $values): array => collect(array_keys($values))->sort(SORT_NATURAL)->values()->all())
            ->all();
    }

    /**
     * @param  array<string, list<string>>  $availableFilterValues
     * @return array<string, string>
     */
    private function selectedFilters(Request $request, array $availableFilterValues): array
    {
        $requested = (array) $request->query('filter', []);
        $selected = [];

        foreach ($availableFilterValues as $label => $values) {
            $value = $requested[$label] ?? null;

            if (is_string($value) && $value !== '' && in_array($value, $values, true)) {
                $selected[$label] = $value;
            }
        }

        return $selected;
    }

    /**
     * Counts each label's values against products already narrowed by every
     * *other* selected filter, so picking one filter updates the counts shown
     * in the rest instead of freezing them at the unfiltered totals.
     *
     * @param  Collection<int, Product>  $products
     * @param  array<string, list<string>>  $availableFilterValues
     * @param  array<string, string>  $selectedFilters
     * @return array<string, list<array{value: string, count: int}>>
     */
    private function facetedFilterGroups(Collection $products, array $availableFilterValues, array $selectedFilters): array
    {
        $groups = [];

        foreach ($availableFilterValues as $label => $values) {
            $otherFilters = collect($selectedFilters)->except($label)->all();
            $scoped = $this->filteredProducts($products, $otherFilters);

            $counts = [];

            foreach ($scoped as $product) {
                foreach ($product->filter_attributes ?? [] as $attribute) {
                    if (($attribute['label'] ?? '') !== $label) {
                        continue;
                    }

                    $value = $attribute['value'] ?? '';

                    if ($value === '') {
                        continue;
                    }

                    $counts[$value] = ($counts[$value] ?? 0) + 1;
                }
            }

            $groups[$label] = collect($values)
                ->filter(fn (string $value): bool => ($counts[$value] ?? 0) > 0 || ($selectedFilters[$label] ?? null) === $value)
                ->map(fn (string $value): array => ['value' => $value, 'count' => $counts[$value] ?? 0])
                ->values()
                ->all();
        }

        return $groups;
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
