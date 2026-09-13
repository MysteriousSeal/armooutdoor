<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\SiteVisit;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class BlogController extends Controller
{
    /** How many articles the "à lire aussi" block holds. */
    private const RELATED_COUNT = 3;

    public function index(): View
    {
        return $this->listing(null);
    }

    /**
     * Une rubrique a sa propre adresse, `/blog/conseils`.
     *
     * La route ne répond que pour un slug de rubrique existant : un slug
     * inconnu n'arrive jamais ici, il tombe sur la route article et donne un
     * 404 franc plutôt qu'une liste complète déguisée en rubrique.
     */
    public function category(string $category): View
    {
        $active = BlogCategory::query()->where('slug', $category)->firstOrFail();

        return $this->listing($active);
    }

    private function listing(?BlogCategory $activeCategory): View
    {
        $categories = BlogCategory::query()
            ->orderBy('sort_order')
            ->withCount(['posts as posts_count' => fn ($query) => $query->visible()])
            ->get();

        if ($activeCategory !== null) {
            // La version comptée, pour que le libellé du bandeau ait son total.
            $activeCategory = $categories->firstWhere('id', $activeCategory->id) ?? $activeCategory;
        }

        $query = BlogPost::query()
            ->visible()
            ->with('category')
            ->withCount(['comments' => fn ($query) => $query->visible()])
            ->when($activeCategory, fn ($query) => $query->where('blog_category_id', $activeCategory->id))
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        // Page one shows the newest post above its twelve; every page leaves
        // it out of the paginated rows, so page two starts at the fourteenth
        // post instead of repeating or skipping one at the seam.
        $newest = (clone $query)->first();

        $posts = $query
            ->when($newest, fn ($query) => $query->whereKeyNot($newest->id))
            ->paginate(12)
            ->withQueryString();

        return view('blog.index', [
            'posts' => $posts,
            'featuredPost' => $posts->onFirstPage() ? $newest : null,
            'lead' => $newest ? 1 : 0,
            'categories' => $categories,
            'activeCategory' => $activeCategory,
        ]);
    }

    public function show(string $slug): View
    {
        // La visibilité passe par le périmètre, jamais par le seul slug :
        // sinon un brouillon reste lisible pour qui connaît son adresse.
        $post = BlogPost::query()
            ->visible()
            ->with(['category', 'products' => fn ($query) => $query->active()->with('discount', 'variants.supplier')])
            ->with(['comments' => fn ($query) => $query->visible()->whereNull('parent_id')
                ->with(['replies' => fn ($replies) => $replies->visible()->with('user'), 'user'])
                ->orderBy('created_at')])
            ->where('slug', $slug)
            ->firstOrFail();

        $related = $this->relatedPosts($post);

        return view('blog.show', [
            'post' => $post,
            'related' => $related,
            'viewCount' => $this->viewCount($post),
        ]);
    }

    /**
     * Three articles to read next, the nearest first.
     *
     * The rubric comes first: an article in the same rubric is the closest
     * thing to the one being read. A thin rubric cannot fill three on its
     * own, though, and « essais » holds a single article, which left that
     * page with no block at all and no way onward. Whatever the rubric
     * cannot supply is topped up from the rest of the blog, newest first,
     * so every article ends on somewhere to go.
     *
     * @return Collection<int, BlogPost>
     */
    private function relatedPosts(BlogPost $post): Collection
    {
        $related = BlogPost::query()
            ->visible()
            ->with('category')
            ->where('blog_category_id', $post->blog_category_id)
            ->whereKeyNot($post->id)
            ->orderByDesc('published_at')
            ->limit(self::RELATED_COUNT)
            ->get();

        if ($related->count() >= self::RELATED_COUNT) {
            return $related;
        }

        // Excluded by key rather than by rubric: a post filed under no
        // rubric at all would slip past a `!=` comparison on the column.
        return $related->concat(
            BlogPost::query()
                ->visible()
                ->with('category')
                ->whereKeyNot($post->id)
                ->whereNotIn('id', $related->modelKeys())
                ->orderByDesc('published_at')
                ->limit(self::RELATED_COUNT - $related->count())
                ->get()
        );
    }

    /**
     * Lifetime human views, the admin list's verdict made public: the
     * rows come thin, the bot check runs in PHP, and ten minutes of cache
     * keep a popular article from re-counting on every read.
     */
    private function viewCount(BlogPost $post): int
    {
        return (int) cache()->remember('blog-views:'.$post->id, 600, fn (): int => SiteVisit::query()
            ->where('path', '/blog/'.$post->slug)
            ->get(['user_agent'])
            ->reject(fn (SiteVisit $visit): bool => $visit->is_bot)
            ->count());
    }

    /**
     * A draft, seen as the page it will become.
     *
     * The address is unreferenced and admin-only: whoever opens it must be
     * signed into the back office, and the page wears a banner and a
     * noindex so it can neither be mistaken for the published article nor
     * picked up by a crawler.
     */
    public function preview(BlogPost $post): View
    {
        $post->load(['category', 'products' => fn ($query) => $query->active()->with('discount', 'variants.supplier')]);

        $related = $this->relatedPosts($post);

        return view('blog.show', [
            'post' => $post,
            'related' => $related,
            'preview' => true,
            'viewCount' => $this->viewCount($post),
        ]);
    }
}
