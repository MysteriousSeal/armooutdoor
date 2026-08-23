@extends('layouts.admin')

@php
    $isEdit = isset($purchaseOrder) && $purchaseOrder !== null;
@endphp

@section('title', $isEdit ? 'Edit purchase order' : 'New purchase order')

@php
    $productOptions = $products->map(fn ($product) => [
        'id' => $product->id,
        'label' => $product->localizedName(),
        'name' => $product->localizedName(),
        'sku' => $product->sku ?: '',
        'meta' => ($product->supplier?->name ? $product->supplier->name.' · ' : '').($product->supplier_price_cents ? number_format($product->supplier_price_cents / 100, 2, ',', ' ').' € HT' : 'no supplier price'),
        'image' => $product->image ? $product->imageUrl() : '',
        'search' => $product->localizedName().' '.($product->sku ?? '').' '.($product->supplier_reference ?? ''),
        'cost' => $product->supplier_price_cents ? number_format($product->supplier_price_cents / 100, 2, '.', '') : '',
        'variants' => $product->variants->where('is_active', true)->map(fn ($variant) => [
            'id' => $variant->id,
            'label' => $variant->label() !== '' ? $variant->label() : $product->localizedName(),
            'sku' => $variant->sku ?: '',
            'quantity' => $variant->quantity,
        ])->values(),
    ])->values();

    $defaultItems = $isEdit
        ? $purchaseOrder->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'variant_id' => $item->product_variant_id,
            'quantity' => $item->quantity_ordered,
            'cost' => number_format($item->unit_cost_cents / 100, 2, '.', ''),
        ])->values()->all()
        : [['product_id' => '', 'quantity' => 1]];
    $items = old('items', $defaultItems);
@endphp

@section('content')
    <div class="admin-form-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker">Inventory</p>
                    <h2 class="admin-list-title">{{ $isEdit ? 'Edit '.$purchaseOrder->number : 'New purchase order' }}</h2>
                    <p class="admin-list-lede">Costs are excl. VAT and prefill from each product's supplier price.</p>
                </div>
            </div>
        </header>

        <form
            method="POST"
            action="{{ $isEdit ? route('admin.purchase-orders.update', $purchaseOrder) : route('admin.purchase-orders.store') }}"
            class="po-form"
            id="purchase-order-form"
        >
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <section class="order-panel">
                <h3 class="order-panel-title">Supplier</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="supplier_id">Supplier</label>
                        <select id="supplier_id" name="supplier_id" class="form-control" required data-supplier-select>
                            <option value="">— Select a supplier —</option>
                            @foreach ($suppliers as $supplier)
                                <option
                                    value="{{ $supplier->id }}"
                                    data-lead-time="{{ $supplier->lead_time_days ?? '' }}"
                                    @selected((string) old('supplier_id', $isEdit ? $purchaseOrder->supplier_id : '') === (string) $supplier->id)
                                >
                                    {{ $supplier->name }}@if ($supplier->lead_time_days) — {{ $supplier->lead_time_days }} day lead time @endif
                                </option>
                            @endforeach
                        </select>
                        @error('supplier_id') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="form-group">
                        <label for="reference">Supplier reference (optional)</label>
                        <input type="text" id="reference" name="reference" class="form-control" value="{{ old('reference', $isEdit ? $purchaseOrder->reference : '') }}" maxlength="120" placeholder="Their quote or order number">
                        @error('reference') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="form-group">
                        <label for="expected_at">Expected date</label>
                        <input type="date" id="expected_at" name="expected_at" class="form-control" value="{{ old('expected_at', $isEdit ? $purchaseOrder->expected_at?->format('Y-m-d') : '') }}" data-expected-at>
                        @error('expected_at') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section class="order-panel">
                <div class="po-lines-header">
                    <h3 class="order-panel-title">Lines</h3>
                    <div class="po-vat-mode">
                        <label for="vat_rate">Prices entered as</label>
                        <select id="vat_rate" name="vat_rate" class="form-control" data-vat-rate>
                            <option value="0" @selected(old('vat_rate', '0') === '0')>Excl. VAT (HT)</option>
                            <option value="5.5" @selected(old('vat_rate') === '5.5')>Incl. VAT at 5.5%</option>
                            <option value="10" @selected(old('vat_rate') === '10')>Incl. VAT at 10%</option>
                            <option value="20" @selected(old('vat_rate') === '20')>Incl. VAT at 20%</option>
                        </select>
                    </div>
                </div>
                <p class="form-hint po-vat-hint">Type prices as the supplier shows them — the order itself is always stored excl. VAT.</p>
                @error('vat_rate') <p class="form-error">{{ $message }}</p> @enderror
                @error('items') <p class="form-error">{{ $message }}</p> @enderror

                <div class="po-lines-head" aria-hidden="true">
                    <span>Product</span>
                    <span>Variant</span>
                    <span>Qty</span>
                    <span>Unit cost € <span data-cost-mode>(excl. VAT)</span></span>
                    <span class="po-lines-head-total">Line total</span>
                    <span></span>
                </div>

                <div id="po-items">
                    @foreach ($items as $index => $item)
                        @php
                            $selectedOption = $productOptions->firstWhere('id', (int) ($item['product_id'] ?? 0));
                            $itemVariants = $selectedOption['variants'] ?? collect();
                        @endphp
                        <div class="po-line">
                            <div class="po-line-field">
                                <div class="search-select" data-search-select data-source="products">
                                    <input type="hidden" name="items[{{ $index }}][product_id]" value="{{ $item['product_id'] ?? '' }}">
                                    <input
                                        type="text"
                                        id="po-item-product-{{ $index }}"
                                        class="form-control search-select-input"
                                        placeholder="Search a product…"
                                        aria-label="Product"
                                        value="{{ $selectedOption['label'] ?? '' }}"
                                        autocomplete="off"
                                        spellcheck="false"
                                    >
                                    <ul class="search-select-list" hidden></ul>
                                </div>
                                @error('items.'.$index.'.product_id') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="po-line-field" data-variant-group @if (count($itemVariants) === 0) data-variant-empty @endif>
                                <select id="po-item-variant-{{ $index }}" name="items[{{ $index }}][variant_id]" class="form-control" data-item-variant aria-label="Variant" @if (count($itemVariants) === 0) disabled @endif>
                                    <option value="">—</option>
                                    @foreach ($itemVariants as $variantOption)
                                        <option value="{{ $variantOption['id'] }}" @selected((string) ($item['variant_id'] ?? '') === (string) $variantOption['id'])>
                                            {{ $variantOption['label'] }}@if ($variantOption['sku']) ({{ $variantOption['sku'] }})@endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('items.'.$index.'.variant_id') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="po-line-field">
                                <input type="number" id="po-item-qty-{{ $index }}" name="items[{{ $index }}][quantity]" class="form-control" data-item-qty aria-label="Quantity" value="{{ $item['quantity'] ?? 1 }}" min="1">
                                @error('items.'.$index.'.quantity') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="po-line-field">
                                <input type="number" id="po-item-cost-{{ $index }}" name="items[{{ $index }}][cost]" class="form-control" data-item-cost aria-label="Unit cost" value="{{ $item['cost'] ?? '' }}" min="0" step="0.01" placeholder="0.00">
                                @error('items.'.$index.'.cost') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <span class="po-line-total" data-line-total>—</span>
                            <button type="button" class="po-line-remove" data-remove-item aria-label="Remove this line">&times;</button>
                        </div>
                    @endforeach
                </div>
                <template id="po-item-row-template">
                    <div class="po-line">
                        <div class="po-line-field">
                            <div class="search-select" data-search-select data-source="products">
                                <input type="hidden" name="items[__INDEX__][product_id]" value="">
                                <input
                                    type="text"
                                    class="form-control search-select-input"
                                    placeholder="Search a product…"
                                    aria-label="Product"
                                    autocomplete="off"
                                    spellcheck="false"
                                >
                                <ul class="search-select-list" hidden></ul>
                            </div>
                        </div>
                        <div class="po-line-field" data-variant-group data-variant-empty>
                            <select name="items[__INDEX__][variant_id]" class="form-control" data-item-variant aria-label="Variant" disabled>
                                <option value="">—</option>
                            </select>
                        </div>
                        <div class="po-line-field">
                            <input type="number" name="items[__INDEX__][quantity]" class="form-control" data-item-qty aria-label="Quantity" value="1" min="1">
                        </div>
                        <div class="po-line-field">
                            <input type="number" name="items[__INDEX__][cost]" class="form-control" data-item-cost aria-label="Unit cost" value="" min="0" step="0.01" placeholder="0.00">
                        </div>
                        <span class="po-line-total" data-line-total>—</span>
                        <button type="button" class="po-line-remove" data-remove-item aria-label="Remove this line">&times;</button>
                    </div>
                </template>

                <div class="po-lines-footer">
                    <button type="button" class="btn btn-secondary" id="po-add-item">Add a line</button>
                    <dl class="po-running-total">
                        <div><dt>Lines</dt><dd data-total-lines>—</dd></div>
                        <div><dt>Shipping</dt><dd data-total-shipping>—</dd></div>
                        <div class="po-running-total-grand"><dt>Total <span data-cost-mode>(excl. VAT)</span></dt><dd data-total-grand>—</dd></div>
                    </dl>
                </div>
            </section>

            <section class="order-panel">
                <h3 class="order-panel-title">Charges & notes</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="shipping_price">Shipping / freight € <span data-cost-mode>(excl. VAT)</span></label>
                        <input type="number" id="shipping_price" name="shipping_price" class="form-control" value="{{ old('shipping_price', $isEdit && $purchaseOrder->shipping_cents ? number_format($purchaseOrder->shipping_cents / 100, 2, '.', '') : '') }}" min="0" step="0.01">
                        @error('shipping_price') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="form-group form-group--wide">
                        <label for="notes">Notes (optional)</label>
                        <textarea id="notes" name="notes" class="form-control" rows="2" maxlength="2000">{{ old('notes', $isEdit ? $purchaseOrder->notes : '') }}</textarea>
                        @error('notes') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <div class="po-form-actions">
                <a href="{{ $isEdit ? route('admin.purchase-orders.show', $purchaseOrder) : route('admin.purchase-orders.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Save draft' : 'Create draft' }}</button>
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
    <script src="{{ versioned_asset('js/admin-purchase-order-items.js') }}"></script>
@endpush
