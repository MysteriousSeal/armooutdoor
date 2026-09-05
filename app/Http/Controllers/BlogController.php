<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\View\View;

class BlogController extends Controller
{
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

        $posts = BlogPost::query()
            ->visible()
            ->with('category')
            ->withCount(['comments' => fn ($query) => $query->visible()])
            ->when($activeCategory, fn ($query) => $query->where('blog_category_id', $activeCategory->id))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('blog.index', compact('posts', 'categories', 'activeCategory'));
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

        $related = BlogPost::query()
            ->visible()
            ->with('category')
            ->where('blog_category_id', $post->blog_category_id)
            ->whereKeyNot($post->id)
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return view('blog.show', [
            'post' => $post,
            'related' => $related,
            'viewCount' => $this->viewCount($post),
        ]);
    }

    /**
     * Lifetime human views, the admin list's verdict made public: the
     * rows come thin, the bot check runs in PHP, and ten minutes of cache
     * keep a popular article from re-counting on every read.
     */
    private function viewCount(BlogPost $post): int
    {
        return (int) cache()->remember('blog-views:'.$post->id, 600, fn (): int => \App\Models\SiteVisit::query()
            ->where('path', '/blog/'.$post->slug)
            ->get(['user_agent'])
            ->reject(fn (\App\Models\SiteVisit $visit): bool => $visit->is_bot)
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

        $related = BlogPost::query()
            ->visible()
            ->with('category')
            ->where('blog_category_id', $post->blog_category_id)
            ->whereKeyNot($post->id)
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return view('blog.show', [
            'post' => $post,
            'related' => $related,
            'preview' => true,
            'viewCount' => $this->viewCount($post),
        ]);
    }
}
