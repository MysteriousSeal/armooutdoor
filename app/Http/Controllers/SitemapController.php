<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
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

        $posts = BlogPost::query()
            ->visible()
            ->with('category')
            ->orderByDesc('published_at')
            ->get();

        $blogCategories = BlogCategory::query()->orderBy('sort_order')->get();

        return view('sitemap.html', compact('categories', 'products', 'posts', 'blogCategories'));
    }

    public function robots(): Response
    {
        // Le back-office n'est cité que sous son nom par défaut : un chemin
        // renommé pour être introuvable ne va pas s'imprimer dans le fichier
        // que tout le monde lit en premier. Un en-tête noindex couvre ses
        // pages quoi qu'il arrive.
        // /cart and /checkout are deliberately NOT disallowed: a page that
        // cannot be fetched cannot be read saying noindex, and Google keeps
        // the bare URL in its index ("indexed, though blocked"). They carry
        // an X-Robots-Tag noindex instead, which requires being crawlable.
        $lines = array_values(array_filter([
            'User-agent: *',
            config('shop.admin_path') === 'admin' ? 'Disallow: /admin' : null,
            '',
            'Sitemap: '.route('sitemap.index'),
        ], fn ($line) => $line !== null));

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain');
    }

    public function index(): Response
    {
        $sitemaps = [
            ['url' => route('sitemap.pages')],
            ['url' => route('sitemap.categories')],
            ['url' => route('sitemap.products')],
            ['url' => route('sitemap.blog')],
        ];

        return $this->xml('sitemap.index', compact('sitemaps'));
    }

    public function pages(): Response
    {
        $urls = [
            ['loc' => localized_route('home', [], 'fr'), 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => route('categories.index'), 'changefreq' => 'weekly', 'priority' => '0.7'],
            ['loc' => route('products.all'), 'changefreq' => 'daily', 'priority' => '0.7'],
            ['loc' => route('products.new-arrivals'), 'changefreq' => 'daily', 'priority' => '0.7'],
            ['loc' => route('products.promotions'), 'changefreq' => 'daily', 'priority' => '0.7'],
            ['loc' => route('products.best-sellers'), 'changefreq' => 'weekly', 'priority' => '0.7'],
            ['loc' => route('contact.show'), 'changefreq' => 'monthly', 'priority' => '0.5'],
            // The HTML plan is a crawl hub: one page linking every category,
            // product and article the XML lists.
            ['loc' => route('sitemap.html'), 'changefreq' => 'weekly', 'priority' => '0.4'],
            ['loc' => route('faq'), 'changefreq' => 'monthly', 'priority' => '0.5'],
            ['loc' => route('guides.index'), 'changefreq' => 'monthly', 'priority' => '0.5'],
            ['loc' => route('guides.cibles'), 'changefreq' => 'monthly', 'priority' => '0.5'],
            ['loc' => route('about'), 'changefreq' => 'monthly', 'priority' => '0.5'],
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

    public function blog(): Response
    {
        // Le même périmètre que la façade publique : un brouillon ou un
        // article programmé n'a rien à faire dans un plan de site.
        // La liste du blog et chaque rubrique ont leur propre adresse depuis
        // qu'elles ne sont plus un paramètre : elles s'indexent comme des
        // pages à part entière. Une rubrique vide est laissée de côté, il n'y
        // a rien à y voir.
        $categories = BlogCategory::query()
            ->withCount(['posts as posts_count' => fn ($query) => $query->visible()])
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (BlogCategory $category): bool => $category->posts_count > 0)
            ->map(fn (BlogCategory $category): array => [
                'loc' => route('blog.category', $category->slug),
                // max() rend la chaîne brute de la base, pas un Carbon : sans
                // reformatage W3C, Search Console rejette la date.
                'lastmod' => self::atom($category->posts()->visible()->max('updated_at')),
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ]);

        $posts = BlogPost::query()->visible()->get()->map(fn (BlogPost $post): array => [
            'loc' => route('blog.show', $post->slug),
            'lastmod' => $post->updated_at?->toAtomString(),
            'changefreq' => 'monthly',
            'priority' => '0.6',
        ]);

        $urls = collect([[
            'loc' => route('blog.index'),
            'lastmod' => self::atom(BlogPost::query()->visible()->max('updated_at')),
            'changefreq' => 'weekly',
            'priority' => '0.7',
        ]])->concat($categories)->concat($posts)->all();

        return $this->xml('sitemap.urlset', compact('urls'));
    }

    /** La chaîne datetime de la base, au format W3C qu'exige le protocole. */
    private static function atom(?string $datetime): ?string
    {
        return $datetime === null ? null : Carbon::parse($datetime)->toAtomString();
    }

    public function products(): Response
    {
        $urls = Product::query()->active()->with('images')->get()->map(fn (Product $product): array => [
            'loc' => localized_route('products.show', ['product' => $product->slug], 'fr'),
            'lastmod' => $product->updated_at?->toAtomString(),
            'changefreq' => 'weekly',
            'priority' => '0.7',
            // The full pictures, not the thumbnails: it is the photograph
            // itself that belongs in an image index, and a 400px crop of it
            // is not the same picture.
            'images' => collect([$product->imageUrl()])
                ->concat($product->images->map(fn ($image): string => $image->imageUrl()))
                ->filter(fn (string $url): bool => $url !== '')
                ->unique()
                ->values()
                ->all(),
        ])->all();

        return $this->xml('sitemap.urlset', compact('urls'));
    }

    private function xml(string $view, array $data): Response
    {
        return response()->view($view, $data)->header('Content-Type', 'application/xml');
    }
}
