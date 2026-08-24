<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(Request $request): View
    {
        $categories = BlogCategory::query()
            ->orderBy('sort_order')
            ->withCount(['posts as posts_count' => fn ($query) => $query->visible()])
            ->get();

        // Une rubrique inconnue ne vaut pas une erreur : on retombe sur « tous
        // les articles » plutôt que sur un 404 pour une faute de frappe.
        $activeCategory = $request->filled('categorie')
            ? $categories->firstWhere('slug', $request->query('categorie'))
            : null;

        $posts = BlogPost::query()
            ->visible()
            ->with('category')
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

        return view('blog.show', compact('post', 'related'));
    }
}
