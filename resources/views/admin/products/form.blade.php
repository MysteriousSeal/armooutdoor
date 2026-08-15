@extends('layouts.admin')

@section('title', $product->exists ? 'Edit product' : 'Add product')

@section('content')
    @php
        $priceValue = old('price', $product->exists ? number_format($product->price_cents / 100, 2, '.', '') : '');
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
                        <h3 class="order-panel-title">Images</h3>
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
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="sku">SKU</label>
                                <input type="text" id="sku" name="sku" class="form-control" value="{{ old('sku', $product->sku) }}" maxlength="64" placeholder="e.g. ARM-TENT-2P-GRN">
                                @error('sku') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="form-group">
                                <label for="gtin">GTIN</label>
                                <input type="text" id="gtin" name="gtin" class="form-control" value="{{ old('gtin', $product->gtin) }}" maxlength="14" placeholder="8, 12, 13, or 14 digits">
                                <p class="form-hint">Barcode number — UPC, EAN, or ISBN.</p>
                                @error('gtin') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
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
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/vendor/quill.snow.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/vendor/quill.js') }}"></script>
    <script src="{{ asset('js/admin-description-editor.js') }}" defer></script>
    <script src="{{ asset('js/admin-gallery-upload.js') }}" defer></script>
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
            var list = document.getElementById('variants-list');
            var addBtn = document.getElementById('variant-add');
            var template = document.getElementById('variant-row-template');
            var counter = list ? list.querySelectorAll('.variant-row').length : 0;

            if (!list || !addBtn || !template) {
                return;
            }

            function bindRemove(row) {
                row.querySelector('.variant-remove').addEventListener('click', function (event) {
                    var hasId = row.querySelector('input[name$="[id]"]');
                    if (!hasId) {
                        row.remove();
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
            });
        })();
    </script>
@endpush
