<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBlogPostRequest;
use App\Models\AdminActivityLog;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Product;
use App\Support\HtmlSanitizer;
use App\Support\ImageThumbnailer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BlogPostController extends Controller
{
    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), ['draft', 'scheduled', 'published'], true)
            ? $request->query('tab')
            : 'all';

        $search = trim((string) $request->query('search', ''));

        $posts = BlogPost::query()
            ->with('category')
            ->when($search !== '', fn (Builder $query) => $query->where('title', 'like', '%'.$search.'%'))
            ->when($tab === 'draft', fn (Builder $query) => $query->where('status', 'draft'))
            ->when($tab === 'published', fn (Builder $query) => $query->visible())
            // Programmé : publié, mais daté du futur. Sans onglet dédié, cet
            // état ne se voit nulle part — il est absent des deux autres.
            ->when($tab === 'scheduled', fn (Builder $query) => $query
                ->where('status', 'published')
                ->where(fn (Builder $inner) => $inner
                    ->whereNull('published_at')
                    ->orWhere('published_at', '>', now())))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.blog.index', [
            'posts' => $posts,
            'tab' => $tab,
            'search' => $search,
            'allCount' => BlogPost::query()->count(),
            'draftCount' => BlogPost::query()->where('status', 'draft')->count(),
            'publishedCount' => BlogPost::query()->visible()->count(),
            'scheduledCount' => BlogPost::query()
                ->where('status', 'published')
                ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '>', now()))
                ->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.blog.form', [
            'post' => new BlogPost(['status' => 'draft']),
            'categories' => BlogCategory::query()->orderBy('sort_order')->get(),
            'selectedProducts' => collect(),
            'productOptions' => $this->productOptions(),
        ]);
    }

    public function edit(BlogPost $post): View
    {
        return view('admin.blog.form', [
            'post' => $post->load('products'),
            'categories' => BlogCategory::query()->orderBy('sort_order')->get(),
            'selectedProducts' => $post->products,
            'productOptions' => $this->productOptions(),
        ]);
    }

    /**
     * La liste des produits sélectionnables, filtrée côté navigateur.
     *
     * Le catalogue tient largement dans la page, et c'est déjà comme cela que
     * `admin-search-select.js` procède ailleurs : pas de point d'entrée JSON
     * de plus à écrire, à protéger et à limiter.
     *
     * @return array<int, array{id: int, label: string, sku: string}>
     */
    private function productOptions(): array
    {
        return Product::query()
            ->orderBy('slug')
            ->get(['id', 'name', 'sku', 'slug'])
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'label' => $product->localizedName(),
                'sku' => (string) $product->sku,
            ])
            ->all();
    }

    public function store(StoreBlogPostRequest $request): RedirectResponse
    {
        $post = BlogPost::query()->create($this->payload($request, null));
        $this->syncProducts($request, $post);

        AdminActivityLog::record('blog_post.created', $post, 'Created post '.$post->localizedTitle());

        return redirect()->route('admin.blog.edit', $post)->with('status', 'Post created.');
    }

    public function update(StoreBlogPostRequest $request, BlogPost $post): RedirectResponse
    {
        $post->update($this->payload($request, $post));
        $this->syncProducts($request, $post);

        AdminActivityLog::record('blog_post.updated', $post, 'Updated post '.$post->localizedTitle());

        return redirect()->route('admin.blog.edit', $post)->with('status', 'Post saved.');
    }

    public function destroy(BlogPost $post): RedirectResponse
    {
        $title = $post->localizedTitle();
        $this->deleteStoredImage($post->image);
        $post->delete();

        AdminActivityLog::record('blog_post.deleted', null, 'Deleted post '.$title);

        return redirect()->route('admin.blog.index')->with('status', 'Post deleted.');
    }

    /** L'éditeur envoie ici les images qu'il insère dans le texte. */
    public function uploadBodyImage(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'image', 'max:8192']]);

        $path = $this->storeUploadedImage($request->file('file'), 'blog-body', landscape: false);

        return response()->json(['url' => '/images/'.$path]);
    }

    /** @return array<string, mixed> */
    private function payload(StoreBlogPostRequest $request, ?BlogPost $post): array
    {
        $validated = $request->validated();

        $payload = [
            'blog_category_id' => (int) $validated['blog_category_id'],
            'title' => ['fr' => $validated['title']],
            'excerpt' => ['fr' => $validated['excerpt'] ?? ''],
            // Le corps d'article a le droit aux images ; les fiches produit
            // passent par le même nettoyeur et ne l'ont pas.
            'body' => ['fr' => HtmlSanitizer::clean($validated['body'], allowImages: true) ?? ''],
            'status' => $validated['status'],
            'published_at' => $validated['published_at'] ?? null,
            'meta_title' => $validated['meta_title'] ?? null,
            'meta_description' => $validated['meta_description'] ?? null,
            // Le préfixe est ajouté à l'affichage : on ne range que le nom,
            // même si l'auteur a retapé « Photo © » devant.
            'image_credit' => $this->normalizeCredit($validated['image_credit'] ?? null),
            // Only rows with a URL survive; an emptied block stores null.
            'sources' => collect($validated['sources'] ?? [])
                ->filter(fn ($source): bool => filled($source['url'] ?? null))
                ->map(fn (array $source): array => [
                    'label' => trim((string) ($source['label'] ?? '')),
                    'url' => trim($source['url']),
                ])
                ->values()
                ->all() ?: null,
        ];

        // Le slug se fige à la création : le changer casserait l'adresse d'un
        // article déjà partagé ou indexé.
        if ($post === null) {
            $payload['slug'] = $this->uniqueSlug($validated['slug'] ?? $validated['title']);
        }

        if ($request->hasFile('image_file')) {
            $this->deleteStoredImage($post?->image);
            $payload['image'] = $this->storeUploadedImage($request->file('image_file'), 'blog');
        } elseif ($request->boolean('remove_image')) {
            $this->deleteStoredImage($post?->image);
            $payload['image'] = null;
            // Le crédit ne survit pas à l'image qu'il crédite.
            $payload['image_credit'] = null;
        }

        return $payload;
    }

    private function normalizeCredit(?string $credit): ?string
    {
        $credit = BlogPost::stripCreditPrefix((string) $credit);

        return $credit === '' ? null : $credit;
    }

    private function syncProducts(StoreBlogPostRequest $request, BlogPost $post): void
    {
        $ids = array_values(array_unique(array_map('intval', $request->input('product_ids', []))));

        $post->products()->sync(
            collect($ids)->mapWithKeys(fn (int $id, int $index): array => [$id => ['sort_order' => $index]])->all()
        );
    }

    private function uniqueSlug(string $source): string
    {
        $base = Str::slug($source) ?: 'article-'.Str::lower(Str::random(6));
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

    private function storeUploadedImage(UploadedFile $file, string $prefix, bool $landscape = true): string
    {
        $directory = public_path('images/blog');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $name = $prefix.'-'.Str::lower(Str::random(8)).'.'.$file->getClientOriginalExtension();
        $file->move($directory, $name);

        $relativePath = 'blog/'.$name;

        if ($landscape) {
            $relativePath = ImageThumbnailer::normalizeLandscape($relativePath) ?? $relativePath;
            ImageThumbnailer::generateLandscapeThumbnail($relativePath);
        }

        return $relativePath;
    }

    /**
     * Supprime le fichier et sa vignette.
     *
     * `Admin\ProductController::deleteStoredImageFile()` oublie la seconde et
     * laisse une vignette orpheline à chaque suppression. Ne pas répéter.
     */
    private function deleteStoredImage(?string $image): void
    {
        if ($image === null || $image === '' || str_starts_with($image, 'http')) {
            return;
        }

        foreach ([public_path('images/'.$image), ImageThumbnailer::absoluteThumbnailPath($image)] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
