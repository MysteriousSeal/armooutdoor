<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Models\Carrier;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Support\HtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $categorySlug = (string) $request->query('category', '');

        $products = Product::query()
            ->with('category')
            ->withCount('variants')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('slug', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%');
                });
            })
            ->when($categorySlug !== '', function ($query) use ($categorySlug): void {
                $query->whereHas('category', function ($query) use ($categorySlug): void {
                    $query->where('slug', $categorySlug)
                        ->orWhereHas('parent', fn ($query) => $query->where('slug', $categorySlug));
                });
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->simplePaginate(24)
            ->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'productCount' => Product::query()->count(),
            'categories' => $this->categoryOptions(),
            'search' => $search,
            'categorySlug' => $categorySlug,
        ]);
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
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $product = Product::query()->create($this->payload($request));
        $this->syncImages($request, $product, null);
        $this->syncVariants($request, $product);
        $product->refresh()->reconcileQuantity();

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
        ]);
    }

    public function update(StoreProductRequest $request, Product $product): RedirectResponse
    {
        $hadVariants = $product->hasVariants();
        $oldCoverImage = $product->image;
        $product->update($this->payload($request, $product));
        $this->syncImages($request, $product, $oldCoverImage);
        $this->syncVariants($request, $product);
        $product->refresh();

        if ($product->hasVariants()) {
            $product->reconcileQuantity();
        } elseif ($hadVariants) {
            // The last variant was just removed: its stock sum is stale
            // and no longer tracks anything real, so reset to zero rather
            // than leaving the product looking in stock by accident.
            $product->update(['quantity' => 0]);
        }

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('status', 'Product saved.');
    }

    public function toggleStatus(Product $product): RedirectResponse
    {
        $product->update(['is_active' => ! $product->is_active]);

        return back()->with('status', $product->is_active ? 'Product activated.' : 'Product disabled.');
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

            while (Product::query()->where('slug', $slug)->exists()) {
                $slug = $original.'-'.$i;
                $i++;
            }
        }

        return [
            'category_id' => $request->integer('category_id'),
            'slug' => $slug,
            'is_active' => $request->boolean('is_active'),
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

        return 'products/'.$name;
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
