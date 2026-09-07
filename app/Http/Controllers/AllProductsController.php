<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Support\ProductSort;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The whole catalogue on one paginated page — the destination the home
 * page's « Voir tous les produits » promised and never had, ordered by
 * the same rules the category pages offer - relevance by default.
 */
class AllProductsController extends Controller
{
    private const PER_PAGE = 24;

    public function index(Request $request): View|RedirectResponse
    {
        if (($url = ProductSort::redirectUrl($request)) !== null) {
            return redirect($url, 301);
        }

        $sort = ProductSort::resolve($request->query('sort'));

        $products = ProductSort::apply(
            Product::query()
                ->active()
                ->with('category', 'discount', 'variants.supplier')
                ->get(),
            $sort,
        );

        $total = $products->count();
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = max(1, min((int) $request->query('page', 1), $lastPage));

        $paginated = new LengthAwarePaginator(
            $products->forPage($page, self::PER_PAGE)->values(),
            $total,
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->except('page')],
        );

        return view('products.all', [
            'products' => $paginated,
            'sort' => $sort,
            'categories' => $this->rails(),
        ]);
    }

    /**
     * The rayons, linked from the one page that touches every product.
     *
     * Fourteen pages of catalogue used to lead nowhere but to themselves and
     * to the product pages: nothing pointed at the categories, which are the
     * pages a search engine has the best reason to rank. A row of them here
     * gives the crawler somewhere to go and the visitor a way out of the pile.
     *
     * @return Collection<int, Category>
     */
    private function rails(): Collection
    {
        return Category::query()
            ->whereNull('parent_id')
            ->with([
                'products' => fn ($query) => $query->active(),
                'children.products' => fn ($query) => $query->active(),
            ])
            ->withCount(['products' => fn ($query) => $query->active()])
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (Category $category): bool => $category->listingProducts()->isNotEmpty())
            ->values();
    }
}
