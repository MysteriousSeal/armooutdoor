@extends('layouts.admin')

@section('title', 'Create manual order')

@php
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
    ])->values();
    $selectedCustomerLabel = optional($customerOptions->firstWhere('id', (int) old('customer_id')))['label'] ?? '';
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
                    <p class="admin-list-kicker"><a href="{{ route('admin.orders.index') }}">Orders</a></p>
                    <h2 class="admin-list-title">Create manual order</h2>
                    <p class="admin-list-lede">Log a sale made outside the site — pick products, then a shipping and billing address.</p>
                </div>
                <a href="{{ route('admin.orders.index') }}" class="btn btn-secondary">Back to orders</a>
            </div>
        </header>

        <form method="POST" action="{{ route('admin.orders.store') }}" class="admin-form-card admin-order-create" id="manual-order-form" novalidate>
            @csrf

            <div class="admin-order-create-grid">
                <div class="admin-order-create-col">
                    <section class="admin-order-create-section">
                        <h3 class="admin-panel-title">Customer</h3>

                        <div class="admin-choice-row">
                            <label class="admin-choice {{ old('customer_mode', 'existing') === 'existing' ? 'is-selected' : '' }}">
                                <input type="radio" name="customer_mode" value="existing" id="customer-mode-existing" @checked(old('customer_mode', 'existing') === 'existing')>
                                <span>Existing customer</span>
                            </label>
                            <label class="admin-choice {{ old('customer_mode') === 'new' ? 'is-selected' : '' }}">
                                <input type="radio" name="customer_mode" value="new" id="customer-mode-new" @checked(old('customer_mode') === 'new')>
                                <span>New external customer</span>
                            </label>
                        </div>
                        <p class="form-hint">External customers are not listed in the shop customers page.</p>

                        <div class="form-group" id="customer-existing-fields">
                            <label for="customer_search">Customer</label>
                            <div class="search-select" data-search-select data-source="customers">
                                <input type="hidden" name="customer_id" id="customer_id" value="{{ old('customer_id') }}">
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

                        <div class="form-row" id="customer-new-fields">
                            <div class="form-group">
                                <label for="new_customer_name">Name</label>
                                <input type="text" id="new_customer_name" name="new_customer_name" class="form-control" value="{{ old('new_customer_name') }}" maxlength="160">
                                @error('new_customer_name') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="form-group">
                                <label for="new_customer_email">Email</label>
                                <input type="email" id="new_customer_email" name="new_customer_email" class="form-control" value="{{ old('new_customer_email') }}" maxlength="160">
                                @error('new_customer_email') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </section>

                    <section class="admin-order-create-section">
                        <h3 class="admin-panel-title">Products</h3>
                        <p class="form-error js-field-error" data-error-for="items" @unless($errors->has('items')) hidden @endunless>
                            {{ $errors->first('items') }}
                        </p>

                        <div id="item-rows">
                            @php($oldItems = old('items', [['product_id' => '', 'quantity' => 1], ['product_id' => '', 'quantity' => 1]]))
                            @foreach ($oldItems as $index => $item)
                                @php($selectedProductLabel = optional($productOptions->firstWhere('id', (int) ($item['product_id'] ?? 0)))['label'] ?? '')
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

                    <section class="admin-order-create-section">
                        <h3 class="admin-panel-title">Carrier</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="carrier_id">Carrier</label>
                                <select id="carrier_id" name="carrier_id" class="form-control">
                                    <option value="" data-price="">— Select a carrier —</option>
                                    @foreach ($carriers as $carrier)
                                        <option
                                            value="{{ $carrier->id }}"
                                            data-price="{{ number_format($carrier->price_cents / 100, 2, '.', '') }}"
                                            @selected((string) old('carrier_id') === (string) $carrier->id)
                                        >
                                            {{ $carrier->localizedName() }} — {{ $carrier->formattedPrice() }} ({{ $carrier->method->value }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('carrier_id') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="form-group">
                                <label for="shipping_price">Shipping (€)</label>
                                <input
                                    type="number"
                                    id="shipping_price"
                                    name="shipping_price"
                                    class="form-control"
                                    value="{{ old('shipping_price') }}"
                                    min="0"
                                    step="0.01"
                                >
                                @error('shipping_price') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </section>
                </div>

                <div class="admin-order-create-col">
                    <section class="admin-order-create-section">
                        <h3 class="admin-panel-title">Shipping address</h3>
                        <div class="form-group" id="saved-shipping-wrap" hidden>
                            <label for="saved-shipping-address">Saved address</label>
                            <select id="saved-shipping-address" class="form-control" data-address-picker="shipping">
                                <option value="">New address</option>
                            </select>
                        </div>
                        @include('admin.orders.partials.address-fields', ['snapshot' => [], 'prefix' => 'shipping', 'bag' => 'default'])
                    </section>

                    <section class="admin-order-create-section">
                        <h3 class="admin-panel-title">Billing address</h3>
                        <div class="form-group">
                            <label class="form-check">
                                <input type="checkbox" name="billing_same_as_shipping" value="1" id="billing-same-as-shipping" @checked(old('billing_same_as_shipping', true))>
                                Same as shipping address
                            </label>
                        </div>
                        <div id="billing-address-fields" @if (old('billing_same_as_shipping', true)) hidden @endif>
                            <div class="form-group" id="saved-billing-wrap" hidden>
                                <label for="saved-billing-address">Saved address</label>
                                <select id="saved-billing-address" class="form-control" data-address-picker="billing">
                                    <option value="">New address</option>
                                </select>
                            </div>
                            @include('admin.orders.partials.address-fields', ['snapshot' => [], 'prefix' => 'billing', 'bag' => 'default', 'fieldPrefix' => 'billing_'])
                        </div>
                    </section>
                </div>
            </div>

            <div class="form-actions admin-order-create-actions">
                <a href="{{ route('admin.orders.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Create order</button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/admin-search-select.js') }}"></script>
    <script>
        AdminSearchSelect.catalogs.customers = @json($customerOptions);
        AdminSearchSelect.catalogs.products = @json($productOptions);
        AdminSearchSelect.mountAll();
        var customerAddresses = @json($customerAddresses);

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

            itemRows.addEventListener('search-select:change', function (event) {
                var row = event.target.closest('.form-row--item');
                if (! row || ! event.detail) {
                    return;
                }
                var price = row.querySelector('[data-item-price]');
                if (price && event.detail.price != null) {
                    price.value = event.detail.price;
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
                document.querySelectorAll('.admin-choice').forEach(function (choice) {
                    choice.classList.toggle('is-selected', choice.querySelector('input').checked);
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

            var billingSame = document.getElementById('billing-same-as-shipping');
            var billingFields = document.getElementById('billing-address-fields');

            billingSame.addEventListener('change', function () {
                billingFields.hidden = billingSame.checked;
            });

            var carrierSelect = document.getElementById('carrier_id');
            var shippingPrice = document.getElementById('shipping_price');

            carrierSelect.addEventListener('change', function () {
                var selected = carrierSelect.options[carrierSelect.selectedIndex];
                shippingPrice.value = selected ? (selected.getAttribute('data-price') || '') : '';
            });

            if (shippingPrice.value === '' && carrierSelect.value !== '') {
                var current = carrierSelect.options[carrierSelect.selectedIndex];
                if (current) {
                    shippingPrice.value = current.getAttribute('data-price') || '';
                }
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
            }

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
                shippingPicker.value = String(preferred.id);
                fillAddress('shipping', preferred);
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
                    var newName = document.getElementById('new_customer_name');
                    var newEmail = document.getElementById('new_customer_email');
                    if (isBlank(newName)) {
                        fail(newName, 'Enter a name.');
                    } else {
                        clearFieldError(newName);
                    }
                    if (isBlank(newEmail)) {
                        fail(newEmail, 'Enter an email.');
                    } else if (! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(newEmail.value.trim())) {
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

                ['first_name', 'last_name', 'line1', 'postal_code', 'city', 'country', 'phone'].forEach(function (field) {
                    var input = document.getElementById('shipping_' + field);
                    if (! validateRequired(input, 'This field is required.')) {
                        if (! firstInvalid) {
                            firstInvalid = input;
                        }
                        ok = false;
                    }
                });

                if (! billingSame.checked) {
                    ['first_name', 'last_name', 'line1', 'postal_code', 'city', 'country', 'phone'].forEach(function (field) {
                        var input = document.getElementById('billing_' + field);
                        if (! validateRequired(input, 'This field is required.')) {
                            if (! firstInvalid) {
                                firstInvalid = input;
                            }
                            ok = false;
                        }
                    });
                }

                if (firstInvalid) {
                    firstInvalid.focus();
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }

                return ok;
            }

            form.addEventListener('submit', function (event) {
                if (! validateForm()) {
                    event.preventDefault();
                }
            });

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
        })();
    </script>
@endpush
