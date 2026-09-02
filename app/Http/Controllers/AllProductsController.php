<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\ProductRelevance;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

/**
 * The whole catalogue on one paginated page — the destination the home
 * page's « Voir tous les produits » promised and never had, ordered by
 * the same business relevance the category pages use.
 */
class AllProductsController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): View
    {
        $products = ProductRelevance::sort(
            Product::query()
                ->active()
                ->with('category', 'discount', 'variants.supplier')
                ->get(),
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

        return view('products.all', ['products' => $paginated]);
    }
}
