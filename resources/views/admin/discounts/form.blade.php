@extends('layouts.admin')

@section('title', $discount->exists ? 'Edit discount' : 'Add discount')

@section('content')
    @php
        $valueDefault = $discount->exists
            ? ($discount->type === 'percentage' ? $discount->value : number_format($discount->value / 100, 2, '.', ''))
            : '';
        $selectedType = old('type', $discount->type ?? 'percentage');
        $selectedValue = old('value', $valueDefault);
        $productOptions = $products->map(fn ($product) => [
            'id' => $product->id,
            'label' => $product->localizedName(),
            'name' => $product->localizedName(),
            'sku' => $product->sku ?: '',
            'meta' => $product->formattedOriginalPrice().' · '.$product->quantity.' in stock',
            'image' => $product->image ? $product->imageUrl() : '',
            'search' => $product->localizedName().' '.($product->sku ?? ''),
            'price_cents' => $product->price_cents,
        ]);
        $selectedProductId = old('product_id', $discount->product_id);
        $previewProduct = $products->firstWhere('id', (int) $selectedProductId);
        $selectedProductLabel = $previewProduct?->localizedName() ?? '';
        $previewCents = null;
        if ($previewProduct && $selectedValue !== '' && is_numeric($selectedValue)) {
            $previewCents = $selectedType === 'percentage'
                ? $previewProduct->price_cents - (int) round($previewProduct->price_cents * ((float) $selectedValue) / 100)
                : $previewProduct->price_cents - (int) round(((float) $selectedValue) * 100);
            $previewCents = max(0, $previewCents);
        }
        $startsAt = old('starts_at', $discount->starts_at?->format('Y-m-d\TH:i'));
        $endsAt = old('ends_at', $discount->ends_at?->format('Y-m-d\TH:i'));
    @endphp

    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker">
                        <a href="{{ route('admin.discounts.index') }}">Discounts</a>
                    </p>
                    <h2 class="admin-list-title">{{ $discount->exists ? 'Edit discount' : 'Add discount' }}</h2>
                    <p class="admin-list-lede">Pick a product, then a percentage or a fixed amount off. Leave the dates blank to start now and run indefinitely.</p>
                </div>
                <a href="{{ route('admin.discounts.index') }}" class="btn btn-secondary">Back to discounts</a>
            </div>
        </header>

        <form
            method="POST"
            action="{{ $discount->exists ? route('admin.discounts.update', $discount) : route('admin.discounts.store') }}"
            class="admin-discount-form"
            id="discount-form"
        >
            @csrf
            @if ($discount->exists)
                @method('PUT')
            @endif

            <div class="admin-order-create-grid">
                <div class="order-main">
                    <section class="order-panel">
                        <h3 class="order-panel-title">Product</h3>
                        <div class="form-group">
                            <label for="product_id">Product</label>
                            <div class="search-select" data-search-select data-source="products">
                                <input type="hidden" name="product_id" id="discount-product-id" value="{{ $selectedProductId }}">
                                <input
                                    type="text"
                                    id="product_id"
                                    class="form-control search-select-input"
                                    placeholder="Search by name or SKU…"
                                    value="{{ $selectedProductLabel }}"
                                    autocomplete="off"
                                    spellcheck="false"
                                >
                                <ul class="search-select-list" hidden></ul>
                            </div>
                            <p class="form-hint">Only products that do not already have a discount are listed.</p>
                            @error('product_id') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </section>

                    <section class="order-panel">
                        <h3 class="order-panel-title">Offer</h3>
                        <div class="form-group">
                            <label>Type</label>
                            <div class="admin-choice-row">
                                <label class="admin-choice">
                                    <input type="radio" name="type" value="percentage" @checked($selectedType === 'percentage')>
                                    <span class="discount-type-copy">
                                        <span class="admin-table-strong">Percentage</span>
                                        <span class="admin-table-sub">e.g. 20% off the price</span>
                                    </span>
                                </label>
                                <label class="admin-choice">
                                    <input type="radio" name="type" value="fixed" @checked($selectedType === 'fixed')>
                                    <span class="discount-type-copy">
                                        <span class="admin-table-strong">Fixed amount</span>
                                        <span class="admin-table-sub">e.g. 5,00 € off the price</span>
                                    </span>
                                </label>
                            </div>
                            @error('type') <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="form-group">
                            <label for="value">Value</label>
                            <div class="discount-value-field">
                                <input
                                    type="number"
                                    id="value"
                                    name="value"
                                    class="form-control"
                                    value="{{ $selectedValue }}"
                                    min="0.01"
                                    step="0.01"
                                    required
                                >
                                <span class="discount-value-suffix" id="discount-value-suffix">{{ $selectedType === 'fixed' ? '€' : '%' }}</span>
                            </div>
                            <p class="form-hint" id="discount-value-hint">
                                {{ $selectedType === 'fixed' ? 'Euro amount to subtract, for example 5.00.' : 'Percentage to subtract, for example 20.' }}
                            </p>
                            @error('value') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </section>

                    <section class="order-panel">
                        <h3 class="order-panel-title">Schedule</h3>
                        <p class="form-hint">Optional. An empty start begins immediately; an empty end never expires.</p>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="starts_at">Starts</label>
                                <input type="datetime-local" id="starts_at" name="starts_at" class="form-control" value="{{ $startsAt }}">
                                @error('starts_at') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="form-group">
                                <label for="ends_at">Ends</label>
                                <input type="datetime-local" id="ends_at" name="ends_at" class="form-control" value="{{ $endsAt }}">
                                @error('ends_at') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </section>
                </div>

                <aside>
                    <section class="order-panel admin-discount-preview" id="discount-preview">
                        <h3 class="order-panel-title">Preview</h3>

                        <div class="admin-discount-preview-empty" id="discount-preview-empty" @if ($previewProduct) hidden @endif>
                            <p>Choose a product to see the sale price.</p>
                        </div>

                        <div class="admin-discount-preview-body" id="discount-preview-body" @unless ($previewProduct) hidden @endunless>
                            <div class="admin-discount-preview-media" id="discount-preview-media">
                                @if ($previewProduct?->image)
                                    <img src="{{ $previewProduct->imageUrl() }}" alt="">
                                @endif
                            </div>
                            <p class="admin-discount-preview-name" id="discount-preview-name">
                                {{ $previewProduct?->localizedName() }}
                            </p>
                            <p class="admin-discount-preview-sku" id="discount-preview-sku">
                                {{ $previewProduct?->sku ? 'SKU '.$previewProduct->sku : 'No SKU' }}
                            </p>
                            <p class="admin-discount-preview-prices">
                                <span class="card-price-original" id="discount-preview-original">
                                    {{ $previewProduct ? $previewProduct->formattedOriginalPrice() : '' }}
                                </span>
                                <span id="discount-preview-sale">
                                    {{ $previewCents !== null ? format_euros($previewCents) : '—' }}
                                </span>
                            </p>
                            <div class="admin-discount-preview-meta">
                                <span class="order-discount-badge" id="discount-preview-badge">
                                    {{ $previewCents !== null && $previewProduct
                                        ? ($selectedType === 'percentage' ? '-'.(int) round((float) $selectedValue).'%' : '-'.format_euros((int) round(((float) $selectedValue) * 100)))
                                        : '—' }}
                                </span>
                                <p class="admin-discount-preview-window" id="discount-preview-window"></p>
                            </div>
                        </div>
                    </section>

                    <div class="order-panel admin-order-create-actions">
                        <a href="{{ route('admin.discounts.index') }}" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">{{ $discount->exists ? 'Save changes' : 'Create discount' }}</button>
                    </div>
                </aside>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/admin-search-select.js') }}"></script>
    <script>
        AdminSearchSelect.catalogs.products = @json($productOptions);
        AdminSearchSelect.mountAll();

        (function () {
            var products = AdminSearchSelect.catalogs.products || [];
            var productInput = document.getElementById('discount-product-id');
            var valueInput = document.getElementById('value');
            var startsInput = document.getElementById('starts_at');
            var endsInput = document.getElementById('ends_at');
            var suffix = document.getElementById('discount-value-suffix');
            var hint = document.getElementById('discount-value-hint');
            var empty = document.getElementById('discount-preview-empty');
            var body = document.getElementById('discount-preview-body');
            var media = document.getElementById('discount-preview-media');
            var nameEl = document.getElementById('discount-preview-name');
            var skuEl = document.getElementById('discount-preview-sku');
            var originalEl = document.getElementById('discount-preview-original');
            var saleEl = document.getElementById('discount-preview-sale');
            var badgeEl = document.getElementById('discount-preview-badge');
            var windowEl = document.getElementById('discount-preview-window');
            var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

            function selectedType() {
                var checked = document.querySelector('input[name="type"]:checked');
                return checked ? checked.value : 'percentage';
            }

            function productById(id) {
                return products.find(function (item) {
                    return String(item.id) === String(id);
                }) || null;
            }

            function formatEuros(cents) {
                return (Math.max(0, cents) / 100).toFixed(2).replace('.', ',') + ' €';
            }

            function formatDateTime(value) {
                if (! value) {
                    return null;
                }
                var date = new Date(value);
                if (isNaN(date.getTime())) {
                    return null;
                }
                var day = String(date.getDate()).padStart(2, '0');
                var hours = String(date.getHours()).padStart(2, '0');
                var minutes = String(date.getMinutes()).padStart(2, '0');
                return day + ' ' + months[date.getMonth()] + ' ' + date.getFullYear() + ' · ' + hours + ':' + minutes;
            }

            function updateTypeCopy() {
                var isFixed = selectedType() === 'fixed';
                suffix.textContent = isFixed ? '€' : '%';
                hint.textContent = isFixed
                    ? 'Euro amount to subtract, for example 5.00.'
                    : 'Percentage to subtract, for example 20.';
            }

            function updatePreview() {
                var product = productById(productInput.value);
                updateTypeCopy();

                if (! product) {
                    empty.hidden = false;
                    body.hidden = true;
                    return;
                }

                empty.hidden = true;
                body.hidden = false;
                nameEl.textContent = product.name || product.label;
                skuEl.textContent = product.sku ? 'SKU ' + product.sku : 'No SKU';
                originalEl.textContent = formatEuros(product.price_cents);

                if (product.image) {
                    media.innerHTML = '<img src="' + product.image + '" alt="">';
                } else {
                    media.innerHTML = '';
                }

                var raw = parseFloat(valueInput.value);
                var type = selectedType();
                if (! isNaN(raw) && raw > 0) {
                    var sale = type === 'percentage'
                        ? product.price_cents - Math.round(product.price_cents * raw / 100)
                        : product.price_cents - Math.round(raw * 100);
                    saleEl.textContent = formatEuros(sale);
                    badgeEl.textContent = type === 'percentage'
                        ? '-' + Math.round(raw) + '%'
                        : '-' + formatEuros(Math.round(raw * 100));
                } else {
                    saleEl.textContent = '—';
                    badgeEl.textContent = '—';
                }

                var start = formatDateTime(startsInput.value);
                var end = formatDateTime(endsInput.value);
                if (! start && ! end) {
                    windowEl.textContent = 'Always on';
                } else {
                    windowEl.textContent = (start || 'No start date') + ' → ' + (end || 'No end date');
                }
            }

            document.querySelectorAll('input[name="type"]').forEach(function (input) {
                input.addEventListener('change', updatePreview);
            });
            productInput.addEventListener('search-select:change', updatePreview);
            productInput.addEventListener('change', updatePreview);
            valueInput.addEventListener('input', updatePreview);
            startsInput.addEventListener('input', updatePreview);
            endsInput.addEventListener('input', updatePreview);

            updatePreview();
        })();
    </script>
@endpush
