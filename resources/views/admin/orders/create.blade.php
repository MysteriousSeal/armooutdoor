@extends('layouts.admin')

@php
    $isEdit = isset($order) && $order !== null;
@endphp

@section('title', $isEdit ? 'Edit draft order' : 'Create manual order')

@php
    $customerIsExternal = $isEdit && $order->user && $order->user->external;
    $defaultCustomerMode = $customerIsExternal ? 'new' : 'existing';
    $defaultCustomerId = $isEdit && $order->user && ! $order->user->external
        ? $order->user_id
        : (($preselectedCustomerId ?? 0) > 0 ? $preselectedCustomerId : '');

    $customerOptions = $customers->map(fn ($customer) => [
        'id' => $customer->id,
        'label' => $customer->name.' ('.$customer->email.')',
        'search' => $customer->name.' '.$customer->email,
    ])->values();
    $productOptions = $products->map(fn ($product) => [
        'id' => $product->id,
        'label' => $product->localizedName(),
        'name' => $product->localizedName(),
        'sku' => $product->sku ?: '',
        'meta' => $product->formattedPrice().' · '.$product->quantity.' in stock',
        'image' => $product->image ? $product->imageUrl() : '',
        'search' => $product->localizedName().' '.($product->sku ?? ''),
        'price' => number_format($product->price_cents / 100, 2, '.', ''),
        'variants' => $product->variants->where('is_active', true)->map(fn ($variant) => [
            'id' => $variant->id,
            'label' => $variant->label() !== '' ? $variant->label() : $product->localizedName(),
            'sku' => $variant->sku ?: '',
            'price' => number_format($variant->effectivePriceCents() / 100, 2, '.', ''),
            'quantity' => $variant->quantity,
        ])->values(),
    ])->values();
    $selectedCustomerLabel = optional($customerOptions->firstWhere('id', (int) old('customer_id', $defaultCustomerId)))['label'] ?? '';
    $defaultItems = $isEdit
        ? $order->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'variant_id' => $item->product_variant_id,
            'quantity' => $item->quantity,
            'price' => number_format($item->unit_price_cents / 100, 2, '.', ''),
        ])->values()->all()
        : [['product_id' => '', 'quantity' => 1], ['product_id' => '', 'quantity' => 1]];
    $manualDiscount = $isEdit && $order->discount_code_id === null ? $order->discount_code_snapshot : null;
    $defaultDiscountType = $manualDiscount['type'] ?? '';
    $defaultDiscountValue = $manualDiscount
        ? ($manualDiscount['type'] === 'percentage' ? $manualDiscount['value'] : number_format($manualDiscount['value'] / 100, 2, '.', ''))
        : '';
    $customerAddresses = $customers->mapWithKeys(function ($customer) {
        return [
            $customer->id => $customer->addresses->map(fn ($address) => [
                'id' => $address->id,
                'label' => trim(
                    ($address->is_default ? 'Default · ' : '').
                    ($address->label ? $address->label.' — ' : '').
                    $address->recipientName().', '.
                    $address->line1.', '.
                    $address->postal_code.' '.$address->city
                ),
                'is_default' => $address->is_default,
                'first_name' => $address->first_name,
                'last_name' => $address->last_name,
                'line1' => $address->line1,
                'line2' => $address->line2,
                'postal_code' => $address->postal_code,
                'city' => $address->city,
                'country' => $address->country,
                'phone' => $address->phone,
            ])->values(),
        ];
    });
@endphp

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker">
                        <a href="{{ route('admin.orders.index') }}">Orders</a>
                        @if ($isEdit)
                            / <a href="{{ route('admin.orders.show', $order) }}">{{ $order->number }}</a>
                        @endif
                    </p>
                    <h2 class="admin-list-title">{{ $isEdit ? 'Edit draft order' : 'Create manual order' }}</h2>
                    <p class="admin-list-lede">
                        @if ($isEdit)
                            This order is still a draft — everything can be edited. Save it as a draft again, or finalize it into a real order.
                        @else
                            Log a sale made outside the site — pick products, then a shipping and billing address.
                        @endif
                    </p>
                </div>
                <a href="{{ $isEdit ? route('admin.orders.show', $order) : route('admin.orders.index') }}" class="btn btn-secondary">
                    {{ $isEdit ? 'Back to order' : 'Back to orders' }}
                </a>
            </div>
        </header>

        <form
            method="POST"
            action="{{ $isEdit ? route('admin.orders.update', $order) : route('admin.orders.store') }}"
            class="admin-order-create"
            id="manual-order-form"
            novalidate
        >
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div class="admin-order-create-grid">
                <div class="order-main">
                    <section class="order-panel">
                        <h3 class="order-panel-title">Customer</h3>

                        <div class="admin-choice-row">
                            <label class="admin-choice {{ old('customer_mode', $defaultCustomerMode) === 'existing' ? 'is-selected' : '' }}">
                                <input type="radio" name="customer_mode" value="existing" id="customer-mode-existing" @checked(old('customer_mode', $defaultCustomerMode) === 'existing')>
                                <span>Existing customer</span>
                            </label>
                            <label class="admin-choice {{ old('customer_mode', $defaultCustomerMode) === 'new' ? 'is-selected' : '' }}">
                                <input type="radio" name="customer_mode" value="new" id="customer-mode-new" @checked(old('customer_mode', $defaultCustomerMode) === 'new')>
                                <span>New external customer</span>
                            </label>
                        </div>
                        <p class="form-hint">External customers are not listed in the shop customers page.</p>

                        <div class="form-group" id="customer-existing-fields">
                            <label for="customer_search">Customer</label>
                            <div class="search-select" data-search-select data-source="customers">
                                <input type="hidden" name="customer_id" id="customer_id" value="{{ old('customer_id', $defaultCustomerId) }}">
                                <input
                                    type="text"
                                    id="customer_search"
                                    class="form-control search-select-input"
                                    placeholder="Search by name or email…"
                                    value="{{ $selectedCustomerLabel }}"
                                    autocomplete="off"
                                    spellcheck="false"
                                >
                                <ul class="search-select-list" hidden></ul>
                            </div>
                            @error('customer_id') <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        <div id="customer-new-fields">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="new_customer_first_name">First name</label>
                                    <input type="text" id="new_customer_first_name" name="new_customer_first_name" class="form-control" value="{{ old('new_customer_first_name', $customerIsExternal ? $order->user->first_name : '') }}" maxlength="80">
                                    @error('new_customer_first_name') <p class="form-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="form-group">
                                    <label for="new_customer_last_name">Last name</label>
                                    <input type="text" id="new_customer_last_name" name="new_customer_last_name" class="form-control" value="{{ old('new_customer_last_name', $customerIsExternal ? $order->user->last_name : '') }}" maxlength="80">
                                    @error('new_customer_last_name') <p class="form-error">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="new_customer_email">Email (optional)</label>
                                <input type="email" id="new_customer_email" name="new_customer_email" class="form-control" value="{{ old('new_customer_email', $customerIsExternal ? $order->user->email : '') }}" maxlength="160">
                                @error('new_customer_email') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </section>

                    <section class="order-panel">
                        <h3 class="order-panel-title">Products</h3>
                        <p class="form-error js-field-error" data-error-for="items" @unless($errors->has('items')) hidden @endunless>
                            {{ $errors->first('items') }}
                        </p>

                        <div id="item-rows">
                            @php($oldItems = old('items', $defaultItems))
                            @foreach ($oldItems as $index => $item)
                                @php($selectedProductOption = $productOptions->firstWhere('id', (int) ($item['product_id'] ?? 0)))
                                @php($selectedProductLabel = $selectedProductOption['label'] ?? '')
                                @php($itemVariants = $selectedProductOption['variants'] ?? collect())
                                <div class="form-row form-row--item">
                                    <div class="form-group">
                                        <label for="item-product-{{ $index }}">Product</label>
                                        <div class="search-select" data-search-select data-source="products">
                                            <input type="hidden" name="items[{{ $index }}][product_id]" value="{{ $item['product_id'] ?? '' }}">
                                            <input
                                                type="text"
                                                id="item-product-{{ $index }}"
                                                class="form-control search-select-input"
                                                placeholder="Search a product…"
                                                value="{{ $selectedProductLabel }}"
                                                autocomplete="off"
                                                spellcheck="false"
                                            >
                                            <ul class="search-select-list" hidden></ul>
                                        </div>
                                        @error('items.'.$index.'.product_id') <p class="form-error">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="form-group" data-variant-group @if ($itemVariants->isEmpty()) hidden @endif>
                                        <label for="item-variant-{{ $index }}">Variant</label>
                                        <select id="item-variant-{{ $index }}" name="items[{{ $index }}][variant_id]" class="form-control" data-item-variant>
                                            <option value="">— Select a variant —</option>
                                            @foreach ($itemVariants as $variantOption)
                                                <option
                                                    value="{{ $variantOption['id'] }}"
                                                    data-price="{{ $variantOption['price'] }}"
                                                    @selected((string) ($item['variant_id'] ?? '') === (string) $variantOption['id'])
                                                >
                                                    {{ $variantOption['label'] }}@if ($variantOption['sku']) ({{ $variantOption['sku'] }})@endif — {{ $variantOption['quantity'] }} in stock
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('items.'.$index.'.variant_id') <p class="form-error">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="form-group">
                                        <label for="item-qty-{{ $index }}">Qty</label>
                                        <input type="number" id="item-qty-{{ $index }}" name="items[{{ $index }}][quantity]" class="form-control" value="{{ $item['quantity'] ?? 1 }}" min="1">
                                        @error('items.'.$index.'.quantity') <p class="form-error">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="form-group">
                                        <label for="item-price-{{ $index }}">Price (€)</label>
                                        <input
                                            type="number"
                                            id="item-price-{{ $index }}"
                                            name="items[{{ $index }}][price]"
                                            class="form-control"
                                            data-item-price
                                            value="{{ $item['price'] ?? optional($productOptions->firstWhere('id', (int) ($item['product_id'] ?? 0)))['price'] ?? '' }}"
                                            min="0"
                                            step="0.01"
                                        >
                                        @error('items.'.$index.'.price') <p class="form-error">{{ $message }}</p> @enderror
                                    </div>
                                    <button type="button" class="btn btn-sm btn-secondary" data-remove-item>Remove</button>
                                </div>
                            @endforeach
                        </div>
                        <template id="item-row-template">
                            <div class="form-row form-row--item">
                                <div class="form-group">
                                    <label>Product</label>
                                    <div class="search-select" data-search-select data-source="products">
                                        <input type="hidden" name="items[__INDEX__][product_id]" value="">
                                        <input
                                            type="text"
                                            class="form-control search-select-input"
                                            placeholder="Search a product…"
                                            autocomplete="off"
                                            spellcheck="false"
                                        >
                                        <ul class="search-select-list" hidden></ul>
                                    </div>
                                </div>
                                <div class="form-group" data-variant-group hidden>
                                    <label>Variant</label>
                                    <select name="items[__INDEX__][variant_id]" class="form-control" data-item-variant>
                                        <option value="">— Select a variant —</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Qty</label>
                                    <input type="number" name="items[__INDEX__][quantity]" class="form-control" value="1" min="1">
                                </div>
                                <div class="form-group">
                                    <label>Price (€)</label>
                                    <input type="number" name="items[__INDEX__][price]" class="form-control" data-item-price min="0" step="0.01">
                                </div>
                                <button type="button" class="btn btn-sm btn-secondary" data-remove-item>Remove</button>
                            </div>
                        </template>
                        <button type="button" class="btn btn-sm btn-secondary" id="add-item-row">Add another product</button>
                    </section>

                    <section class="order-panel">
                        <h3 class="order-panel-title">Billing address</h3>
                        <div class="form-group" id="saved-billing-wrap" hidden>
                            <label for="saved-billing-address">Saved address</label>
                            <select id="saved-billing-address" class="form-control" data-address-picker="billing">
                                <option value="">New address</option>
                            </select>
                        </div>
                        @include('admin.orders.partials.address-fields', ['snapshot' => $isEdit ? ($order->billing_address_snapshot ?? $order->address_snapshot ?? []) : [], 'prefix' => 'billing', 'bag' => 'default', 'fieldPrefix' => 'billing_', 'phoneOptional' => true])
                    </section>

                    <section class="order-panel">
                        <h3 class="order-panel-title">Carrier</h3>
                        @php($selectedCarrierId = (string) old('carrier_id', $isEdit ? $order->carrier_id : ''))
                        <div class="form-group">
                            <label>Carrier</label>
                            <input type="hidden" id="carrier_id" name="carrier_id" value="{{ $selectedCarrierId }}">
                            <div class="admin-choice-row admin-choice-row--stack">
                                @foreach ($carriers as $carrier)
                                    <label class="admin-choice">
                                        <input
                                            type="radio"
                                            name="carrier_id_choice"
                                            value="{{ $carrier->id }}"
                                            data-sync-field="carrier_id"
                                            data-price="{{ number_format($carrier->price_cents / 100, 2, '.', '') }}"
                                            data-method="{{ $carrier->method === \App\Enums\DeliveryMethod::Relay ? 'relay' : 'home' }}"
                                            data-carrier-slug="{{ $carrier->slug }}"
                                            @checked($selectedCarrierId === (string) $carrier->id)
                                        >
                                        <span class="admin-choice-line">
                                            <span class="admin-table-strong">{{ $carrier->localizedName() }}</span>
                                            <span>{{ $carrier->formattedStartingPrice() }} · {{ $carrier->method === \App\Enums\DeliveryMethod::Home ? 'Home delivery' : 'Relay' }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('carrier_id') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="form-group">
                            <label for="shipping_price">Shipping (€)</label>
                            <input
                                type="number"
                                id="shipping_price"
                                name="shipping_price"
                                class="form-control"
                                value="{{ old('shipping_price', $isEdit ? number_format($order->shipping_cents / 100, 2, '.', '') : '') }}"
                                min="0"
                                step="0.01"
                            >
                            @error('shipping_price') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="form-group" id="admin-relay-picker" hidden>
                            <label for="admin-relay-postal-code">Pickup point</label>
                            <p class="form-hint">Defaults to the billing address postal code. Click a point to fill it into the shipping address.</p>
                            <input
                                type="text"
                                id="admin-relay-postal-code"
                                class="form-control"
                                placeholder="Postal code"
                                maxlength="12"
                            >
                            <div class="admin-relay-list" id="admin-relay-list"></div>
                            <p class="form-hint" id="admin-relay-empty" hidden>No pickup points found for this postal code.</p>

                            {{-- Le point relais d'une place de marché ne figure pas
                                 forcément dans la liste du transporteur : les champs
                                 restent saisissables à la main. --}}
                            @php($relay = old('relay', $isEdit ? ($order->relay_snapshot ?? []) : []))
                            <div class="admin-relay-fields">
                                <div class="form-group">
                                    <label for="relay_name">Pickup point name</label>
                                    <input type="text" id="relay_name" name="relay[name]" class="form-control" value="{{ $relay['name'] ?? '' }}" maxlength="120">
                                    @error('relay.name') <p class="form-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="form-group">
                                    <label for="relay_line1">Pickup point address</label>
                                    <input type="text" id="relay_line1" name="relay[line1]" class="form-control" value="{{ $relay['line1'] ?? '' }}" maxlength="120">
                                    @error('relay.line1') <p class="form-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="relay_postal_code">Postal code</label>
                                        <input type="text" id="relay_postal_code" name="relay[postal_code]" class="form-control" value="{{ $relay['postal_code'] ?? '' }}" maxlength="12">
                                        @error('relay.postal_code') <p class="form-error">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="form-group">
                                        <label for="relay_city">City</label>
                                        <input type="text" id="relay_city" name="relay[city]" class="form-control" value="{{ $relay['city'] ?? '' }}" maxlength="80">
                                        @error('relay.city') <p class="form-error">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                                <input type="hidden" id="relay_slug" name="relay[slug]" value="{{ $relay['slug'] ?? '' }}">
                            </div>
                        </div>
                    </section>

                    <section class="order-panel">
                        <h3 class="order-panel-title">Shipping address</h3>
                        <div class="form-group" id="saved-shipping-wrap" hidden>
                            <label for="saved-shipping-address">Saved address</label>
                            <select id="saved-shipping-address" class="form-control" data-address-picker="shipping">
                                <option value="">New address</option>
                            </select>
                        </div>
                        @include('admin.orders.partials.address-fields', ['snapshot' => $isEdit ? ($order->address_snapshot ?? []) : [], 'prefix' => 'shipping', 'bag' => 'default', 'phoneOptional' => true])
                    </section>

                    <section class="order-panel">
                        <h3 class="order-panel-title">Discount</h3>
                        @php($selectedDiscountType = old('discount_type', $defaultDiscountType))
                        <div class="form-group">
                            <label>Type</label>
                            <input type="hidden" id="discount_type" name="discount_type" value="{{ $selectedDiscountType }}">
                            <div class="admin-choice-row admin-choice-row--3">
                                <label class="admin-choice">
                                    <input type="radio" name="discount_type_choice" value="" data-sync-field="discount_type" @checked($selectedDiscountType === '' || $selectedDiscountType === null)>
                                    <span class="admin-table-strong">None</span>
                                </label>
                                <label class="admin-choice">
                                    <input type="radio" name="discount_type_choice" value="percentage" data-sync-field="discount_type" @checked($selectedDiscountType === 'percentage')>
                                    <span class="admin-table-strong">Percentage</span>
                                </label>
                                <label class="admin-choice">
                                    <input type="radio" name="discount_type_choice" value="fixed" data-sync-field="discount_type" @checked($selectedDiscountType === 'fixed')>
                                    <span class="admin-table-strong">Fixed amount</span>
                                </label>
                            </div>
                            @error('discount_type') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="form-group" id="discount-value-group" @if ($selectedDiscountType === '' || $selectedDiscountType === null) hidden @endif>
                            <label for="discount_value">Value</label>
                            <input
                                type="number"
                                id="discount_value"
                                name="discount_value"
                                class="form-control"
                                value="{{ old('discount_value', $defaultDiscountValue) }}"
                                min="0.01"
                                step="0.01"
                            >
                            @error('discount_value') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <p class="form-hint">Percentage (1–100) or a fixed euro amount off the order subtotal. No discount code is created.</p>
                    </section>

                    <section class="order-panel">
                        <h3 class="order-panel-title">Marketplace</h3>
                        @php($selectedMarketplaceId = (string) old('marketplace_id', $isEdit ? $order->marketplace_id : ''))
                        <div class="form-group">
                            <label>Marketplace (optional)</label>
                            <input type="hidden" id="marketplace_id" name="marketplace_id" value="{{ $selectedMarketplaceId }}">
                            <div class="admin-choice-row">
                                <label class="admin-choice">
                                    <input type="radio" name="marketplace_id_choice" value="" data-sync-field="marketplace_id" @checked($selectedMarketplaceId === '')>
                                    <span class="admin-table-strong">None</span>
                                </label>
                                @foreach ($marketplaces as $marketplace)
                                    <label class="admin-choice">
                                        <input
                                            type="radio"
                                            name="marketplace_id_choice"
                                            value="{{ $marketplace->id }}"
                                            data-sync-field="marketplace_id"
                                            @checked($selectedMarketplaceId === (string) $marketplace->id)
                                        >
                                        <span class="admin-table-strong">{{ $marketplace->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('marketplace_id') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </section>
                </div>

                <aside class="admin-order-create-aside">
                    <section class="order-panel admin-order-preview" id="order-preview">
                        <h3 class="order-panel-title">Preview</h3>
                        <dl class="admin-order-preview-facts">
                            <div>
                                <dt>Customer</dt>
                                <dd id="order-preview-customer">—</dd>
                            </div>
                            <div>
                                <dt>Carrier</dt>
                                <dd id="order-preview-carrier">—</dd>
                            </div>
                            <div>
                                <dt>Marketplace</dt>
                                <dd id="order-preview-marketplace">—</dd>
                            </div>
                        </dl>
                        <ul class="admin-order-preview-items" id="order-preview-items"></ul>
                        <p class="admin-order-preview-empty" id="order-preview-empty">Add products to preview the order.</p>
                        <dl class="order-totals" id="order-preview-totals">
                            <div>
                                <dt>Subtotal</dt>
                                <dd id="order-preview-subtotal">—</dd>
                            </div>
                            <div id="order-preview-discount-row" hidden>
                                <dt id="order-preview-discount-label">Discount</dt>
                                <dd id="order-preview-discount">—</dd>
                            </div>
                            <div>
                                <dt>Shipping</dt>
                                <dd id="order-preview-shipping">—</dd>
                            </div>
                        </dl>
                        <div class="admin-order-preview-total">
                            <span>Total</span>
                            <strong id="order-preview-total">—</strong>
                        </div>
                        <div class="admin-order-preview-addresses">
                            <div>
                                <p class="admin-order-preview-address-label">Shipping</p>
                                <dl class="admin-order-preview-address-fields" id="order-preview-shipping-address">
                                    <div><dt>First name</dt><dd data-field="first_name">—</dd></div>
                                    <div><dt>Last name</dt><dd data-field="last_name">—</dd></div>
                                    <div><dt>Address</dt><dd data-field="line1">—</dd></div>
                                    <div><dt>Line 2</dt><dd data-field="line2">—</dd></div>
                                    <div><dt>Postal code</dt><dd data-field="postal_code">—</dd></div>
                                    <div><dt>City</dt><dd data-field="city">—</dd></div>
                                    <div><dt>Country</dt><dd data-field="country">—</dd></div>
                                    <div><dt>Phone</dt><dd data-field="phone">—</dd></div>
                                </dl>
                            </div>
                            <div>
                                <p class="admin-order-preview-address-label">Billing</p>
                                <dl class="admin-order-preview-address-fields" id="order-preview-billing-address">
                                    <div><dt>First name</dt><dd data-field="first_name">—</dd></div>
                                    <div><dt>Last name</dt><dd data-field="last_name">—</dd></div>
                                    <div><dt>Address</dt><dd data-field="line1">—</dd></div>
                                    <div><dt>Line 2</dt><dd data-field="line2">—</dd></div>
                                    <div><dt>Postal code</dt><dd data-field="postal_code">—</dd></div>
                                    <div><dt>City</dt><dd data-field="city">—</dd></div>
                                    <div><dt>Country</dt><dd data-field="country">—</dd></div>
                                    <div><dt>Phone</dt><dd data-field="phone">—</dd></div>
                                </dl>
                            </div>
                        </div>
                    </section>

                    <div class="order-panel admin-order-create-actions">
                        <a href="{{ $isEdit ? route('admin.orders.show', $order) : route('admin.orders.index') }}" class="btn btn-secondary">Cancel</a>
                        <button type="submit" name="action" value="draft" class="btn btn-secondary">Save as draft</button>
                        <button type="submit" name="action" value="placed" class="btn btn-primary">{{ $isEdit ? 'Finalize order' : 'Create order' }}</button>
                    </div>
                </aside>
            </div>
        </form>

        <dialog id="marketplace-confirm-modal" class="modal" aria-labelledby="marketplace-confirm-title">
            <p class="modal-kicker">Marketplace</p>
            <h3 class="modal-title" id="marketplace-confirm-title">No marketplace selected</h3>
            <p class="modal-body">
                Are you sure you don't want to select a marketplace for this order?
            </p>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="button" class="btn btn-primary" id="marketplace-confirm-continue">Continue without marketplace</button>
            </div>
        </dialog>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/admin-search-select.js') }}"></script>
    <script>
        AdminSearchSelect.catalogs.customers = @json($customerOptions);
        AdminSearchSelect.catalogs.products = @json($productOptions);
        AdminSearchSelect.mountAll();
        var customerAddresses = @json($customerAddresses);
        var relayPointsUrl = @json(route('checkout.relay-points'));

        (function () {
            var itemRows = document.getElementById('item-rows');
            var addItemBtn = document.getElementById('add-item-row');
            var itemTemplate = document.getElementById('item-row-template');
            var itemCounter = itemRows.querySelectorAll('.form-row--item').length;

            addItemBtn.addEventListener('click', function () {
                var html = itemTemplate.innerHTML.replaceAll('__INDEX__', String(itemCounter));
                itemRows.insertAdjacentHTML('beforeend', html);
                AdminSearchSelect.mountAll(itemRows);
                itemCounter++;
            });

            itemRows.addEventListener('click', function (event) {
                if (event.target.hasAttribute('data-remove-item')) {
                    event.target.closest('.form-row--item').remove();
                }
            });

            function populateVariantSelect(row, variants) {
                var group = row.querySelector('[data-variant-group]');
                var select = row.querySelector('[data-item-variant]');
                if (! group || ! select) {
                    return;
                }
                select.innerHTML = '<option value="">— Select a variant —</option>';
                (variants || []).forEach(function (variant) {
                    var option = document.createElement('option');
                    option.value = String(variant.id);
                    option.setAttribute('data-price', variant.price);
                    option.textContent = variant.label + (variant.sku ? ' (' + variant.sku + ')' : '') + ' — ' + variant.quantity + ' in stock';
                    select.appendChild(option);
                });
                group.hidden = ! (variants && variants.length);
            }

            itemRows.addEventListener('search-select:change', function (event) {
                var row = event.target.closest('.form-row--item');
                if (! row || ! event.detail) {
                    return;
                }
                var hasVariants = Boolean(event.detail.variants && event.detail.variants.length);
                populateVariantSelect(row, event.detail.variants);
                var price = row.querySelector('[data-item-price]');
                var variantGroup = row.querySelector('[data-variant-group]');
                if (price) {
                    price.value = hasVariants ? '' : (event.detail.price != null ? event.detail.price : '');
                }
                if (variantGroup) {
                    clearFieldError(row.querySelector('[data-item-variant]'));
                }
            });

            itemRows.addEventListener('change', function (event) {
                if (! event.target.hasAttribute('data-item-variant')) {
                    return;
                }
                var row = event.target.closest('.form-row--item');
                var price = row ? row.querySelector('[data-item-price]') : null;
                var option = event.target.options[event.target.selectedIndex];
                if (price && option && option.value !== '') {
                    price.value = option.getAttribute('data-price') || '';
                }
            });

            var modeExisting = document.getElementById('customer-mode-existing');
            var modeNew = document.getElementById('customer-mode-new');
            var existingFields = document.getElementById('customer-existing-fields');
            var newFields = document.getElementById('customer-new-fields');

            function syncCustomerMode() {
                var isNew = modeNew.checked;
                existingFields.hidden = isNew;
                newFields.hidden = ! isNew;
                document.querySelectorAll('input[name="customer_mode"]').forEach(function (input) {
                    var choice = input.closest('.admin-choice');
                    if (choice) {
                        choice.classList.toggle('is-selected', input.checked);
                    }
                });
                if (typeof syncAddressPickers === 'function') {
                    if (isNew) {
                        shippingPickerWrap.hidden = true;
                        billingPickerWrap.hidden = true;
                    } else {
                        syncAddressPickers(false);
                    }
                }
            }

            modeExisting.addEventListener('change', syncCustomerMode);
            modeNew.addEventListener('change', syncCustomerMode);

            var carrierSelect = document.getElementById('carrier_id');
            var shippingPrice = document.getElementById('shipping_price');

            function selectedCarrierRadio() {
                return document.querySelector('input[data-sync-field="carrier_id"]:checked');
            }

            function applyCarrierPrice(radio) {
                if (! radio) {
                    return;
                }
                shippingPrice.value = radio.getAttribute('data-price') || '';
            }

            document.addEventListener('change', function (event) {
                var radio = event.target.closest('[data-sync-field]');
                if (! radio) {
                    return;
                }
                var target = document.getElementById(radio.getAttribute('data-sync-field'));
                if (target) {
                    target.value = radio.value;
                    target.dispatchEvent(new Event('change', { bubbles: true }));
                }
                if (radio.getAttribute('data-sync-field') === 'carrier_id') {
                    applyCarrierPrice(radio);
                }
            });

            if (shippingPrice.value === '' && carrierSelect.value !== '') {
                applyCarrierPrice(selectedCarrierRadio());
            }

            var RELAY_PROVIDERS = {
                'mondial-relay': 'mondial_relay',
                'relais-pickup': 'chronopost',
            };
            var relayPicker = document.getElementById('admin-relay-picker');
            var relayList = document.getElementById('admin-relay-list');
            var relayEmpty = document.getElementById('admin-relay-empty');
            var relaySearchInput = document.getElementById('admin-relay-postal-code');
            var billingPostalCode = document.getElementById('billing_postal_code');
            var billingCountry = document.getElementById('billing_country');
            var relayPointsLoadedFor = null;
            var relaySearchTouched = false;

            relaySearchInput.addEventListener('input', function () {
                relaySearchTouched = true;
                searchRelayPoints();
            });

            function fillShippingFromRelayPoint(point) {
                document.getElementById('shipping_line1').value = point.name;
                document.getElementById('shipping_line2').value = point.line1;
                document.getElementById('shipping_postal_code').value = point.postal_code;
                document.getElementById('shipping_city').value = point.city;

                // Le point choisi renseigne aussi son identité, que l'adresse
                // d'expédition seule ne conserve pas.
                document.getElementById('relay_name').value = point.name;
                document.getElementById('relay_line1').value = point.line1;
                document.getElementById('relay_postal_code').value = point.postal_code;
                document.getElementById('relay_city').value = point.city;
                document.getElementById('relay_slug').value = point.slug || '';
                updateOrderPreview();
                syncSubmitButtons();
            }

            function renderRelayPoints(points) {
                relayList.innerHTML = '';
                points.slice(0, 10).forEach(function (point) {
                    var button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'admin-relay-point';

                    var name = document.createElement('span');
                    name.className = 'admin-relay-point-name';
                    name.textContent = point.name;
                    button.appendChild(name);

                    var address = document.createElement('span');
                    address.className = 'admin-relay-point-address';
                    address.textContent = point.line1 + ', ' + point.postal_code + ' ' + point.city;
                    button.appendChild(address);

                    button.addEventListener('click', function () {
                        relayList.querySelectorAll('.admin-relay-point').forEach(function (el) {
                            el.classList.remove('is-selected');
                        });
                        button.classList.add('is-selected');
                        fillShippingFromRelayPoint(point);
                    });

                    relayList.appendChild(button);
                });
                relayEmpty.hidden = points.length > 0;
            }

            function loadRelayPoints(postalCode, country, provider) {
                var cacheKey = provider + ':' + postalCode + ':' + country;
                if (cacheKey === relayPointsLoadedFor) {
                    return;
                }
                relayPointsLoadedFor = cacheKey;

                var url = relayPointsUrl + '?postal_code=' + encodeURIComponent(postalCode)
                    + '&country=' + encodeURIComponent(country || 'FR')
                    + '&provider=' + encodeURIComponent(provider);

                fetch(url, { headers: { Accept: 'application/json' } })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        renderRelayPoints((data && data.points) || []);
                    })
                    .catch(function () {});
            }

            function searchRelayPoints() {
                var carrier = selectedCarrierRadio();
                var provider = carrier ? RELAY_PROVIDERS[carrier.getAttribute('data-carrier-slug')] : null;

                if (! provider) {
                    return;
                }

                var postalCode = (relaySearchInput.value || '').trim();

                if (postalCode === '') {
                    relayList.innerHTML = '';
                    relayEmpty.hidden = true;
                    relayPointsLoadedFor = null;
                    return;
                }

                loadRelayPoints(postalCode, billingCountry.value, provider);
            }

            function syncRelayPicker() {
                var carrier = selectedCarrierRadio();
                var provider = carrier ? RELAY_PROVIDERS[carrier.getAttribute('data-carrier-slug')] : null;

                if (! provider) {
                    relayPicker.hidden = true;
                    relayPointsLoadedFor = null;
                    return;
                }

                relayPicker.hidden = false;

                if (! relaySearchTouched) {
                    relaySearchInput.value = billingPostalCode.value || '';
                }

                searchRelayPoints();
            }

            document.addEventListener('change', function (event) {
                if (event.target.matches('input[data-sync-field="carrier_id"]')) {
                    syncRelayPicker();
                }
            });
            billingPostalCode.addEventListener('input', syncRelayPicker);
            billingCountry.addEventListener('change', syncRelayPicker);
            syncRelayPicker();

            var discountType = document.getElementById('discount_type');
            var discountValueGroup = document.getElementById('discount-value-group');
            if (discountType && discountValueGroup) {
                discountType.addEventListener('change', function () {
                    discountValueGroup.hidden = discountType.value === '';
                });
            }

            var customerIdInput = document.getElementById('customer_id');
            var shippingPickerWrap = document.getElementById('saved-shipping-wrap');
            var billingPickerWrap = document.getElementById('saved-billing-wrap');
            var shippingPicker = document.getElementById('saved-shipping-address');
            var billingPicker = document.getElementById('saved-billing-address');
            var addressFields = {
                shipping: ['first_name', 'last_name', 'line1', 'line2', 'postal_code', 'city', 'country', 'phone'],
                billing: ['first_name', 'last_name', 'line1', 'line2', 'postal_code', 'city', 'country', 'phone']
            };

            function addressesForCustomer() {
                if (! modeExisting.checked || ! customerIdInput.value) {
                    return [];
                }
                return customerAddresses[customerIdInput.value] || customerAddresses[String(customerIdInput.value)] || [];
            }

            function fillPicker(select) {
                var addresses = addressesForCustomer();
                select.innerHTML = '<option value="">New address</option>';
                addresses.forEach(function (address) {
                    var option = document.createElement('option');
                    option.value = String(address.id);
                    option.textContent = address.label;
                    select.appendChild(option);
                });
            }

            function fillAddress(prefix, address) {
                addressFields[prefix].forEach(function (field) {
                    var input = document.getElementById(prefix + '_' + field);
                    if (input) {
                        input.value = address[field] || '';
                    }
                });
                if (prefix === 'billing') {
                    syncShippingNameFromBilling();
                }
                updateOrderPreview();
                syncSubmitButtons();
            }

            var billingFirstName = document.getElementById('billing_first_name');
            var billingLastName = document.getElementById('billing_last_name');
            var shippingFirstName = document.getElementById('shipping_first_name');
            var shippingLastName = document.getElementById('shipping_last_name');
            var shippingNameTouched = false;

            shippingFirstName.addEventListener('input', function () { shippingNameTouched = true; });
            shippingLastName.addEventListener('input', function () { shippingNameTouched = true; });

            function syncShippingNameFromBilling() {
                if (shippingNameTouched) {
                    return;
                }
                shippingFirstName.value = billingFirstName.value;
                shippingLastName.value = billingLastName.value;
            }

            billingFirstName.addEventListener('input', syncShippingNameFromBilling);
            billingLastName.addEventListener('input', syncShippingNameFromBilling);

            function syncAddressPickers(autofill) {
                var addresses = addressesForCustomer();
                var show = addresses.length > 0;
                shippingPickerWrap.hidden = ! show;
                billingPickerWrap.hidden = ! show;
                fillPicker(shippingPicker);
                fillPicker(billingPicker);

                if (! show || ! autofill) {
                    return;
                }

                var preferred = addresses.find(function (address) { return address.is_default; }) || addresses[0];
                billingPicker.value = String(preferred.id);
                fillAddress('billing', preferred);
            }

            customerIdInput.addEventListener('search-select:change', function () {
                syncAddressPickers(true);
            });

            shippingPicker.addEventListener('change', function () {
                var addresses = addressesForCustomer();
                var selected = addresses.find(function (address) {
                    return String(address.id) === shippingPicker.value;
                });
                if (selected) {
                    fillAddress('shipping', selected);
                }
            });

            billingPicker.addEventListener('change', function () {
                var addresses = addressesForCustomer();
                var selected = addresses.find(function (address) {
                    return String(address.id) === billingPicker.value;
                });
                if (selected) {
                    fillAddress('billing', selected);
                }
            });

            syncCustomerMode();

            var form = document.getElementById('manual-order-form');

            function fieldGroup(el) {
                return el ? el.closest('.form-group') : null;
            }

            function setFieldError(el, message) {
                var group = fieldGroup(el);
                if (! group) {
                    return;
                }

                var error = group.querySelector('.js-field-error');
                if (! error) {
                    error = document.createElement('p');
                    error.className = 'form-error js-field-error';
                    group.appendChild(error);
                }

                error.textContent = message || '';
                error.hidden = ! message;

                el.classList.toggle('is-invalid', Boolean(message));
                var search = group.querySelector('.search-select');
                if (search) {
                    search.classList.toggle('is-invalid', Boolean(message));
                }
            }

            function clearFieldError(el) {
                setFieldError(el, '');
            }

            function isBlank(el) {
                return ! el || String(el.value || '').trim() === '';
            }

            function validateRequired(el, message) {
                if (isBlank(el)) {
                    setFieldError(el, message);
                    return false;
                }
                setFieldError(el, '');
                return true;
            }

            function validateForm() {
                var ok = true;
                var firstInvalid = null;

                function fail(el, message) {
                    setFieldError(el, message);
                    if (! firstInvalid) {
                        firstInvalid = el;
                    }
                    ok = false;
                }

                if (modeExisting.checked) {
                    if (isBlank(customerIdInput)) {
                        fail(document.getElementById('customer_search'), 'Select a customer.');
                    } else {
                        clearFieldError(document.getElementById('customer_search'));
                    }
                } else {
                    var newFirstName = document.getElementById('new_customer_first_name');
                    var newLastName = document.getElementById('new_customer_last_name');
                    var newEmail = document.getElementById('new_customer_email');
                    if (isBlank(newFirstName)) {
                        fail(newFirstName, 'Enter a first name.');
                    } else {
                        clearFieldError(newFirstName);
                    }
                    if (isBlank(newLastName)) {
                        fail(newLastName, 'Enter a last name.');
                    } else {
                        clearFieldError(newLastName);
                    }
                    if (! isBlank(newEmail) && ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(newEmail.value.trim())) {
                        fail(newEmail, 'Enter a valid email.');
                    } else {
                        clearFieldError(newEmail);
                    }
                }

                var itemsError = document.querySelector('[data-error-for="items"]');
                var completeItems = 0;
                itemRows.querySelectorAll('.form-row--item').forEach(function (row) {
                    var product = row.querySelector('input[type="hidden"][name*="[product_id]"]');
                    var quantity = row.querySelector('input[name*="[quantity]"]');
                    var searchInput = row.querySelector('.search-select-input');

                    if (isBlank(product)) {
                        if (searchInput) {
                            clearFieldError(searchInput);
                        }
                        if (quantity) {
                            clearFieldError(quantity);
                        }
                        return;
                    }

                    completeItems++;
                    if (searchInput) {
                        clearFieldError(searchInput);
                    }
                    if (quantity && (isBlank(quantity) || Number(quantity.value) < 1)) {
                        fail(quantity, 'Enter a quantity.');
                    }

                    var variantGroup = row.querySelector('[data-variant-group]');
                    var variantSelect = row.querySelector('[data-item-variant]');
                    if (variantGroup && ! variantGroup.hidden && variantSelect && isBlank(variantSelect)) {
                        fail(variantSelect, 'Select a variant.');
                    } else if (variantSelect) {
                        clearFieldError(variantSelect);
                    }
                });

                if (completeItems === 0) {
                    if (itemsError) {
                        itemsError.textContent = 'Add at least one product.';
                        itemsError.hidden = false;
                    }
                    var firstSearch = itemRows.querySelector('.search-select-input');
                    if (firstSearch) {
                        fail(firstSearch, 'Select a product.');
                    }
                    ok = false;
                } else if (itemsError) {
                    itemsError.hidden = true;
                    itemsError.textContent = '';
                }

                if (isBlank(carrierSelect)) {
                    fail(carrierSelect, 'Select a carrier.');
                } else {
                    clearFieldError(carrierSelect);
                }

                ['first_name', 'last_name', 'line1', 'postal_code', 'city', 'country'].forEach(function (field) {
                    var input = document.getElementById('shipping_' + field);
                    if (! validateRequired(input, 'This field is required.')) {
                        if (! firstInvalid) {
                            firstInvalid = input;
                        }
                        ok = false;
                    }
                });

                ['first_name', 'last_name', 'line1', 'postal_code', 'city', 'country'].forEach(function (field) {
                    var input = document.getElementById('billing_' + field);
                    if (! validateRequired(input, 'This field is required.')) {
                        if (! firstInvalid) {
                            firstInvalid = input;
                        }
                        ok = false;
                    }
                });

                if (firstInvalid) {
                    firstInvalid.focus();
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }

                return ok;
            }

            function isFormComplete() {
                if (modeExisting.checked) {
                    if (isBlank(customerIdInput)) {
                        return false;
                    }
                } else {
                    var newFirstName = document.getElementById('new_customer_first_name');
                    var newLastName = document.getElementById('new_customer_last_name');
                    var newEmail = document.getElementById('new_customer_email');
                    if (isBlank(newFirstName) || isBlank(newLastName)) {
                        return false;
                    }
                    if (! isBlank(newEmail) && ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(newEmail.value.trim())) {
                        return false;
                    }
                }

                var completeItems = 0;
                var itemsOk = true;
                itemRows.querySelectorAll('.form-row--item').forEach(function (row) {
                    var product = row.querySelector('input[type="hidden"][name*="[product_id]"]');
                    var quantity = row.querySelector('input[name*="[quantity]"]');

                    if (isBlank(product)) {
                        return;
                    }

                    completeItems++;
                    if (isBlank(quantity) || Number(quantity.value) < 1) {
                        itemsOk = false;
                    }

                    var variantGroup = row.querySelector('[data-variant-group]');
                    var variantSelect = row.querySelector('[data-item-variant]');
                    if (variantGroup && ! variantGroup.hidden && variantSelect && isBlank(variantSelect)) {
                        itemsOk = false;
                    }
                });

                if (completeItems === 0 || ! itemsOk) {
                    return false;
                }

                if (isBlank(carrierSelect)) {
                    return false;
                }

                var requiredAddressFields = ['first_name', 'last_name', 'line1', 'postal_code', 'city', 'country'];
                for (var i = 0; i < requiredAddressFields.length; i++) {
                    if (isBlank(document.getElementById('shipping_' + requiredAddressFields[i]))) {
                        return false;
                    }
                    if (isBlank(document.getElementById('billing_' + requiredAddressFields[i]))) {
                        return false;
                    }
                }

                return true;
            }

            var draftButton = document.querySelector('button[name="action"][value="draft"]');
            var placeButton = document.querySelector('button[name="action"][value="placed"]');

            function syncSubmitButtons() {
                var ready = isFormComplete();
                if (draftButton) {
                    draftButton.disabled = ! ready;
                }
                if (placeButton) {
                    placeButton.disabled = ! ready;
                }
            }

            var marketplaceModal = document.getElementById('marketplace-confirm-modal');
            var marketplaceConfirmBtn = document.getElementById('marketplace-confirm-continue');
            var marketplaceConfirmed = false;
            var pendingSubmitter = null;

            form.addEventListener('submit', function (event) {
                if (! validateForm()) {
                    event.preventDefault();
                    return;
                }

                if (! marketplaceConfirmed && marketplaceModal && isBlank(document.getElementById('marketplace_id'))) {
                    event.preventDefault();
                    pendingSubmitter = event.submitter;
                    marketplaceModal.showModal();
                }
            });

            if (marketplaceConfirmBtn) {
                marketplaceConfirmBtn.addEventListener('click', function () {
                    var submitter = pendingSubmitter;
                    marketplaceConfirmed = true;
                    pendingSubmitter = null;
                    marketplaceModal.close();
                    form.requestSubmit(submitter);
                });
            }

            form.addEventListener('input', function (event) {
                var group = fieldGroup(event.target);
                if (! group) {
                    return;
                }
                var visible = group.querySelector('.search-select-input, .form-control');
                if (visible) {
                    clearFieldError(visible);
                }
            });

            form.addEventListener('change', function (event) {
                if (event.target.classList.contains('form-control')) {
                    clearFieldError(event.target);
                }
            });

            form.addEventListener('search-select:change', function (event) {
                var group = fieldGroup(event.target);
                var visible = group ? group.querySelector('.search-select-input') : null;
                if (visible) {
                    clearFieldError(visible);
                }
            });

            var productsCatalog = AdminSearchSelect.catalogs.products || [];
            var customersCatalog = AdminSearchSelect.catalogs.customers || [];

            function formatEuros(amount) {
                var value = Number(amount);
                if (isNaN(value)) {
                    return '—';
                }
                return value.toFixed(2).replace('.', ',') + ' €';
            }

            function productById(id) {
                return productsCatalog.find(function (item) {
                    return String(item.id) === String(id);
                }) || null;
            }

            function customerById(id) {
                return customersCatalog.find(function (item) {
                    return String(item.id) === String(id);
                }) || null;
            }

            function updateOrderPreview() {
                var customerEl = document.getElementById('order-preview-customer');
                var carrierEl = document.getElementById('order-preview-carrier');
                var marketplaceEl = document.getElementById('order-preview-marketplace');
                var itemsEl = document.getElementById('order-preview-items');
                var emptyEl = document.getElementById('order-preview-empty');
                var subtotalEl = document.getElementById('order-preview-subtotal');
                var discountRow = document.getElementById('order-preview-discount-row');
                var discountLabel = document.getElementById('order-preview-discount-label');
                var discountEl = document.getElementById('order-preview-discount');
                var shippingEl = document.getElementById('order-preview-shipping');
                var totalEl = document.getElementById('order-preview-total');
                var shippingAddressEl = document.getElementById('order-preview-shipping-address');
                var billingAddressEl = document.getElementById('order-preview-billing-address');

                if (! itemsEl) {
                    return;
                }

                if (modeNew.checked) {
                    var newName = [document.getElementById('new_customer_first_name').value, document.getElementById('new_customer_last_name').value].filter(Boolean).join(' ');
                    customerEl.textContent = newName !== '' ? newName : 'New customer';
                } else {
                    var customer = customerById(document.getElementById('customer_id').value);
                    customerEl.textContent = customer ? customer.label : '—';
                }

                var carrierRadio = document.querySelector('input[data-sync-field="carrier_id"]:checked');
                if (carrierRadio) {
                    var carrierName = carrierRadio.closest('.admin-choice').querySelector('.admin-table-strong');
                    carrierEl.textContent = carrierName ? carrierName.textContent : '—';
                } else {
                    carrierEl.textContent = '—';
                }

                var marketplaceRadio = document.querySelector('input[data-sync-field="marketplace_id"]:checked');
                marketplaceEl.textContent = marketplaceRadio && marketplaceRadio.value
                    ? marketplaceRadio.closest('.admin-choice').querySelector('.admin-table-strong').textContent
                    : 'None';

                itemsEl.innerHTML = '';
                var subtotal = 0;
                var hasItems = false;

                itemRows.querySelectorAll('.form-row--item').forEach(function (row) {
                    var productId = row.querySelector('input[type="hidden"][name$="[product_id]"]');
                    var qtyInput = row.querySelector('input[name$="[quantity]"]');
                    var priceInput = row.querySelector('[data-item-price]');
                    var variantSelect = row.querySelector('[data-item-variant]');
                    var product = productId ? productById(productId.value) : null;
                    var qty = qtyInput ? parseInt(qtyInput.value, 10) : 0;
                    var price = priceInput ? parseFloat(priceInput.value) : NaN;

                    if (! product || ! qty || qty < 1 || isNaN(price)) {
                        return;
                    }

                    hasItems = true;
                    var line = price * qty;
                    subtotal += line;

                    var variantText = '';
                    if (variantSelect && variantSelect.value && variantSelect.selectedOptions[0]) {
                        variantText = variantSelect.selectedOptions[0].textContent.split(' — ')[0];
                    }

                    var li = document.createElement('li');
                    li.innerHTML = '<div><p class="admin-order-preview-item-name"></p><p class="admin-order-preview-item-meta"></p></div><p class="admin-order-preview-item-total"></p>';
                    li.querySelector('.admin-order-preview-item-name').textContent = product.name || product.label;
                    li.querySelector('.admin-order-preview-item-meta').textContent = (variantText ? variantText + ' · ' : '') + qty + ' × ' + formatEuros(price);
                    li.querySelector('.admin-order-preview-item-total').textContent = formatEuros(line);
                    itemsEl.appendChild(li);
                });

                emptyEl.hidden = hasItems;
                subtotalEl.textContent = hasItems ? formatEuros(subtotal) : '—';

                var discountType = document.getElementById('discount_type').value;
                var discountValue = parseFloat(document.getElementById('discount_value').value);
                var discountAmount = 0;
                if (discountType && ! isNaN(discountValue) && discountValue > 0 && hasItems) {
                    discountAmount = discountType === 'percentage'
                        ? subtotal * discountValue / 100
                        : discountValue;
                    discountAmount = Math.min(discountAmount, subtotal);
                    discountRow.hidden = false;
                    discountLabel.textContent = discountType === 'percentage' ? 'Discount (−' + Math.round(discountValue) + '%)' : 'Discount';
                    discountEl.textContent = '−' + formatEuros(discountAmount);
                } else {
                    discountRow.hidden = true;
                }

                var shipping = parseFloat(document.getElementById('shipping_price').value);
                shippingEl.textContent = isNaN(shipping) ? '—' : formatEuros(shipping);

                var total = (hasItems ? subtotal : 0) - discountAmount + (isNaN(shipping) ? 0 : shipping);
                totalEl.textContent = hasItems || ! isNaN(shipping) ? formatEuros(total) : '—';

                function fieldValue(id) {
                    var el = document.getElementById(id);
                    return el ? String(el.value || '').trim() : '';
                }

                function fillPreviewAddress(root, prefix) {
                    if (! root) {
                        return;
                    }
                    var source = prefix;
                    var sameAsShipping = document.getElementById('billing-same-as-shipping');
                    if (prefix === 'billing' && sameAsShipping && sameAsShipping.checked) {
                        source = 'shipping';
                    }
                    ['first_name', 'last_name', 'line1', 'line2', 'postal_code', 'city', 'phone'].forEach(function (field) {
                        var dd = root.querySelector('[data-field="' + field + '"]');
                        if (dd) {
                            dd.textContent = fieldValue(source + '_' + field) || '—';
                        }
                    });
                    var countryDd = root.querySelector('[data-field="country"]');
                    var countrySelect = document.getElementById(source + '_country');
                    if (countryDd) {
                        countryDd.textContent = countrySelect && countrySelect.selectedOptions[0]
                            ? countrySelect.selectedOptions[0].textContent
                            : '—';
                    }
                }

                fillPreviewAddress(shippingAddressEl, 'shipping');
                fillPreviewAddress(billingAddressEl, 'billing');
            }

            form.addEventListener('input', updateOrderPreview);
            form.addEventListener('change', updateOrderPreview);
            form.addEventListener('search-select:change', updateOrderPreview);
            form.addEventListener('input', syncSubmitButtons);
            form.addEventListener('change', syncSubmitButtons);
            form.addEventListener('search-select:change', syncSubmitButtons);
            addItemBtn.addEventListener('click', function () {
                updateOrderPreview();
                syncSubmitButtons();
            });
            itemRows.addEventListener('click', function (event) {
                if (event.target.hasAttribute('data-remove-item')) {
                    updateOrderPreview();
                    syncSubmitButtons();
                }
            });
            updateOrderPreview();
            syncSubmitButtons();
        })();
    </script>
@endpush
