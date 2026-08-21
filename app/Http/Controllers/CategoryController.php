<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CategoryController extends Controller
{
    /** Produits par page sur une fiche catégorie. */
    private const PER_PAGE = 20;

    public const SORTS = ['relevance', 'name', 'price-asc', 'price-desc', 'newest'];

    public function index(): View
    {
        $categories = Category::query()
            ->whereNull('parent_id')
            ->with([
                'products' => fn ($query) => $query->active(),
                'products.variants.supplier',
                'children' => fn ($query) => $query->orderBy('sort_order'),
                'children.products' => fn ($query) => $query->active(),
                'children.products.variants.supplier',
            ])
            ->orderBy('sort_order')
            ->get();

        return view('categories.index', compact('categories'));
    }

    public function show(Request $request, string $category): View|RedirectResponse
    {
        $slug = $category;
        $category = Category::where('slug', $slug)->first();

        if ($category === null) {
            $newSlug = config('category_slug_redirects.'.$slug);

            if ($newSlug !== null) {
                return redirect(localized_route('categories.show', ['category' => $newSlug]), 301);
            }

            abort(404);
        }

        // Other code (e.g. the nav) reads request()->route('category')
        // expecting a Category model, matching the old implicit binding.
        $request->route()->setParameter('category', $category);

        $category->load([
            'parent.children.products' => fn ($query) => $query->active(),
            'children.products' => fn ($query) => $query->active(),
            'products' => fn ($query) => $query->active(),
            'products.category',
            'products.discount',
            'products.variants.supplier',
            'children.products.discount',
            'children.products.variants.supplier',
        ]);

        $sort = $request->query('sort', 'relevance');

        if (! in_array($sort, self::SORTS, true)) {
            $sort = 'relevance';
        }

        $listingProducts = $category->listingProducts();
        $availableFilterValues = $this->availableFilterValues($listingProducts);
        $selectedFilters = $this->selectedFilters($request, $availableFilterValues);

        $products = $this->paginate(
            $this->sortedProducts(
                $this->filteredProducts($listingProducts, $selectedFilters),
                $sort
            ),
            $request
        );

        $filterGroups = $this->facetedFilterGroups($listingProducts, $availableFilterValues, $selectedFilters);

        return view('categories.show', compact('category', 'products', 'sort', 'filterGroups', 'selectedFilters'));
    }

    /**
     * Les filtres et le tri s'appliquent en PHP sur la collection entière, pas
     * en base : la pagination se découpe donc après coup, sur le résultat.
     *
     * La page demandée est ramenée dans les bornes existantes. Les formulaires
     * de tri et de filtres ne transportent pas de page, donc les changer
     * revient déjà à la première — mais une URL collée à la main, elle, peut
     * pointer n'importe où.
     *
     * @param  Collection<int, Product>  $products
     * @return LengthAwarePaginator<int, Product>
     */
    private function paginate(Collection $products, Request $request): LengthAwarePaginator
    {
        $total = $products->count();
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = max(1, min((int) $request->query('page', 1), $lastPage));

        return new LengthAwarePaginator(
            $products->forPage($page, self::PER_PAGE)->values(),
            $total,
            self::PER_PAGE,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->except('page'),
            ]
        );
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
            ->map(fn (array $values): array => collect(array_keys($values))
                ->map(fn ($value): string => (string) $value)
                ->sort(SORT_NATURAL)
                ->values()
                ->all())
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
            'name' => $products
                ->sortBy(fn (Product $product): string => mb_strtolower($product->localizedName()), SORT_NATURAL)
                ->values(),
            'price-asc' => $products->sortBy('price_cents')->values(),
            'price-desc' => $products->sortByDesc('price_cents')->values(),
            // "Pertinence" has no scoring yet — same ordering as "Nouveautés" for now.
            'newest', 'relevance' => $products->sortByDesc('created_at')->values(),
            default => $products->values(),
        };
    }
}
