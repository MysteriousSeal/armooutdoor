<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\BlogPostPayloadRequest;
use App\Http\Requests\Api\Admin\StoreBlogPostRequest;
use App\Http\Requests\Api\Admin\UpdateBlogPostRequest;
use App\Http\Resources\Api\Admin\BlogCategoryResource;
use App\Http\Resources\Api\Admin\BlogPostResource;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class BlogPostController extends Controller
{
    private const MAX_PER_PAGE = 100;

    public function categories(): AnonymousResourceCollection
    {
        return BlogCategoryResource::collection(
            BlogCategory::query()
                ->withCount(['posts as posts_count' => fn (Builder $query) => $query->visible()])
                ->orderBy('sort_order')
                ->get(),
        );
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $posts = BlogPost::query()
            ->with('category', 'products')
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $term = trim((string) $request->query('search'));

                $query->where(fn (Builder $inner) => $inner
                    ->where('title', 'like', '%'.$term.'%')
                    ->orWhere('slug', 'like', '%'.$term.'%'));
            })
            ->when($request->filled('slug'), fn (Builder $q) => $q->where('slug', $request->query('slug')))
            ->when($request->filled('category_id'), fn (Builder $q) => $q->where('blog_category_id', $request->integer('category_id')))
            ->when($request->filled('category'), function (Builder $query) use ($request): void {
                $query->whereHas('category', fn (Builder $inner) => $inner->where('slug', $request->query('category')));
            })
            ->when($request->filled('status'), fn (Builder $q) => $this->applyStatusFilter($q, (string) $request->query('status')))
            ->when($request->filled('updated_since'), fn (Builder $q) => $q->where('updated_at', '>=', $request->date('updated_since')))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return BlogPostResource::collection($posts);
    }

    public function show(BlogPost $post): JsonResponse
    {
        return response()->json([
            'data' => new BlogPostResource($post->load('category', 'products')),
        ]);
    }

    public function store(StoreBlogPostRequest $request): JsonResponse
    {
        $post = BlogPost::query()->create($this->payload($request, null));
        $this->syncProducts($request, $post);

        return response()->json([
            'data' => new BlogPostResource($post->load('category', 'products')),
        ], 201);
    }

    public function update(UpdateBlogPostRequest $request, BlogPost $post): JsonResponse
    {
        $post->update($this->payload($request, $post));
        $this->syncProducts($request, $post);

        return response()->json([
            'data' => new BlogPostResource($post->fresh()->load('category', 'products')),
        ]);
    }

    public function destroy(BlogPost $post): JsonResponse
    {
        // L'image de couverture appartient à l'article ; les images citées
        // dans le corps du texte restent sur le disque, comme côté back-office.
        $post->delete();

        return response()->json([], 204);
    }

    /**
     * `status` accepte quatre valeurs, dont deux ne sont pas des colonnes.
     *
     * « visible » et « scheduled » sont des états, pas des statuts stockés :
     * les exposer évite au client de réimplémenter la règle de visibilité,
     * qui est exactement ce qu'on cherche à garder en un seul endroit.
     */
    private function applyStatusFilter(Builder $query, string $status): void
    {
        match ($status) {
            'draft' => $query->where('status', 'draft'),
            'published' => $query->where('status', 'published'),
            'visible' => $query->visible(),
            'scheduled' => $query->where('status', 'published')
                ->where(fn (Builder $inner) => $inner
                    ->whereNull('published_at')
                    ->orWhere('published_at', '>', now())),
            default => $query->whereRaw('1 = 1'),
        };
    }

    private function perPage(Request $request): int
    {
        $requested = $request->filled('per_page') ? $request->integer('per_page') : 25;

        return max(1, min($requested, self::MAX_PER_PAGE));
    }

    /** @return array<string, mixed> */
    private function payload(BlogPostPayloadRequest $request, ?BlogPost $post): array
    {
        $validated = $request->validated();
        $payload = [];

        foreach (['blog_category_id', 'status', 'published_at', 'meta_title', 'meta_description', 'image'] as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        if (array_key_exists('title', $validated)) {
            $payload['title'] = ['fr' => $validated['title']];
        }

        if (array_key_exists('excerpt', $validated)) {
            $payload['excerpt'] = ['fr' => $validated['excerpt'] ?? ''];
        }

        // Les images d'un article sont autorisées, celles d'une fiche produit
        // non : la permission est demandée ici, jamais acquise globalement.
        if (array_key_exists('body', $validated)) {
            $payload['body'] = ['fr' => HtmlSanitizer::clean($validated['body'], allowImages: true) ?? ''];
        }

        if ($post === null) {
            $payload['status'] ??= 'draft';
            $payload['slug'] = $this->uniqueSlug($validated['title']);
        }

        return $payload;
    }

    private function syncProducts(BlogPostPayloadRequest $request, BlogPost $post): void
    {
        $validated = $request->validated();

        if (! array_key_exists('product_ids', $validated)) {
            return;
        }

        $ids = array_values(array_unique(array_map('intval', $validated['product_ids'])));

        $post->products()->sync(
            collect($ids)->mapWithKeys(fn (int $id, int $index): array => [$id => ['sort_order' => $index]])->all()
        );
    }

    /** Le slug se fige à la création : le changer casserait l'adresse publiée. */
    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'article-'.Str::lower(Str::random(6));
        $slug = $base;
        $suffix = 2;

        // Une rubrique occupe la même forme d'URL qu'un article : un article
        // qui prendrait « conseils » serait masqué par la route rubrique et
        // deviendrait inatteignable. On écarte ces slugs d'emblée.
        $reserved = BlogCategory::query()->pluck('slug')->all() ?: BlogCategory::SEEDED_SLUGS;

        while (in_array($slug, $reserved, true) || BlogPost::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
