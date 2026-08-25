<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\StockMovementReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\ProductPayloadRequest;
use App\Http\Requests\Api\Admin\StoreProductRequest;
use App\Http\Requests\Api\Admin\UpdateProductRequest;
use App\Http\Resources\Api\Admin\ProductResource;
use App\Models\Product;
use App\Models\ProductSlug;
use App\Support\HtmlSanitizer;
use App\Support\StockContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    private const MAX_PER_PAGE = 100;

    /**
     * La liste, filtrable.
     *
     * Sans filtre, un client de synchronisation devait parcourir tout le
     * catalogue pour retrouver un article. Les critères ci-dessous sont ceux
     * dont un tel client a besoin : retrouver par code, par catégorie, ou ne
     * demander que ce qui a bougé depuis son dernier passage.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->with('category', 'images', 'variants')
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $term = trim((string) $request->query('search'));

                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('name', 'like', '%'.$term.'%')
                        ->orWhere('slug', 'like', '%'.$term.'%')
                        ->orWhere('sku', 'like', '%'.$term.'%');
                });
            })
            ->when($request->filled('sku'), fn (Builder $q) => $q->where('sku', $request->query('sku')))
            ->when($request->filled('gtin'), fn (Builder $q) => $q->where('gtin', $request->query('gtin')))
            ->when($request->filled('slug'), fn (Builder $q) => $q->where('slug', $request->query('slug')))
            ->when($request->filled('category_id'), fn (Builder $q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('supplier_id'), fn (Builder $q) => $q->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->has('is_active'), fn (Builder $q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('updated_since'), function (Builder $query) use ($request): void {
                $query->where('updated_at', '>=', $request->date('updated_since'));
            })
            ->orderBy('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return ProductResource::collection($products);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'data' => new ProductResource($product->load('category', 'images', 'variants')),
        ]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = StockContext::during(
            StockMovementReason::ApiUpdate,
            function () use ($request): Product {
                $product = Product::query()->create($this->payload($request, null));

                $this->syncVariants($request, $product);
                $this->settleVariantState($product);

                return $product;
            },
        );

        return response()->json([
            'data' => new ProductResource($product->load('category', 'images', 'variants')),
        ], 201);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        StockContext::during(
            StockMovementReason::ApiUpdate,
            function () use ($request, $product): void {
                $hadVariants = $product->variants()->exists();

                $product->update($this->payload($request, $product));
                $this->syncVariants($request, $product);
                $this->settleVariantState($product, $hadVariants);
            },
        );

        return response()->json([
            'data' => new ProductResource($product->fresh()->load('category', 'images', 'variants')),
        ]);
    }

    private function perPage(Request $request): int
    {
        $requested = $request->filled('per_page') ? $request->integer('per_page') : 50;

        return max(1, min($requested, self::MAX_PER_PAGE));
    }

    /**
     * Traduit le corps de la requête en colonnes.
     *
     * Seules les clés présentes sont reprises : une modification partielle ne
     * doit pas remettre à zéro ce dont elle ne parle pas.
     *
     * @return array<string, mixed>
     */
    private function payload(ProductPayloadRequest $request, ?Product $product): array
    {
        $validated = $request->validated();
        $payload = [];

        $direct = [
            'slug', 'category_id', 'quantity', 'is_active', 'ai_validated', 'age_restricted', 'image_may_vary',
            'featured', 'sort_order', 'sku', 'gtin', 'weight_grams', 'carrier_ids',
            'supplier_id', 'available_at_supplier', 'supplier_reference',
            'supplier_product_url', 'characteristics', 'filter_attributes', 'image',
        ];

        foreach ($direct as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        if (array_key_exists('name', $validated)) {
            $payload['name'] = ['fr' => $validated['name']];
        }

        if (array_key_exists('description', $validated)) {
            $payload['description'] = ['fr' => HtmlSanitizer::clean($validated['description']) ?? ''];
        }

        // `products.image` n'accepte pas NULL : un effacement s'écrit ''.
        if (array_key_exists('image', $validated)) {
            $payload['image'] = $validated['image'] ?? '';
        }

        if (array_key_exists('price', $validated)) {
            $payload['price_cents'] = (int) round($validated['price'] * 100);
        }

        if (array_key_exists('supplier_price', $validated)) {
            $payload['supplier_price_cents'] = $validated['supplier_price'] === null
                ? null
                : (int) round($validated['supplier_price'] * 100);
        }

        if (array_key_exists('markup_percent', $validated)) {
            $payload['markup_basis_points'] = $validated['markup_percent'] === null
                ? null
                : (int) round($validated['markup_percent'] * 100);
        }

        if ($product === null) {
            // Le slug donné l'emporte ; sinon il se déduit du nom.
            $payload['slug'] ??= $this->uniqueSlug($validated['name']);
            $payload['image'] ??= '';
            // En bout de liste plutôt qu'en tête : `sort_order` classe les
            // vitrines par ordre croissant, et un zéro par défaut placerait
            // chaque nouveauté devant tout le catalogue déjà rangé.
            $payload['sort_order'] ??= (int) Product::query()->max('sort_order') + 1;
        }

        return $payload;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'product-'.Str::lower(Str::random(6));
        }

        $slug = $base;
        $suffix = 2;

        // `product_slugs` porte aussi les adresses abandonnées : reprendre
        // l'une d'elles détournerait une redirection encore vivante.
        while (ProductSlug::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * Applique les déclinaisons reçues : mise à jour, création, suppression.
     *
     * Une déclinaison absente du corps de la requête est laissée en place —
     * une modification partielle ne supprime que ce qu'on lui demande de
     * supprimer, par `_delete`.
     */
    private function syncVariants(ProductPayloadRequest $request, Product $product): void
    {
        $rows = $request->validated()['variants'] ?? null;

        if ($rows === null) {
            return;
        }

        foreach ($rows as $index => $row) {
            $variantId = filled($row['id'] ?? null) ? (int) $row['id'] : null;
            $variant = $variantId === null
                ? null
                : $product->variants()->whereKey($variantId)->first();

            if ($variantId !== null && $variant === null) {
                continue;
            }

            if (! empty($row['_delete'])) {
                $variant?->delete();

                continue;
            }

            $payload = [];

            if (array_key_exists('attributes', $row)) {
                $payload['attribute_values'] = $row['attributes'];
            }

            foreach (['sku', 'gtin', 'quantity', 'is_active', 'sort_order', 'supplier_id',
                'available_at_supplier', 'supplier_reference', 'supplier_product_url'] as $field) {
                if (array_key_exists($field, $row)) {
                    $payload[$field] = $row[$field];
                }
            }

            if (array_key_exists('price', $row)) {
                $payload['price_cents'] = $row['price'] === null ? null : (int) round($row['price'] * 100);
            }

            if ($variant !== null) {
                $variant->update($payload);

                continue;
            }

            $payload['attribute_values'] ??= [];
            $payload['quantity'] ??= 0;
            $payload['is_active'] ??= true;
            $payload['sort_order'] ??= $index;

            $product->variants()->create($payload);
        }
    }

    /**
     * Remet le produit d'accord avec ses déclinaisons.
     *
     * C'est ce que faisait déjà le formulaire d'administration et que l'API
     * ne faisait pas : sans cela, `quantity` gardait une valeur qui ne
     * correspondait plus à la somme des déclinaisons, et les identifiants du
     * produit doublonnaient ceux de ses tailles.
     */
    private function settleVariantState(Product $product, bool $hadVariants = false): void
    {
        $product->refresh();

        if ($product->hasVariants()) {
            $product->update([
                'sku' => null,
                'gtin' => null,
                'supplier_id' => null,
                'supplier_reference' => null,
                'supplier_product_url' => null,
                'supplier_price_cents' => null,
                'markup_basis_points' => null,
            ]);

            $product->reconcileQuantity();

            return;
        }

        if ($hadVariants) {
            // La dernière déclinaison vient de disparaître : sa somme ne suit
            // plus rien de réel.
            $product->update(['quantity' => 0]);
        }
    }
}
