<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StockMovementReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Models\AdminActivityLog;
use App\Models\Carrier;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductSlug;
use App\Models\ProductVariant;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Support\Csv;
use App\Support\HtmlSanitizer;
use App\Support\ImageThumbnailer;
use App\Support\StockContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    private const SORTS = ['id-asc', 'id-desc', 'name-asc', 'name-desc', 'stock-asc', 'stock-desc', 'supplier-asc', 'supplier-desc', 'price-asc', 'price-desc'];

    private const DEFAULT_SORT = 'id-desc';

    private const SORT_COOKIE = 'admin_products_sort_v2';

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $categorySlug = (string) $request->query('category', '');
        $supplierId = $request->filled('supplier') ? (int) $request->query('supplier') : null;
        $tab = in_array($request->query('tab'), ['active', 'disabled', 'in-stock', 'restocking', 'at-supplier', 'out-of-stock', 'no-sku', 'no-gtin', 'no-weight'], true)
            ? (string) $request->query('tab')
            : 'active';

        // An explicit ?sort= always wins; otherwise fall back to the last
        // sort remembered from a previous visit, via cookie.
        $sort = in_array($request->query('sort'), self::SORTS, true)
            ? (string) $request->query('sort')
            : (in_array($request->cookie(self::SORT_COOKIE), self::SORTS, true) ? $request->cookie(self::SORT_COOKIE) : self::DEFAULT_SORT);

        $products = $this->filteredProductsQuery($search, $categorySlug, $tab, $supplierId)
            ->with('category', 'supplier', 'variants.supplier')
            ->withCount('variants')
            ->tap(fn ($query) => $this->applyProductSort($query, $sort))
            ->paginate(24)
            ->withQueryString();

        return response()->view('admin.products.index', [
            'products' => $products,
            'tab' => $tab,
            'sort' => $sort,
            'productCount' => Product::query()->count(),
            'activeCount' => Product::query()->where('is_active', true)->count(),
            'disabledCount' => Product::query()->where('is_active', false)->count(),
            'inStockCount' => Product::query()->tap(fn ($query) => $this->inStock($query))->count(),
            'restockingCount' => Product::query()->tap(fn ($query) => $this->restocking($query))->count(),
            'atSupplierCount' => Product::query()->tap(fn ($query) => $this->atSupplier($query))->count(),
            'outOfStockCount' => Product::query()->tap(fn ($query) => $this->outOfStock($query))->count(),
            'noSkuCount' => Product::query()->tap(fn ($query) => $this->missingSku($query))->count(),
            'noGtinCount' => Product::query()->where('is_active', true)->where(fn ($query) => $query->whereNull('gtin')->orWhere('gtin', ''))->count(),
            'noWeightCount' => Product::query()->where('is_active', true)->where(fn ($query) => $query->whereNull('weight_grams')->orWhere('weight_grams', 0))->count(),
            'categories' => $this->categoryOptions(),
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'search' => $search,
            'categorySlug' => $categorySlug,
            'supplierId' => $supplierId,
        ])->cookie(self::SORT_COOKIE, $sort, 60 * 24 * 365);
    }

    public function export(Request $request): StreamedResponse
    {
        $search = trim((string) $request->query('search', ''));
        $categorySlug = (string) $request->query('category', '');
        $supplierId = $request->filled('supplier') ? (int) $request->query('supplier') : null;
        $tab = in_array($request->query('tab'), ['active', 'disabled', 'in-stock', 'at-supplier', 'out-of-stock', 'no-sku', 'no-gtin', 'no-weight'], true)
            ? (string) $request->query('tab')
            : 'active';

        $products = $this->filteredProductsQuery($search, $categorySlug, $tab, $supplierId)
            ->with('category', 'supplier')
            ->orderBy('id')
            ->get();

        return Csv::download(
            'products-'.now()->format('Y-m-d').'.csv',
            ['ID', 'Name', 'SKU', 'GTIN', 'Category', 'Supplier', 'Price', 'Stock', 'Weight (g)', 'Active'],
            $products->map(fn (Product $product): array => [
                $product->id,
                $product->localizedName(),
                $product->sku ?? '',
                $product->gtin ?? '',
                $product->category?->localizedName() ?? '',
                $product->supplier?->name ?? '',
                number_format($product->price_cents / 100, 2, '.', ''),
                $product->quantity,
                $product->weight_grams ?? '',
                $product->is_active ? 'yes' : 'no',
            ])
        );
    }

    private function filteredProductsQuery(string $search, string $categorySlug, string $tab, ?int $supplierId = null): Builder
    {
        return Product::query()
            ->tap(fn ($query) => $this->applyProductTab($query, $tab))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('products.slug', 'like', '%'.$search.'%')
                        ->orWhere('products.name', 'like', '%'.$search.'%')
                        ->orWhere('products.sku', 'like', '%'.$search.'%')
                        // La référence imprimée sur l'article est souvent celle
                        // d'une déclinaison : quarante-six produits n'en portent
                        // aucune eux-mêmes et resteraient introuvables.
                        ->orWhereHas('variants', fn ($query) => $query->where('sku', 'like', '%'.$search.'%'));
                });
            })
            ->when($categorySlug !== '', function ($query) use ($categorySlug): void {
                $query->whereHas('category', function ($query) use ($categorySlug): void {
                    $query->where('slug', $categorySlug)
                        ->orWhereHas('parent', fn ($query) => $query->where('slug', $categorySlug));
                });
            })
            ->when($supplierId !== null, fn ($query) => $query->where('supplier_id', $supplierId));
    }

    public function create(): View
    {
        return view('admin.products.form', [
            'product' => new Product([
                'is_active' => true,
                'sort_order' => 0,
            ]),
            'categories' => $this->categoryOptions(),
            'carriers' => Carrier::query()->orderBy('sort_order')->get(),
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            // Un produit qui n'existe pas encore n'a rien en commande.
            'inboundStock' => ['quantity' => 0, 'orders' => collect()],
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        // Le formulaire produit écrit des quantités à plusieurs endroits — le
        // produit, chaque ligne de déclinaison, la remise à zéro quand la
        // dernière disparaît. Une seule parenthèse les nomme toutes.
        $product = StockContext::during(StockMovementReason::ProductEdited, function () use ($request): Product {
            $product = Product::query()->create($this->payload($request));
            $this->syncImages($request, $product, null);
            $this->syncVariants($request, $product);
            $product->refresh();
            $this->clearMainProductFieldsIfHasVariants($product);
            $product->reconcileQuantity();

            return $product;
        });

        AdminActivityLog::record('product.created', $product, 'Created product '.$product->localizedName());

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('status', 'Product created.');
    }

    public function edit(Product $product): View
    {
        return view('admin.products.form', [
            'product' => $product->load('images', 'variants'),
            'categories' => $this->categoryOptions(),
            'carriers' => Carrier::query()->orderBy('sort_order')->get(),
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'averagePurchaseCostInclVatCents' => $product->averagePurchaseCostInclVatCents(),
            'inboundStock' => $product->inboundStock(),
            'receivedPurchaseUnits' => $product->receivedPurchaseUnits(),
        ]);
    }

    public function update(StoreProductRequest $request, Product $product): RedirectResponse
    {
        StockContext::during(StockMovementReason::ProductEdited, function () use ($request, $product): void {
            $hadVariants = $product->hasVariants();
            $oldCoverImage = $product->image;
            $product->update($this->payload($request, $product));
            $this->syncImages($request, $product, $oldCoverImage);
            $this->syncVariants($request, $product);
            $product->refresh();
            $this->clearMainProductFieldsIfHasVariants($product);

            if ($product->hasVariants()) {
                $product->reconcileQuantity();
            } elseif ($hadVariants) {
                // The last variant was just removed: its stock sum is stale
                // and no longer tracks anything real, so reset to zero rather
                // than leaving the product looking in stock by accident.
                $product->update(['quantity' => 0]);
            }
        });

        AdminActivityLog::record('product.updated', $product, 'Updated product '.$product->localizedName());

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('status', 'Product saved.');
    }

    public function toggleStatus(Product $product): RedirectResponse
    {
        $product->update(['is_active' => ! $product->is_active]);
        AdminActivityLog::record(
            $product->is_active ? 'product.activated' : 'product.disabled',
            $product,
            ($product->is_active ? 'Activated ' : 'Disabled ').$product->localizedName()
        );

        return back()->with('status', $product->is_active ? 'Product activated.' : 'Product disabled.');
    }

    /**
     * Tout ce qui a fait bouger le stock de ce produit.
     *
     * Ouverte à tous les administrateurs : la page ne montre ni prix d'achat
     * ni paiement, et celui qui réceptionne la marchandise est justement
     * celui qui a besoin de la relire.
     */
    public function stockHistory(Request $request, Product $product): View
    {
        $product->load('variants');

        $reason = StockMovementReason::tryFrom((string) $request->query('reason', ''));
        $variantId = (int) $request->query('variant', 0);

        $movements = $product->stockMovements()
            ->with(['user', 'subject'])
            ->when($reason !== null, fn ($query) => $query->where('reason', $reason))
            ->when($variantId > 0, fn ($query) => $query->where('product_variant_id', $variantId))
            ->simplePaginate(50)
            ->withQueryString();

        return view('admin.products.stock-history', [
            'product' => $product,
            'movements' => $movements,
            'reason' => $reason,
            'variantId' => $variantId,
            'drift' => $this->stockLedgerDrift($product),
        ]);
    }

    /**
     * Comment le coût d'achat moyen affiché sur la fiche produit a été
     * obtenu — une ligne par bon de commande reçu, dans le même calcul que
     * Product::averagePurchaseCostInclVatCents(), pour que la page et le
     * chiffre ne puissent jamais se contredire.
     */
    public function averageCost(Product $product): View
    {
        $lines = $product->purchaseOrderItems()
            ->with('purchaseOrder.items')
            ->where('quantity_received', '>', 0)
            ->get()
            ->sortBy(fn (PurchaseOrderItem $line) => $line->purchaseOrder->received_at ?? $line->purchaseOrder->created_at)
            ->values();

        // Sommée ici plutôt que reprise de moyenne × unités : la moyenne est
        // elle-même arrondie, donc le produit des deux ne retombe pas
        // toujours exactement sur la somme des lignes.
        $totalInclVatCents = (int) $lines->sum(
            fn (PurchaseOrderItem $line): int => $line->purchaseOrder->receivedLineTotalInclVatWithChargesCents($line),
        );

        return view('admin.products.average-cost', [
            'product' => $product,
            'lines' => $lines,
            'units' => $product->receivedPurchaseUnits(),
            'totalInclVatCents' => $totalInclVatCents,
            'averageCostInclVatCents' => $product->averagePurchaseCostInclVatCents(),
        ]);
    }

    /**
     * Ce que le journal croit, contre ce que la base dit vraiment.
     *
     * C'est tout l'intérêt de stocker le solde après coup : une écriture
     * passée à côté de l'observateur — un UPDATE direct, une requête au
     * query builder — se voit ici et nulle part ailleurs.
     *
     * @return list<array{label: string, expected: int, actual: int}>
     */
    private function stockLedgerDrift(Product $product): array
    {
        $stockables = $product->hasVariants()
            ? $product->variants->map(fn (ProductVariant $variant) => [
                'label' => $variant->label() ?: 'Variant #'.$variant->id,
                'quantity' => $variant->quantity,
                'movement' => $product->stockMovements()->where('product_variant_id', $variant->id)->first(),
            ])
            : collect([[
                'label' => $product->localizedName(),
                'quantity' => $product->quantity,
                'movement' => $product->stockMovements()->whereNull('product_variant_id')->first(),
            ]]);

        return $stockables
            // Sans mouvement enregistré, il n'y a rien à contredire : le
            // journal démarre vide et ne prétend pas connaître le passé.
            ->filter(fn (array $row) => $row['movement'] !== null && $row['movement']->quantity_after !== $row['quantity'])
            ->map(fn (array $row) => [
                'label' => $row['label'],
                'expected' => $row['movement']->quantity_after,
                'actual' => $row['quantity'],
            ])
            ->values()
            ->all();
    }

    public function updateQuantity(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $previousQuantity = $product->quantity;

        // La raison est facultative, mais c'est elle qui rend l'historique
        // utile : un chiffre corrigé sans explication ne se relit pas.
        StockContext::during(
            StockMovementReason::ManualAdjustment,
            fn () => $product->update(['quantity' => $validated['quantity']]),
            note: $validated['note'] ?? null,
        );

        AdminActivityLog::record(
            'product.restocked',
            $product,
            'Set stock for '.$product->localizedName().' from '.$previousQuantity.' to '.$validated['quantity']
        );

        return back()->with('status', 'Stock updated for '.$product->localizedName().'.');
    }

    /**
     * Saves only the supplier block, so an admin comparing suppliers can note
     * a price without pushing the whole product form — which would also
     * revalidate the description, the photos and every variant row.
     *
     * A product with variants keeps its supplier data on the variants, so the
     * block is refused here rather than silently written and later cleared.
     */
    public function updateSupplier(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        abort_if($product->hasVariants(), 403);

        $validated = $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'available_at_supplier' => ['sometimes', 'boolean'],
            'supplier_reference' => ['nullable', 'string', 'max:120'],
            'supplier_product_url' => ['nullable', 'url', 'max:2048'],
            'supplier_price' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'markup_percent' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ]);

        $product->update([
            'supplier_id' => $validated['supplier_id'] ?? null,
            'available_at_supplier' => $request->boolean('available_at_supplier'),
            'supplier_reference' => filled($validated['supplier_reference'] ?? null) ? trim((string) $validated['supplier_reference']) : null,
            'supplier_product_url' => filled($validated['supplier_product_url'] ?? null) ? trim((string) $validated['supplier_product_url']) : null,
            'supplier_price_cents' => filled($validated['supplier_price'] ?? null) ? (int) round((float) $validated['supplier_price'] * 100) : null,
            'markup_basis_points' => filled($validated['markup_percent'] ?? null) ? (int) round((float) $validated['markup_percent'] * 100) : null,
        ]);

        AdminActivityLog::record('product.supplier_updated', $product, 'Updated supplier details for '.$product->localizedName());

        $message = 'Supplier details saved for '.$product->localizedName().'.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('status', $message);
    }

    private function applyProductSort(Builder $query, string $sort): void
    {
        [$column, $direction] = match ($sort) {
            'id-desc' => ['id', 'desc'],
            'name-asc' => ['name', 'asc'],
            'name-desc' => ['name', 'desc'],
            'stock-asc' => ['quantity', 'asc'],
            'stock-desc' => ['quantity', 'desc'],
            'supplier-asc' => ['supplier', 'asc'],
            'supplier-desc' => ['supplier', 'desc'],
            'price-asc' => ['price_cents', 'asc'],
            'price-desc' => ['price_cents', 'desc'],
            default => ['id', 'asc'],
        };

        if ($column === 'name') {
            $nameSql = $query->getConnection()->getDriverName() === 'sqlite'
                ? "json_extract(name, '$.fr')"
                : "json_unquote(json_extract(name, '$.fr'))";

            $query->orderByRaw($nameSql.' '.$direction)->orderBy('id');

            return;
        }

        if ($column === 'supplier') {
            if (empty($query->getQuery()->columns)) {
                $query->select('products.*');
            }

            $query->leftJoin('suppliers', 'suppliers.id', '=', 'products.supplier_id')
                ->orderByRaw('suppliers.name IS NULL')
                ->orderBy('suppliers.name', $direction)
                ->orderBy('products.id');

            return;
        }

        $query->orderBy($column, $direction);

        if ($column !== 'id') {
            $query->orderBy('id');
        }
    }

    /**
     * Products with something on the shelf, whatever the quantity.
     *
     * A product sold in several sizes is read through its sizes: its own
     * quantity is a total, and a total of zero can still hide a size that is
     * available.
     */
    private function inStock(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->where(fn (Builder $query) => $query->doesntHave('variants')->where('quantity', '>', 0))
                    ->orWhereHas('variants', fn (Builder $query) => $query->where('is_active', true)->where('quantity', '>', 0));
            });
    }

    /**
     * Nothing on the shelf, but the supplier can still fetch it.
     *
     * The exact opposite of outOfStock() among the products at zero, so the
     * two tabs split that group cleanly instead of overlapping.
     */
    /**
     * Les produits dont un réassort est en route.
     *
     * Même définition qu'en boutique : une ligne de commande fournisseur
     * encore incomplète, sur une commande envoyée ou partiellement reçue. La
     * déclinaison compte pour son produit.
     */
    private function restocking(Builder $query): void
    {
        $open = fn (Builder $item) => $item
            ->whereColumn('quantity_received', '<', 'quantity_ordered')
            ->whereHas('purchaseOrder', fn (Builder $po) => $po->open());

        $query->where('is_active', true)
            ->where(fn (Builder $inner) => $inner
                ->whereHas('purchaseOrderItems', $open)
                ->orWhereHas('variants.purchaseOrderItems', $open));
    }

    private function atSupplier(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(function (Builder $query): void {
                // « Aucune déclinaison disponible » est vrai pour un produit
                // qui n'en a aucune : il faut donc exiger qu'il en ait.
                $query->where(fn (Builder $query) => $query->doesntHave('variants')->where('quantity', '<=', 0))
                    ->orWhere(fn (Builder $query) => $query->has('variants')
                        ->whereDoesntHave('variants', fn (Builder $query) => $query->where('is_active', true)->where('quantity', '>', 0)));
            })
            ->where(function (Builder $query): void {
                $query->where(fn (Builder $query) => $query->doesntHave('variants')
                    ->whereNotNull('supplier_id')
                    ->where('available_at_supplier', true))
                    ->orWhereHas('variants', fn (Builder $query) => $query->where('is_active', true)
                        ->whereNotNull('supplier_id')
                        ->where('available_at_supplier', true));
            });
    }

    /**
     * Products genuinely out of stock — nothing on the shelf, and nothing the
     * supplier can fetch.
     *
     * A quantity of zero alone is not the same thing: thirty of the fifty-one
     * rows this tab listed could still be ordered in, so the list of what
     * actually needs restocking was drowned in items that did not.
     */
    private function outOfStock(Builder $query): void
    {
        $query->where('is_active', true)
            ->where('quantity', '<=', 0)
            ->whereNot(function (Builder $query): void {
                // Sans déclinaison, c'est le produit qui porte le fournisseur.
                $query->where(fn (Builder $query) => $query->doesntHave('variants')
                    ->whereNotNull('supplier_id')
                    ->where('available_at_supplier', true))
                    // Avec déclinaisons, il suffit qu'une seule soit commandable.
                    ->orWhereHas('variants', fn (Builder $query) => $query->where('is_active', true)
                        ->whereNotNull('supplier_id')
                        ->where('available_at_supplier', true));
            });
    }

    /**
     * Products still owing a reference.
     *
     * A product sold in several sizes carries its reference on each of them,
     * not on itself. Listing it as incomplete while every size is covered
     * turns the tab into a to-do list that can never be emptied — so a
     * product drops out once nothing is left to fill in, whether that is on
     * the product or on its variants.
     */
    private function missingSku(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('sku')->orWhere('sku', ''))
            ->whereNot(function (Builder $query): void {
                $query->has('variants')
                    ->whereDoesntHave('variants', fn (Builder $query) => $query->whereNull('sku')->orWhere('sku', ''));
            });
    }

    private function applyProductTab(Builder $query, string $tab): void
    {
        match ($tab) {
            'disabled' => $query->where('is_active', false),
            'in-stock' => $this->inStock($query),
            'restocking' => $this->restocking($query),
            'at-supplier' => $this->atSupplier($query),
            'out-of-stock' => $this->outOfStock($query),
            'no-sku' => $this->missingSku($query),
            'no-gtin' => $query->where('is_active', true)->where(fn (Builder $query) => $query->whereNull('gtin')->orWhere('gtin', '')),
            'no-weight' => $query->where('is_active', true)->where(fn (Builder $query) => $query->whereNull('weight_grams')->orWhere('weight_grams', 0)),
            default => $query->where('is_active', true),
        };
    }

    private function categoryOptions()
    {
        $all = Category::query()->with('parent')->orderBy('sort_order')->get();

        return $all->whereNull('parent_id')
            ->flatMap(function (Category $root) use ($all) {
                return collect([$root])->concat(
                    $all->where('parent_id', $root->id)->sortBy('sort_order')->values(),
                );
            })
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(StoreProductRequest $request, ?Product $product = null): array
    {
        $slug = $product?->slug ?: Str::slug($request->string('name'));

        if ($slug === '') {
            $slug = 'product-'.Str::lower(Str::random(6));
        }

        if ($product === null) {
            $original = $slug;
            $i = 2;

            // Les adresses abandonnées comptent : elles redirigent encore.
            while (ProductSlug::query()->where('slug', $slug)->exists()) {
                $slug = $original.'-'.$i;
                $i++;
            }
        }

        return [
            'category_id' => $request->integer('category_id'),
            'supplier_id' => $request->filled('supplier_id') ? $request->integer('supplier_id') : null,
            'available_at_supplier' => $request->boolean('available_at_supplier'),
            'supplier_product_url' => $request->filled('supplier_product_url') ? $request->string('supplier_product_url')->trim()->toString() : null,
            'supplier_reference' => $request->filled('supplier_reference') ? $request->string('supplier_reference')->trim()->toString() : null,
            'supplier_price_cents' => $request->filled('supplier_price') ? (int) round((float) $request->input('supplier_price') * 100) : null,
            'markup_basis_points' => $request->filled('markup_percent') ? (int) round((float) $request->input('markup_percent') * 100) : null,
            'slug' => $slug,
            'is_active' => $request->boolean('is_active'),
            'age_restricted' => $request->boolean('age_restricted'),
            'image_may_vary' => $request->boolean('image_may_vary'),
            'sku' => $request->filled('sku') ? $request->string('sku')->trim()->toString() : null,
            'gtin' => $request->filled('gtin') ? $request->string('gtin')->trim()->toString() : null,
            'weight_grams' => $request->filled('weight_grams') ? $request->integer('weight_grams') : null,
            'carrier_ids' => array_map('intval', $request->input('carrier_ids', [])),
            'name' => [
                'fr' => $request->string('name')->toString(),
            ],
            'description' => [
                'fr' => HtmlSanitizer::clean($request->input('description')) ?? '',
            ],
            'characteristics' => $this->characteristicsPayload($request),
            'filter_attributes' => $this->filterAttributesPayload($request),
            'price_cents' => (int) round(((float) $request->input('price')) * 100),
            'quantity' => $request->integer('quantity'),
            'image' => $this->resolveImage($request, $product, $slug),
            'sort_order' => $product?->sort_order ?? 0,
        ];
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function characteristicsPayload(StoreProductRequest $request): array
    {
        $labels = (array) $request->input('characteristic_label', []);
        $values = (array) $request->input('characteristic_value', []);

        $characteristics = [];

        foreach ($labels as $index => $label) {
            $label = trim((string) $label);
            $value = trim((string) ($values[$index] ?? ''));

            if ($label === '' || $value === '') {
                continue;
            }

            $characteristics[] = ['label' => $label, 'value' => $value];
        }

        return $characteristics;
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function filterAttributesPayload(StoreProductRequest $request): array
    {
        $labels = (array) $request->input('filter_label', []);
        $values = (array) $request->input('filter_value', []);

        $filterAttributes = [];

        foreach ($labels as $index => $label) {
            $label = trim((string) $label);
            $value = trim((string) ($values[$index] ?? ''));

            if ($label === '' || $value === '') {
                continue;
            }

            $filterAttributes[] = ['label' => $label, 'value' => $value];
        }

        return $filterAttributes;
    }

    /**
     * SKU, GTIN and supplier fields live on variants once a product has
     * them — the main product's own values would be stale/ambiguous, so
     * they're cleared regardless of what the (disabled) form fields sent.
     */
    private function clearMainProductFieldsIfHasVariants(Product $product): void
    {
        if (! $product->hasVariants()) {
            return;
        }

        $product->update([
            'sku' => null,
            'gtin' => null,
            'supplier_id' => null,
            'supplier_reference' => null,
            'supplier_product_url' => null,
            'supplier_price_cents' => null,
            'markup_basis_points' => null,
        ]);
    }

    /**
     * Variant attributes are entered as free text like "Taille: L, Couleur: Rouge"
     * so the admin isn't locked into a fixed set of attribute types.
     */
    private function syncVariants(StoreProductRequest $request, Product $product): void
    {
        $rows = (array) $request->input('variants', []);
        $files = $request->file('variant_images', []);

        foreach ($rows as $index => $row) {
            $variantId = filled($row['id'] ?? null) ? (int) $row['id'] : null;

            if (! empty($row['_delete'])) {
                if ($variantId !== null) {
                    $variant = ProductVariant::query()->find($variantId);

                    if ($variant !== null && $variant->product_id === $product->id) {
                        if ($variant->image) {
                            $this->deleteStoredImageFile($variant->image);
                        }

                        $variant->delete();
                    }
                }

                continue;
            }

            $attributesText = trim((string) ($row['attributes_text'] ?? ''));
            $sku = filled($row['sku'] ?? null) ? trim((string) $row['sku']) : null;
            $gtin = filled($row['gtin'] ?? null) ? trim((string) $row['gtin']) : null;

            if ($variantId === null && $attributesText === '' && $sku === null && $gtin === null) {
                continue;
            }

            $payload = [
                'attribute_values' => $this->parseVariantAttributes($attributesText),
                'sku' => $sku,
                'gtin' => $gtin,
                'price_cents' => filled($row['price'] ?? null) ? (int) round(((float) $row['price']) * 100) : null,
                'quantity' => (int) ($row['quantity'] ?? 0),
                'is_active' => ! empty($row['is_active']),
                'sort_order' => (int) $index,
                'supplier_id' => filled($row['supplier_id'] ?? null) ? (int) $row['supplier_id'] : null,
                'available_at_supplier' => ! empty($row['available_at_supplier']),
                'supplier_reference' => filled($row['supplier_reference'] ?? null) ? trim((string) $row['supplier_reference']) : null,
                'supplier_product_url' => filled($row['supplier_product_url'] ?? null) ? trim((string) $row['supplier_product_url']) : null,
            ];

            $uploadedImage = $files[$index] ?? null;

            if ($variantId !== null) {
                $variant = ProductVariant::query()->find($variantId);

                if ($variant === null || $variant->product_id !== $product->id) {
                    continue;
                }

                if ($uploadedImage instanceof UploadedFile) {
                    if ($variant->image) {
                        $this->deleteStoredImageFile($variant->image);
                    }

                    $payload['image'] = $this->storeUploadedImage($uploadedImage, $product->slug.'-variant');
                } elseif (! empty($row['remove_image'])) {
                    if ($variant->image) {
                        $this->deleteStoredImageFile($variant->image);
                    }

                    $payload['image'] = null;
                }

                $variant->update($payload);

                continue;
            }

            if ($uploadedImage instanceof UploadedFile) {
                $payload['image'] = $this->storeUploadedImage($uploadedImage, $product->slug.'-variant');
            }

            $product->variants()->create($payload);
        }
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function parseVariantAttributes(string $text): array
    {
        if ($text === '') {
            return [];
        }

        return collect(explode(',', $text))
            ->map(function (string $pair): array {
                $parts = explode(':', $pair, 2);

                return [
                    'label' => trim($parts[0] ?? ''),
                    'value' => trim($parts[1] ?? ''),
                ];
            })
            ->filter(fn (array $attribute): bool => $attribute['label'] !== '' && $attribute['value'] !== '')
            ->values()
            ->all();
    }

    private function resolveImage(StoreProductRequest $request, ?Product $product, string $slug): string
    {
        if ($request->hasFile('image_file')) {
            return $this->storeUploadedImage($request->file('image_file'), $slug);
        }

        return $product?->image ?? '';
    }

    private function storeUploadedImage(UploadedFile $file, string $slug): string
    {
        $directory = public_path('images/products');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $name = Str::slug($slug).'-'.Str::lower(Str::random(6)).'.'.$file->getClientOriginalExtension();
        $file->move($directory, $name);

        $relativePath = ImageThumbnailer::normalizeMain('products/'.$name) ?? 'products/'.$name;

        ImageThumbnailer::generate($relativePath);

        return $relativePath;
    }

    /**
     * Reconciles the merged cover + gallery drag list against storage. The cover
     * lives on the `image` column while the rest live in `product_images`, but the
     * admin form presents them as one reorderable list, so this translates the
     * posted order back into that split.
     */
    private function syncImages(StoreProductRequest $request, Product $product, ?string $oldCoverImage): void
    {
        $this->removeGalleryImages($product, (array) $request->input('remove_gallery_images', []));

        $orderKeys = $this->parseImageOrder((string) $request->input('gallery_order', ''));
        $removeMain = $request->boolean('remove_main');
        $newCoverUploaded = $request->hasFile('image_file');

        $this->applyImageOrder($product, $orderKeys, $removeMain, $oldCoverImage, $newCoverUploaded);

        $newExtras = array_values(array_filter($request->file('gallery_images', []) ?? []));
        $this->appendGalleryImages($newExtras, $product);
    }

    /** @return array<int, string> */
    private function parseImageOrder(string $order): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $order))));
    }

    /** @param  array<int, mixed>  $ids */
    private function removeGalleryImages(Product $product, array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));

        if ($ids === []) {
            return;
        }

        $images = $product->images()->whereIn('id', $ids)->get();

        foreach ($images as $image) {
            $this->deleteStoredImageFile($image->image);
            $image->delete();
        }
    }

    /** @param  array<int, string>  $orderKeys */
    private function applyImageOrder(
        Product $product,
        array $orderKeys,
        bool $removeMain,
        ?string $oldCoverImage,
        bool $newCoverUploaded,
    ): void {
        if ($orderKeys === [] && ! $removeMain) {
            return;
        }

        $product->refresh();
        $existing = $product->images()->get()->keyBy(fn (ProductImage $image) => (string) $image->id);

        $slots = [];
        $mainIncluded = false;

        foreach ($orderKeys as $key) {
            if ($key === 'main') {
                if ($removeMain || $oldCoverImage === null) {
                    continue;
                }

                $mainIncluded = true;
                $slots[] = $oldCoverImage;

                continue;
            }

            if (! $existing->has($key)) {
                continue;
            }

            $slots[] = $existing->get($key)->image;
        }

        $oldCoverOrphaned = $removeMain && ! $mainIncluded && $oldCoverImage !== null;

        if ($newCoverUploaded) {
            // The cover is already the freshly uploaded file. Every surviving
            // existing key (including a demoted "main") becomes a gallery entry.
            $product->images()->delete();

            foreach (array_values($slots) as $index => $image) {
                ProductImage::query()->create([
                    'product_id' => $product->id,
                    'image' => $image,
                    'sort_order' => $index,
                ]);
            }
        } elseif ($slots === []) {
            if ($oldCoverOrphaned) {
                $product->forceFill(['image' => ''])->save();
            }
        } else {
            $product->images()->delete();

            $newCoverImage = array_shift($slots);
            $product->forceFill(['image' => $newCoverImage])->save();

            foreach (array_values($slots) as $index => $image) {
                ProductImage::query()->create([
                    'product_id' => $product->id,
                    'image' => $image,
                    'sort_order' => $index,
                ]);
            }
        }

        if ($oldCoverOrphaned) {
            $product->refresh();
            $inUse = collect([$product->image])->merge($product->images()->pluck('image'))->all();

            if (! in_array($oldCoverImage, $inUse, true)) {
                $this->deleteStoredImageFile($oldCoverImage);
            }
        }
    }

    /** @param  array<int, UploadedFile>  $files */
    private function appendGalleryImages(array $files, Product $product): void
    {
        if ($files === []) {
            return;
        }

        $position = (int) $product->images()->max('sort_order') + 1;

        foreach ($files as $file) {
            ProductImage::query()->create([
                'product_id' => $product->id,
                'image' => $this->storeUploadedImage($file, $product->slug),
                'sort_order' => $position,
            ]);

            $position++;
        }
    }

    private function deleteStoredImageFile(string $image): void
    {
        if ($image === '' || str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return;
        }

        $path = public_path('images/'.$image);

        if (is_file($path)) {
            @unlink($path);
        }
    }
}
