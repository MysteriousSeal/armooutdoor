@extends('layouts.admin')

@section('title', $product->exists ? 'Edit product' : 'Add product')

@section('content')
    @php
        $priceValue = old('price', $product->exists ? number_format($product->price_cents / 100, 2, '.', '') : '');
        $supplierPriceValue = old('supplier_price', $product->supplier_price_cents !== null ? number_format($product->supplier_price_cents / 100, 2, '.', '') : '');
        // Stockée en points de base, saisie en pourcent : 3000 -> 30 (et 3250 -> 32.5).
        $markupValue = old('markup_percent', $product->markup_basis_points !== null ? rtrim(rtrim(number_format($product->markup_basis_points / 100, 2, '.', ''), '0'), '.') : '');
        $hasMainImage = $product->exists && $product->image !== '';
    @endphp

    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker">Catalog</p>
                    <h2 class="admin-list-title">{{ $product->exists ? 'Edit product' : 'Add product' }}</h2>
                    <p class="admin-list-lede">
                        French name, a euro price, and photos.
                    </p>
                </div>
                <a href="{{ route('admin.products.index') }}" class="btn btn-secondary">Back to products</a>
            </div>
        </header>

        <form
            method="POST"
            action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}"
            enctype="multipart/form-data"
            class="admin-product-form"
        >
            @csrf
            @if ($product->exists)
                @method('PUT')
            @endif

            <div class="admin-order-create-grid">
                <div class="order-main">
                    <section class="order-panel">
                        <div class="admin-panel-head">
                            <h3 class="order-panel-title">Images</h3>
                            @if ($hasMainImage)
                                {{-- The cover as a JPEG, converted on the way out:
                                     the shop stores WebP, which no marketplace form
                                     or supplier wants. --}}
                                <a
                                    href="{{ route('admin.products.cover', $product) }}"
                                    class="btn btn-secondary btn-small"
                                    title="Download the cover, full size, as a JPEG"
                                >
                                    <svg viewBox="0 0 24 24" width="13" height="13" aria-hidden="true">
                                        <path d="M12 4v11m0 0-4-4m4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M5 17v2a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-2" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    Cover as JPG
                                </a>
                            @endif
                        </div>
                        <p class="form-hint">Drag tiles to reorder — the first is the cover shown across the shop. Drop more below.</p>

                        <div
                            class="upload-images-list"
                            id="gallery-images-list"
                            @if ($hasMainImage) data-has-main="1" @endif
                            aria-label="Image order"
                        >
                            @if ($hasMainImage)
                                <div
                                    class="additional-images-item is-cover"
                                    data-image-key="main"
                                    tabindex="0"
                                    role="button"
                                    aria-label="Cover image. Press arrow keys to reorder."
                                >
                                    <img src="{{ $product->imageUrl() }}" alt="" draggable="false">
                                    <span class="additional-images-badge">Cover</span>
                                    <button type="button" class="additional-images-remove" aria-label="Remove this image">&times;</button>
                                    <input type="checkbox" name="remove_main" value="1" id="remove-main-checkbox" hidden>
                                    <span class="upload-images-handle" aria-hidden="true" title="Drag to reorder">⋮⋮</span>
                                </div>
                            @endif
                            @foreach ($product->images as $galleryImage)
                                <div
                                    class="additional-images-item"
                                    data-image-key="{{ $galleryImage->id }}"
                                    tabindex="0"
                                    role="button"
                                    aria-label="Existing image. Press arrow keys to reorder."
                                >
                                    <img src="{{ $galleryImage->imageUrl() }}" alt="" draggable="false">
                                    <button type="button" class="additional-images-remove" aria-label="Remove this image">&times;</button>
                                    <input type="checkbox" name="remove_gallery_images[]" value="{{ $galleryImage->id }}" hidden>
                                    <span class="upload-images-handle" aria-hidden="true" title="Drag to reorder">⋮⋮</span>
                                </div>
                            @endforeach
                        </div>
                        <input type="hidden" name="gallery_order" id="gallery-order-input" value="">

                        <div class="drop-zone" id="gallery-drop-zone">
                            <p>Drag & drop images here, or <strong>click to browse</strong></p>
                            <p class="drop-zone-hint">JPEG, PNG, GIF, WebP · Max 4 MB each · Multiple files allowed</p>
                            <input
                                type="file"
                                id="gallery-files-picker"
                                accept="image/jpeg,image/png,image/gif,image/webp"
                                multiple
                                @unless($hasMainImage) required @endunless
                            >
                        </div>

                        <input type="file" name="image_file" id="image-file-input" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
                        <input type="file" name="gallery_images[]" id="gallery-images-input" accept="image/jpeg,image/png,image/gif,image/webp" multiple hidden>

                        @error('image_file') <p class="form-error">{{ $message }}</p> @enderror
                        @error('gallery_images') <p class="form-error">{{ $message }}</p> @enderror
                        @error('gallery_images.*') <p class="form-error">{{ $message }}</p> @enderror

                        <div class="form-group image-may-vary-field">
                            <label class="form-check">
                                <input type="checkbox" id="image_may_vary" name="image_may_vary" value="1" @checked(old('image_may_vary', $product->exists ? $product->image_may_vary : false))>
                                The final product visual can change from the pictures, according to supply.
                            </label>
                        </div>
                    </section>

                    <section class="order-panel">
                        <h3 class="order-panel-title">Product</h3>
                        <div class="form-group">
                            <label for="name">Name</label>
                            <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $product->name['fr'] ?? '') }}" required maxlength="120">
                            @error('name') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="form-group">
                            <label for="category_id">Category</label>
                            <select id="category_id" name="category_id" class="form-control" required>
                                <option value="">Choose a category</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected((string) old('category_id', $product->category_id) === (string) $category->id)>
                                        {{ $category->parent ? '— '.$category->localizedName() : ($category->name['fr'] ?? $category->localizedName()) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('category_id') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-check">
                                <input type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $product->exists ? $product->is_active : true))>
                                Active — visible in the shop
                            </label>
                            <p class="form-hint">Disabled products are hidden from the storefront but stay in the catalog.</p>
                        </div>
                        <div class="form-group">
                            <label class="form-check">
                                <input type="checkbox" id="age_restricted" name="age_restricted" value="1" @checked(old('age_restricted', $product->exists ? $product->age_restricted : false))>
                                Vente libre aux plus de 18 ans
                            </label>
                            <p class="form-hint">Shows an age-restriction notice on the product page.</p>
                        </div>
                    </section>

            @php
                $oldFilterLabels = old('filter_label', collect($product->filter_attributes ?? [])->pluck('label')->all());
                $oldFilterValues = old('filter_value', collect($product->filter_attributes ?? [])->pluck('value')->all());
            @endphp
                    <section class="order-panel">
                        <h3 class="order-panel-title">Filters</h3>
                        <p class="form-hint">Key/value attributes to filter products on in the shop — e.g. Calibre, Marque. Front-office filtering isn't built yet; this just records the data.</p>

                <div class="characteristics-list" id="filters-list">
                    @forelse ($oldFilterLabels as $i => $label)
                        <div class="characteristic-row">
                            <input type="text" name="filter_label[]" class="form-control" placeholder="Label (ex. Calibre)" value="{{ $label }}">
                            <input type="text" name="filter_value[]" class="form-control" placeholder="Valeur" value="{{ $oldFilterValues[$i] ?? '' }}">
                            <button type="button" class="btn btn-sm btn-secondary characteristic-remove" aria-label="Remove filter">&times;</button>
                        </div>
                    @empty
                        <div class="characteristic-row">
                            <input type="text" name="filter_label[]" class="form-control" placeholder="Label (ex. Calibre)" value="">
                            <input type="text" name="filter_value[]" class="form-control" placeholder="Valeur" value="">
                            <button type="button" class="btn btn-sm btn-secondary characteristic-remove" aria-label="Remove filter">&times;</button>
                        </div>
                    @endforelse
                </div>

                <button type="button" class="btn btn-sm btn-secondary" id="filter-add">Add filter</button>

                <template id="filter-row-template">
                    <div class="characteristic-row">
                        <input type="text" name="filter_label[]" class="form-control" placeholder="Label (ex. Calibre)">
                        <input type="text" name="filter_value[]" class="form-control" placeholder="Valeur">
                        <button type="button" class="btn btn-sm btn-secondary characteristic-remove" aria-label="Remove filter">&times;</button>
                    </div>
                </template>

                        @error('filter_label.*') <p class="form-error">{{ $message }}</p> @enderror
                        @error('filter_value.*') <p class="form-error">{{ $message }}</p> @enderror
                    </section>

            @php
                $oldLabels = old('characteristic_label', collect($product->characteristics ?? [])->pluck('label')->all());
                $oldValues = old('characteristic_value', collect($product->characteristics ?? [])->pluck('value')->all());
            @endphp
                    <section class="order-panel">
                        <h3 class="order-panel-title">Characteristics</h3>
                        <p class="form-hint">Key/value specs shown on the product page — e.g. Type, Diamètre, Couleur.</p>

                <div class="characteristics-list" id="characteristics-list">
                    @forelse ($oldLabels as $i => $label)
                        <div class="characteristic-row">
                            <input type="text" name="characteristic_label[]" class="form-control" placeholder="Label (ex. Type)" value="{{ $label }}">
                            <input type="text" name="characteristic_value[]" class="form-control" placeholder="Valeur" value="{{ $oldValues[$i] ?? '' }}">
                            <button type="button" class="btn btn-sm btn-secondary characteristic-remove" aria-label="Remove characteristic">&times;</button>
                        </div>
                    @empty
                        <div class="characteristic-row">
                            <input type="text" name="characteristic_label[]" class="form-control" placeholder="Label (ex. Type)" value="">
                            <input type="text" name="characteristic_value[]" class="form-control" placeholder="Valeur" value="">
                            <button type="button" class="btn btn-sm btn-secondary characteristic-remove" aria-label="Remove characteristic">&times;</button>
                        </div>
                    @endforelse
                </div>

                <button type="button" class="btn btn-sm btn-secondary" id="characteristic-add">Add characteristic</button>

                <template id="characteristic-row-template">
                    <div class="characteristic-row">
                        <input type="text" name="characteristic_label[]" class="form-control" placeholder="Label (ex. Type)">
                        <input type="text" name="characteristic_value[]" class="form-control" placeholder="Valeur">
                        <button type="button" class="btn btn-sm btn-secondary characteristic-remove" aria-label="Remove characteristic">&times;</button>
                    </div>
                </template>

                        @error('characteristic_label.*') <p class="form-error">{{ $message }}</p> @enderror
                        @error('characteristic_value.*') <p class="form-error">{{ $message }}</p> @enderror
                    </section>
                </div>

                <div class="order-main">
                    <section class="order-panel">
                        <h3 class="order-panel-title">Description</h3>
                        <div class="form-group description-editor-group">
                            <label for="description" class="sr-only">Description</label>
                            <div class="description-editor" aria-label="Description editor"></div>
                            <textarea id="description" name="description" class="description-editor-source" hidden placeholder="Rédigez une description…">{{ old('description', $product->description['fr'] ?? '') }}</textarea>
                            <p class="form-hint">Éditeur de texte enrichi — collez depuis d'autres sites pour garder le gras, les liens et les listes. Le HTML est nettoyé.</p>
                            @error('description') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </section>

                    <section class="order-panel">
                        <h3 class="order-panel-title">Price and stock</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="price">Price (EUR)</label>
                                <input type="number" id="price" name="price" class="form-control" value="{{ $priceValue }}" min="0" max="99999.99" step="0.01" required>
                                @error('price') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="form-group">
                                <label for="quantity">Available quantity</label>
                                <input
                                    type="number"
                                    id="quantity"
                                    name="quantity"
                                    class="form-control"
                                    value="{{ old('quantity', $product->exists ? $product->quantity : 0) }}"
                                    min="0"
                                    max="99999"
                                    step="1"
                                    required
                                    @if ($product->exists && $product->hasVariants()) readonly @endif
                                >
                                @if ($product->exists && $product->hasVariants())
                                    <p class="form-hint">Computed automatically from the variants below — edit their quantities instead.</p>
                                @else
                                    <p class="form-hint">Units you can sell. 0 means out of stock.</p>
                                @endif
                                @error('quantity') <p class="form-error">{{ $message }}</p> @enderror

                                {{-- Ce qui est déjà commandé : sans cette ligne, on
                                     recommande un article dont le réassort est en route. --}}
                                @if ($inboundStock['quantity'] > 0)
                                        <p class="product-inbound">
                                            <span class="product-inbound-arrow" aria-hidden="true">&#8627;</span>
                                            <strong>{{ number_format($inboundStock['quantity']) }}</strong>
                                            on order
                                            @foreach ($inboundStock['orders'] as $purchaseOrder)
                                                <a href="{{ route('admin.purchase-orders.show', $purchaseOrder) }}" class="product-inbound-ref">{{ $purchaseOrder->number }}</a>
                                            @endforeach
                                        </p>
                                @endif

                                @if ($product->exists)
                                    <p class="form-hint">
                                        <a href="{{ route('admin.products.stock-history', $product) }}">Stock history</a>
                                        — every recorded change to this product's stock.
                                    </p>
                                @endif
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="sku">SKU</label>
                                <input type="text" id="sku" name="sku" class="form-control" value="{{ old('sku', $product->hasVariants() ? null : $product->sku) }}" maxlength="64" placeholder="e.g. ARM-TENT-2P-GRN" @if ($product->exists && $product->hasVariants()) disabled @endif>
                                @if ($product->exists && $product->hasVariants())
                                    <p class="form-hint">Set per variant below instead.</p>
                                @endif
                                @error('sku') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="form-group">
                                <label for="gtin">GTIN</label>
                                <input type="text" id="gtin" name="gtin" class="form-control" value="{{ old('gtin', $product->hasVariants() ? null : $product->gtin) }}" maxlength="14" placeholder="8, 12, 13, or 14 digits" @if ($product->exists && $product->hasVariants()) disabled @endif>
                                @if ($product->exists && $product->hasVariants())
                                    <p class="form-hint">Set per variant below instead.</p>
                                @else
                                    <p class="form-hint">Barcode number — UPC, EAN, or ISBN.</p>
                                @endif
                                @error('gtin') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="weight_grams">Weight (g)</label>
                            <input type="number" id="weight_grams" name="weight_grams" class="form-control" value="{{ old('weight_grams', $product->weight_grams) }}" min="0" max="99999" step="1" placeholder="e.g. 850">
                            @error('weight_grams') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </section>

                    @php
                        $allCarrierIds = $carriers->pluck('id')->all();
                        $selectedCarrierIds = old('carrier_ids', $product->exists ? ($product->carrier_ids ?? $allCarrierIds) : $allCarrierIds);
                    @endphp

                    <section class="order-panel">
                        <h3 class="order-panel-title">Carriers</h3>
                        <p class="form-hint">
                            Which carriers can ship this product. Uncheck a carrier here to hide it at checkout for any
                            cart that includes this product. Leave everything checked if there's no restriction.
                        </p>

                        <div class="admin-check-list">
                            @foreach ($carriers as $carrier)
                                <label class="form-check">
                                    <input
                                        type="checkbox"
                                        name="carrier_ids[]"
                                        value="{{ $carrier->id }}"
                                        @checked(in_array($carrier->id, $selectedCarrierIds))
                                    >
                                    {{ $carrier->localizedName() }}
                                    <span class="admin-check-list-meta">— {{ $carrier->formattedStartingPrice() }}, {{ $carrier->method->value }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('carrier_ids') <p class="form-error">{{ $message }}</p> @enderror
                    </section>

                    <section class="order-panel">
                        <h3 class="order-panel-title">Supplier</h3>
                        @if ($product->exists && $product->hasVariants())
                            <p class="form-hint">Set per variant below instead.</p>
                        @else
                            <p class="form-hint">Which supplier this product is ordered from, if any.</p>
                        @endif

                        @php
                            $disableSupplierFields = $product->exists && $product->hasVariants();
                        @endphp

                        <div class="form-group">
                            <label for="supplier_id" class="sr-only">Supplier</label>
                            <select id="supplier_id" name="supplier_id" class="form-control" @if ($disableSupplierFields) disabled @endif>
                                <option value="">No supplier</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" @selected(! $disableSupplierFields && old('supplier_id', $product->supplier_id) == $supplier->id)>
                                        {{ $supplier->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('supplier_id') <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-check">
                                <input type="checkbox" id="available_at_supplier" name="available_at_supplier" value="1" @checked(! $disableSupplierFields && old('available_at_supplier', $product->exists ? $product->available_at_supplier : true)) @if ($disableSupplierFields) disabled @endif>
                                Available at supplier
                            </label>
                            <p class="form-hint">Whether the supplier currently has this item in stock for reordering.</p>
                        </div>

                        <div class="form-group">
                            <label for="supplier_reference">Supplier reference</label>
                            <input
                                type="text"
                                id="supplier_reference"
                                name="supplier_reference"
                                class="form-control"
                                value="{{ old('supplier_reference', $disableSupplierFields ? null : $product->supplier_reference) }}"
                                maxlength="120"
                                placeholder="e.g. SA-BB-020-1000-BIO"
                                @if ($disableSupplierFields) disabled @endif
                            >
                            <p class="form-hint">The supplier's own product code, for reordering.</p>
                            @error('supplier_reference') <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="supplier_price">Supplier price, excl. tax (EUR)</label>
                                <input
                                    type="number"
                                    id="supplier_price"
                                    name="supplier_price"
                                    class="form-control"
                                    value="{{ $disableSupplierFields ? '' : $supplierPriceValue }}"
                                    min="0"
                                    max="99999.99"
                                    step="0.01"
                                    placeholder="e.g. 49.90"
                                    @if ($disableSupplierFields) disabled @endif
                                >
                                <p class="form-hint">What this costs you before VAT. Never shown to customers.</p>
                                @error('supplier_price') <p class="form-error">{{ $message }}</p> @enderror
                                @if ($product->exists && $averagePurchaseCostInclVatCents !== null)
                                    <a href="{{ route('admin.products.average-cost', $product) }}" class="product-avg-cost" title="See the breakdown">
                                        <span class="product-avg-cost-label">Average paid, incl. VAT</span>
                                        <span class="product-avg-cost-value">{{ format_euros($averagePurchaseCostInclVatCents) }}</span>
                                        <span class="product-avg-cost-note">from {{ number_format($receivedPurchaseUnits) }} unit{{ $receivedPurchaseUnits === 1 ? '' : 's' }} received</span>
                                    </a>
                                @endif
                            </div>

                            <div class="form-group">
                                <label for="markup_percent">Markup (%)</label>
                                <input
                                    type="number"
                                    id="markup_percent"
                                    name="markup_percent"
                                    class="form-control"
                                    value="{{ $disableSupplierFields ? '' : $markupValue }}"
                                    min="0"
                                    max="1000"
                                    step="0.01"
                                    placeholder="e.g. 30"
                                    @if ($disableSupplierFields) disabled @endif
                                >
                                <p class="form-hint">The margin you want on this product, on top of the supplier price.</p>
                                @error('markup_percent') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="supplier_product_url">Link to product on supplier website</label>
                            <input
                                type="url"
                                id="supplier_product_url"
                                name="supplier_product_url"
                                class="form-control"
                                value="{{ old('supplier_product_url', $disableSupplierFields ? null : $product->supplier_product_url) }}"
                                maxlength="2048"
                                placeholder="https://…"
                                @if ($disableSupplierFields) disabled @endif
                            >
                            @if (! $disableSupplierFields && $product->supplier_product_url)
                                <p class="form-hint">
                                    <a href="{{ $product->supplier_product_url }}" target="_blank" rel="noopener noreferrer">Open on supplier website ↗</a>
                                </p>
                            @endif
                            @error('supplier_product_url') <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        {{-- Recalculé en direct par le script ; la valeur rendue
                             ici sert d'état de départ et de repli sans JS. --}}
                        <p
                            class="supplier-recommended"
                            id="supplier-recommended"
                            data-vat-basis-points="{{ App\Models\Product::VAT_RATE_BASIS_POINTS }}"
                            data-max-price-cents="{{ App\Models\Product::MAX_PRICE_CENTS }}"
                            @if ($disableSupplierFields || $product->recommendedPriceCents() === null) hidden @endif
                        >
                            <span class="supplier-recommended-label">Recommended price</span>
                            <span class="supplier-recommended-value" id="supplier-recommended-value">
                                {{ $product->recommendedPriceCents() !== null ? format_euros($product->recommendedPriceCents()) : '' }}
                            </span>
                            <button
                                type="button"
                                class="btn btn-sm btn-secondary supplier-recommended-apply"
                                id="supplier-recommended-apply"
                                hidden
                            >Apply to price</button>
                            <span class="supplier-recommended-note" id="supplier-recommended-note">Purchase price + 20% VAT + markup, rounded up to the next ,49 or ,99</span>
                        </p>

                        @if ($product->exists && ! $disableSupplierFields)
                            {{-- Sans JS, le bouton reste caché : le formulaire
                                 complet en bas de page enregistre déjà tout. --}}
                            <div
                                class="supplier-save-row"
                                id="supplier-save-row"
                                data-endpoint="{{ route('admin.products.supplier', $product) }}"
                                hidden
                            >
                                <button
                                    type="button"
                                    class="btn btn-sm btn-secondary"
                                    data-modal-open="supplier-save-modal"
                                    id="supplier-save-btn"
                                >Save supplier details</button>
                                <p class="form-hint">Saves this panel on its own, without submitting the rest of the form.</p>
                            </div>
                        @endif
                    </section>
                </div>
            </div>

            @php
                $oldVariants = old('variants', $product->exists
                    ? $product->variants->map(fn ($variant) => [
                        'id' => $variant->id,
                        'attributes_text' => collect($variant->attribute_values ?? [])->map(fn ($attribute) => $attribute['label'].': '.$attribute['value'])->implode(', '),
                        'sku' => $variant->sku,
                        'gtin' => $variant->gtin,
                        'price' => $variant->price_cents !== null ? number_format($variant->price_cents / 100, 2, '.', '') : '',
                        'quantity' => $variant->quantity,
                        'is_active' => $variant->is_active,
                        'image_url' => $variant->image ? $variant->imageUrl() : null,
                        'supplier_id' => $variant->supplier_id,
                        'available_at_supplier' => $variant->available_at_supplier,
                        'supplier_reference' => $variant->supplier_reference,
                        'supplier_product_url' => $variant->supplier_product_url,
                    ])->values()->all()
                    : []);
            @endphp

            <section class="order-panel admin-product-variants">
                <div class="variant-section-head">
                    <div>
                        <h3 class="order-panel-title">Variants</h3>
                        <p class="form-hint">
                            One card per option. Attributes like <code>Groupe: A+</code> or <code>Taille: L, Couleur: Rouge</code>.
                            Leave price blank to use the product price.
                        </p>
                    </div>
                    <button type="button" class="btn btn-sm btn-secondary" id="variant-add">Add variant</button>
                </div>

                <div id="variants-list">
                    @forelse ($oldVariants as $index => $variant)
                        @php($variantTitle = filled($variant['attributes_text'] ?? null) ? $variant['attributes_text'] : 'New variant')
                        <div class="variant-row">
                            @if (! empty($variant['id']))
                                <input type="hidden" name="variants[{{ $index }}][id]" value="{{ $variant['id'] }}">
                            @endif
                            <input type="hidden" name="variants[{{ $index }}][_delete]" value="" class="variant-delete-flag">
                            <header class="variant-card-head">
                                <p class="variant-card-title">{{ $variantTitle }}</p>
                                <label class="form-check">
                                    <input type="checkbox" name="variants[{{ $index }}][is_active]" value="1" @checked($variant['is_active'] ?? true)>
                                    Active
                                </label>
                                <button type="button" class="btn btn-sm btn-secondary variant-remove">Remove</button>
                            </header>
                            <div class="variant-card-body">
                                <div class="variant-card-media">
                                    <span class="variant-card-preview">
                                        @if (! empty($variant['image_url']))
                                            <img src="{{ $variant['image_url'] }}" alt="">
                                        @endif
                                    </span>
                                    @if (! empty($variant['image_url']))
                                        <label class="form-check">
                                            <input type="checkbox" name="variants[{{ $index }}][remove_image]" value="1">
                                            Remove photo
                                        </label>
                                    @endif
                                    <label class="btn btn-sm btn-secondary variant-card-upload">
                                        {{ ! empty($variant['image_url']) ? 'Replace photo' : 'Add photo' }}
                                        <input type="file" name="variant_images[{{ $index }}]" accept="image/jpeg,image/png,image/gif,image/webp">
                                    </label>
                                </div>
                                <div class="variant-card-fields">
                                    <div class="form-group">
                                        <label>Attributes</label>
                                        <input type="text" name="variants[{{ $index }}][attributes_text]" class="form-control variant-attributes-input" placeholder="Groupe: A+" value="{{ $variant['attributes_text'] ?? '' }}">
                                        @error("variants.{$index}.attributes_text") <p class="form-error">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>SKU</label>
                                            <input type="text" name="variants[{{ $index }}][sku]" class="form-control" maxlength="64" value="{{ $variant['sku'] ?? '' }}">
                                            @error("variants.{$index}.sku") <p class="form-error">{{ $message }}</p> @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>GTIN</label>
                                            <input type="text" name="variants[{{ $index }}][gtin]" class="form-control" maxlength="14" value="{{ $variant['gtin'] ?? '' }}">
                                            @error("variants.{$index}.gtin") <p class="form-error">{{ $message }}</p> @enderror
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Price (€)</label>
                                            <input type="number" name="variants[{{ $index }}][price]" class="form-control" min="0" max="99999.99" step="0.01" placeholder="Same as product" value="{{ $variant['price'] ?? '' }}">
                                            @error("variants.{$index}.price") <p class="form-error">{{ $message }}</p> @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Quantity</label>
                                            <input type="number" name="variants[{{ $index }}][quantity]" class="form-control" min="0" max="99999" step="1" value="{{ $variant['quantity'] ?? 0 }}">
                                            @error("variants.{$index}.quantity") <p class="form-error">{{ $message }}</p> @enderror
                                        </div>
                                    </div>
                                    <div class="variant-supplier-fields">
                                        <p class="form-hint">Supplier — independent from the main product's supplier.</p>
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label>Supplier</label>
                                                <select name="variants[{{ $index }}][supplier_id]" class="form-control">
                                                    <option value="">No supplier</option>
                                                    @foreach ($suppliers as $supplier)
                                                        <option value="{{ $supplier->id }}" @selected(($variant['supplier_id'] ?? null) == $supplier->id)>
                                                            {{ $supplier->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label class="form-check">
                                                    <input type="checkbox" name="variants[{{ $index }}][available_at_supplier]" value="1" @checked($variant['available_at_supplier'] ?? true)>
                                                    Available at supplier
                                                </label>
                                            </div>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label>Supplier reference</label>
                                                <input type="text" name="variants[{{ $index }}][supplier_reference]" class="form-control" maxlength="120" value="{{ $variant['supplier_reference'] ?? '' }}">
                                            </div>
                                            <div class="form-group">
                                                <label>Supplier product URL</label>
                                                <input type="url" name="variants[{{ $index }}][supplier_product_url]" class="form-control" maxlength="2048" value="{{ $variant['supplier_product_url'] ?? '' }}">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="variant-empty">No variants yet. Add one if this product has sizes, colors or other options.</p>
                    @endforelse
                </div>

                <template id="variant-row-template">
                    <div class="variant-row">
                        <input type="hidden" name="variants[__INDEX__][_delete]" value="" class="variant-delete-flag">
                        <header class="variant-card-head">
                            <p class="variant-card-title">New variant</p>
                            <label class="form-check">
                                <input type="checkbox" name="variants[__INDEX__][is_active]" value="1" checked>
                                Active
                            </label>
                            <button type="button" class="btn btn-sm btn-secondary variant-remove">Remove</button>
                        </header>
                        <div class="variant-card-body">
                            <div class="variant-card-media">
                                <span class="variant-card-preview"></span>
                                <label class="btn btn-sm btn-secondary variant-card-upload">
                                    Add photo
                                    <input type="file" name="variant_images[__INDEX__]" accept="image/jpeg,image/png,image/gif,image/webp">
                                </label>
                            </div>
                            <div class="variant-card-fields">
                                <div class="form-group">
                                    <label>Attributes</label>
                                    <input type="text" name="variants[__INDEX__][attributes_text]" class="form-control variant-attributes-input" placeholder="Groupe: A+">
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>SKU</label>
                                        <input type="text" name="variants[__INDEX__][sku]" class="form-control" maxlength="64">
                                    </div>
                                    <div class="form-group">
                                        <label>GTIN</label>
                                        <input type="text" name="variants[__INDEX__][gtin]" class="form-control" maxlength="14">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Price (€)</label>
                                        <input type="number" name="variants[__INDEX__][price]" class="form-control" min="0" max="99999.99" step="0.01" placeholder="Same as product">
                                    </div>
                                    <div class="form-group">
                                        <label>Quantity</label>
                                        <input type="number" name="variants[__INDEX__][quantity]" class="form-control" min="0" max="99999" step="1" value="0">
                                    </div>
                                </div>
                                <div class="variant-supplier-fields">
                                    <p class="form-hint">Supplier — independent from the main product's supplier.</p>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Supplier</label>
                                            <select name="variants[__INDEX__][supplier_id]" class="form-control">
                                                <option value="">No supplier</option>
                                                @foreach ($suppliers as $supplier)
                                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-check">
                                                <input type="checkbox" name="variants[__INDEX__][available_at_supplier]" value="1" checked>
                                                Available at supplier
                                            </label>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Supplier reference</label>
                                            <input type="text" name="variants[__INDEX__][supplier_reference]" class="form-control" maxlength="120">
                                        </div>
                                        <div class="form-group">
                                            <label>Supplier product URL</label>
                                            <input type="url" name="variants[__INDEX__][supplier_product_url]" class="form-control" maxlength="2048">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </section>

            <div class="order-panel admin-order-create-actions">
                <a href="{{ route('admin.products.index') }}" class="btn btn-secondary">Cancel</a>
                @if ($product->exists)
                    <a href="{{ localized_route('products.show', ['product' => $product->slug], 'fr') }}" class="btn btn-secondary" target="_blank" rel="noopener noreferrer">View in shop</a>
                @endif
                <button type="submit" class="btn btn-primary">{{ $product->exists ? 'Save changes' : 'Create product' }}</button>
            </div>
        </form>

        {{-- Hors du <form> : un dialog imbriqué se soumettrait avec lui. --}}
        @unless ($product->exists && $product->hasVariants())
            <dialog id="apply-price-modal" class="modal" aria-labelledby="apply-price-title">
                <h3 class="modal-title" id="apply-price-title">Replace the current price?</h3>
                <p class="modal-body" id="apply-price-body"></p>
                <p class="modal-body">Only the Price field is filled in. Nothing is saved until you press Save changes.</p>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                    <button type="button" class="btn btn-primary" id="apply-price-confirm">Replace the price</button>
                </div>
            </dialog>
        @endunless

        @if ($product->exists && ! $product->hasVariants())
            <dialog id="supplier-save-modal" class="modal" aria-labelledby="supplier-save-title">
                <p class="modal-kicker">{{ $product->localizedName() }}</p>
                <h3 class="modal-title" id="supplier-save-title">Save supplier details?</h3>
                <p class="modal-body">
                    Only the supplier panel is written: the supplier, its reference and link,
                    the purchase price and the markup. Nothing else on this page is touched,
                    and unsaved changes elsewhere stay unsaved.
                </p>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                    <button type="button" class="btn btn-primary" id="supplier-save-confirm">Save supplier details</button>
                </div>
            </dialog>
        @endif
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/vendor/quill.snow.css') }}">
@endpush


@push('scripts')
    <script src="{{ asset('js/vendor/quill.js') }}"></script>
    <script src="{{ asset('js/admin-description-editor.js') }}" defer></script>
    <script src="{{ asset('js/admin-gallery-upload.js') }}" defer></script>
    <script src="{{ asset('js/admin-product-supplier-save.js') }}" defer></script>
    <script src="{{ asset('js/admin-product-recommended-price.js') }}" defer></script>
    <script>
        (function () {
            var list = document.getElementById('characteristics-list');
            var addBtn = document.getElementById('characteristic-add');
            var template = document.getElementById('characteristic-row-template');

            if (!list || !addBtn || !template) {
                return;
            }

            function bindRemove(row) {
                row.querySelector('.characteristic-remove').addEventListener('click', function () {
                    row.remove();
                });
            }

            list.querySelectorAll('.characteristic-row').forEach(bindRemove);

            addBtn.addEventListener('click', function () {
                var row = template.content.firstElementChild.cloneNode(true);
                bindRemove(row);
                list.appendChild(row);
                row.querySelector('input').focus();
            });
        })();

        (function () {
            var list = document.getElementById('filters-list');
            var addBtn = document.getElementById('filter-add');
            var template = document.getElementById('filter-row-template');

            if (!list || !addBtn || !template) {
                return;
            }

            function bindRemove(row) {
                row.querySelector('.characteristic-remove').addEventListener('click', function () {
                    row.remove();
                });
            }

            list.querySelectorAll('.characteristic-row').forEach(bindRemove);

            addBtn.addEventListener('click', function () {
                var row = template.content.firstElementChild.cloneNode(true);
                bindRemove(row);
                list.appendChild(row);
                row.querySelector('input').focus();
            });
        })();

        (function () {
            var list = document.getElementById('variants-list');
            var addBtn = document.getElementById('variant-add');
            var template = document.getElementById('variant-row-template');
            var counter = list ? list.querySelectorAll('.variant-row').length : 0;
            var mainFieldIds = ['sku', 'gtin', 'supplier_id', 'available_at_supplier', 'supplier_reference', 'supplier_product_url'];

            if (!list || !addBtn || !template) {
                return;
            }

            function syncMainProductFieldLocks() {
                var remaining = Array.prototype.filter.call(
                    list.querySelectorAll('.variant-row'),
                    function (row) { return !row.classList.contains('is-marked-for-delete'); }
                ).length;

                mainFieldIds.forEach(function (id) {
                    var field = document.getElementById(id);
                    if (field) {
                        field.disabled = remaining > 0;
                    }
                });

                var quantityField = document.getElementById('quantity');
                if (quantityField) {
                    quantityField.readOnly = remaining > 0;
                }
            }

            function bindRemove(row) {
                row.querySelector('.variant-remove').addEventListener('click', function (event) {
                    var hasId = row.querySelector('input[name$="[id]"]');
                    if (!hasId) {
                        row.remove();
                        syncMainProductFieldLocks();
                        return;
                    }

                    var flag = row.querySelector('.variant-delete-flag');
                    var marked = flag.value === '1';
                    flag.value = marked ? '' : '1';
                    row.classList.toggle('is-marked-for-delete', !marked);
                    row.querySelectorAll('input:not(.variant-delete-flag):not([name$="[id]"]), select').forEach(function (field) {
                        field.disabled = !marked;
                    });
                    event.target.textContent = marked ? 'Remove' : 'Undo';
                    syncMainProductFieldLocks();
                });
            }

            function bindTitle(row) {
                var input = row.querySelector('.variant-attributes-input');
                var title = row.querySelector('.variant-card-title');
                if (!input || !title) {
                    return;
                }
                input.addEventListener('input', function () {
                    title.textContent = input.value.trim() || 'New variant';
                });
            }

            function bindPreview(row) {
                var file = row.querySelector('input[type="file"]');
                var preview = row.querySelector('.variant-card-preview');
                if (!file || !preview) {
                    return;
                }
                file.addEventListener('change', function () {
                    var chosen = file.files && file.files[0];
                    if (!chosen) {
                        return;
                    }
                    var img = preview.querySelector('img') || document.createElement('img');
                    img.alt = '';
                    img.src = URL.createObjectURL(chosen);
                    preview.appendChild(img);
                });
            }

            function bindRow(row) {
                bindRemove(row);
                bindTitle(row);
                bindPreview(row);
            }

            list.querySelectorAll('.variant-row').forEach(bindRow);
            syncMainProductFieldLocks();

            addBtn.addEventListener('click', function () {
                var empty = list.querySelector('.variant-empty');
                if (empty) {
                    empty.remove();
                }
                var html = template.innerHTML.replaceAll('__INDEX__', String(counter));
                list.insertAdjacentHTML('beforeend', html);
                var row = list.lastElementChild;
                bindRow(row);
                var focus = row.querySelector('.variant-attributes-input');
                if (focus) {
                    focus.focus();
                }
                counter++;
                syncMainProductFieldLocks();
            });
        })();
    </script>
@endpush
