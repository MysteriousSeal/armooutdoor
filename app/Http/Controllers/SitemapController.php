<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Response;
use Illuminate\View\View;

class SitemapController extends Controller
{
    public function html(): View
    {
        $categories = Category::query()
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('sort_order')
            ->get();

        $products = Product::query()
            ->active()
            ->with('category')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Product $product) => $product->category?->id);

        return view('sitemap.html', compact('categories', 'products'));
    }

    public function index(): Response
    {
        $sitemaps = [
            ['url' => route('sitemap.pages')],
            ['url' => route('sitemap.categories')],
            ['url' => route('sitemap.products')],
        ];

        return $this->xml('sitemap.index', compact('sitemaps'));
    }

    public function pages(): Response
    {
        $urls = [
            ['loc' => localized_route('home', [], 'fr'), 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => route('categories.index'), 'changefreq' => 'weekly', 'priority' => '0.7'],
            ['loc' => route('faq'), 'changefreq' => 'monthly', 'priority' => '0.5'],
            ['loc' => route('help.shipping-returns'), 'changefreq' => 'monthly', 'priority' => '0.5'],
            ['loc' => route('help.secure-payment'), 'changefreq' => 'monthly', 'priority' => '0.5'],
            ['loc' => route('legal.terms'), 'changefreq' => 'yearly', 'priority' => '0.3'],
            ['loc' => route('legal.notice'), 'changefreq' => 'yearly', 'priority' => '0.3'],
            ['loc' => route('legal.privacy'), 'changefreq' => 'yearly', 'priority' => '0.3'],
            ['loc' => route('legal.withdrawal'), 'changefreq' => 'yearly', 'priority' => '0.3'],
        ];

        return $this->xml('sitemap.urlset', compact('urls'));
    }

    public function categories(): Response
    {
        $urls = Category::query()->get()->map(fn (Category $category): array => [
            'loc' => localized_route('categories.show', ['category' => $category->slug], 'fr'),
            'lastmod' => $category->updated_at?->toAtomString(),
            'changefreq' => 'weekly',
            'priority' => $category->parent_id ? '0.6' : '0.8',
        ])->all();

        return $this->xml('sitemap.urlset', compact('urls'));
    }

    public function products(): Response
    {
        $urls = Product::query()->active()->get()->map(fn (Product $product): array => [
            'loc' => localized_route('products.show', ['product' => $product->slug], 'fr'),
            'lastmod' => $product->updated_at?->toAtomString(),
            'changefreq' => 'weekly',
            'priority' => '0.7',
        ])->all();

        return $this->xml('sitemap.urlset', compact('urls'));
    }

    private function xml(string $view, array $data): Response
    {
        return response()->view($view, $data)->header('Content-Type', 'application/xml');
    }
}
