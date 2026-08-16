@extends('layouts.admin')

@section('title', $discount->exists ? 'Edit discount' : 'Add discount')

@section('content')
    @php
        $valueDefault = $discount->exists
            ? ($discount->type === 'percentage' ? $discount->value : number_format($discount->value / 100, 2, '.', ''))
            : '';
        $productOptions = $products->map(fn ($product) => [
            'id' => $product->id,
            'label' => $product->localizedName(),
            'name' => $product->localizedName(),
            'sku' => $product->sku ?: '',
            'meta' => $product->formattedPrice().' · '.$product->quantity.' in stock',
            'image' => $product->image ? $product->imageUrl() : '',
            'search' => $product->localizedName().' '.($product->sku ?? ''),
        ]);
        $selectedProductId = old('product_id', $discount->product_id);
        $selectedProductLabel = optional($productOptions->firstWhere('id', (int) $selectedProductId))['label'] ?? '';
    @endphp

    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker">Catalog</p>
                    <h2 class="admin-list-title">{{ $discount->exists ? 'Edit discount' : 'Add discount' }}</h2>
                    <p class="admin-list-lede">A single product, a percentage or fixed amount off — no code needed.</p>
                </div>
                <a href="{{ route('admin.discounts.index') }}" class="btn btn-secondary">Back to discounts</a>
            </div>
        </header>

        <form
            method="POST"
            action="{{ $discount->exists ? route('admin.discounts.update', $discount) : route('admin.discounts.store') }}"
            class="admin-form-card admin-form-card--solo"
        >
            @csrf
            @if ($discount->exists)
                @method('PUT')
            @endif

            <div class="form-group">
                <label for="product_id">Product</label>
                <div class="search-select" data-search-select data-source="products">
                    <input type="hidden" name="product_id" value="{{ $selectedProductId }}">
                    <input
                        type="text"
                        id="product_id"
                        class="form-control search-select-input"
                        placeholder="Search a product…"
                        value="{{ $selectedProductLabel }}"
                        autocomplete="off"
                        spellcheck="false"
                    >
                    <ul class="search-select-list" hidden></ul>
                </div>
                <p class="form-hint">Only products without a discount already are listed.</p>
                @error('product_id') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="type">Type</label>
                    <select id="type" name="type" class="form-control" required>
                        <option value="percentage" @selected(old('type', $discount->type ?? 'percentage') === 'percentage')>Percentage off</option>
                        <option value="fixed" @selected(old('type', $discount->type) === 'fixed')>Fixed amount off (EUR)</option>
                    </select>
                    @error('type') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label for="value">Value</label>
                    <input type="number" id="value" name="value" class="form-control" value="{{ old('value', $valueDefault) }}" min="0.01" step="0.01" required>
                    <p class="form-hint">A percentage (e.g. 20) or a euro amount (e.g. 5.00), depending on the type above.</p>
                    @error('value') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="starts_at">Start date &amp; time</label>
                    <input type="datetime-local" id="starts_at" name="starts_at" class="form-control" value="{{ old('starts_at', $discount->starts_at?->format('Y-m-d\TH:i')) }}">
                    <p class="form-hint">Leave blank to start immediately.</p>
                    @error('starts_at') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label for="ends_at">End date &amp; time</label>
                    <input type="datetime-local" id="ends_at" name="ends_at" class="form-control" value="{{ old('ends_at', $discount->ends_at?->format('Y-m-d\TH:i')) }}">
                    <p class="form-hint">Leave blank to run indefinitely.</p>
                    @error('ends_at') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">{{ $discount->exists ? 'Save changes' : 'Create discount' }}</button>
                <a href="{{ route('admin.discounts.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/admin-search-select.js') }}"></script>
    <script>
        AdminSearchSelect.catalogs.products = @json($productOptions);
        AdminSearchSelect.mountAll();
    </script>
@endpush
