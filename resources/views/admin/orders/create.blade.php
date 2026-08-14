@extends('layouts.admin')

@section('title', 'Create manual order')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker"><a href="{{ route('admin.orders.index') }}">Orders</a></p>
                    <h2 class="admin-list-title">Create manual order</h2>
                    <p class="admin-list-lede">Log a sale made outside the site — pick products, then a shipping and billing address.</p>
                </div>
            </div>
        </header>

        <form method="POST" action="{{ route('admin.orders.store') }}" class="admin-form-card admin-form-card--solo" id="manual-order-form">
            @csrf

            <h3 class="admin-panel-title">Customer</h3>

            <div class="form-group">
                <label class="form-check">
                    <input type="radio" name="customer_mode" value="existing" id="customer-mode-existing" @checked(old('customer_mode', 'existing') === 'existing')>
                    Existing customer
                </label>
                <label class="form-check">
                    <input type="radio" name="customer_mode" value="new" id="customer-mode-new" @checked(old('customer_mode') === 'new')>
                    New external customer (not shown in the customers list)
                </label>
            </div>

            <div class="form-group" id="customer-existing-fields">
                <label for="customer_id">Customer</label>
                <select id="customer_id" name="customer_id" class="form-control">
                    <option value="">— Select a customer —</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected((string) old('customer_id') === (string) $customer->id)>
                            {{ $customer->name }} ({{ $customer->email }})
                        </option>
                    @endforeach
                </select>
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

            <h3 class="admin-panel-title">Products</h3>

            @error('items') <p class="form-error">{{ $message }}</p> @enderror

            <div id="item-rows">
                @php($oldItems = old('items', [['product_id' => '', 'quantity' => 1], ['product_id' => '', 'quantity' => 1]]))
                @foreach ($oldItems as $index => $item)
                    <div class="form-row form-row--item">
                        <div class="form-group">
                            <label class="sr-only" for="item-product-{{ $index }}">Product</label>
                            <select id="item-product-{{ $index }}" name="items[{{ $index }}][product_id]" class="form-control">
                                <option value="">— Select a product —</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}" @selected((string) ($item['product_id'] ?? '') === (string) $product->id)>
                                        {{ $product->localizedName() }} — {{ $product->formattedPrice() }} ({{ $product->quantity }} in stock)
                                    </option>
                                @endforeach
                            </select>
                            @error('items.'.$index.'.product_id') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="form-group">
                            <label class="sr-only" for="item-qty-{{ $index }}">Quantity</label>
                            <input type="number" id="item-qty-{{ $index }}" name="items[{{ $index }}][quantity]" class="form-control" value="{{ $item['quantity'] ?? 1 }}" min="1">
                            @error('items.'.$index.'.quantity') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary" data-remove-item>Remove</button>
                    </div>
                @endforeach
            </div>
            <button type="button" class="btn btn-sm btn-secondary" id="add-item-row">Add another product</button>

            <h3 class="admin-panel-title">Carrier</h3>
            <div class="form-group">
                <label for="carrier_id">Carrier</label>
                <select id="carrier_id" name="carrier_id" class="form-control">
                    <option value="">— Select a carrier —</option>
                    @foreach ($carriers as $carrier)
                        <option value="{{ $carrier->id }}" @selected((string) old('carrier_id') === (string) $carrier->id)>
                            {{ $carrier->localizedName() }} — {{ $carrier->formattedPrice() }} ({{ $carrier->method->value }})
                        </option>
                    @endforeach
                </select>
                @error('carrier_id') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <h3 class="admin-panel-title">Shipping address</h3>
            @include('admin.orders.partials.address-fields', ['snapshot' => [], 'prefix' => 'shipping', 'bag' => 'default'])

            <h3 class="admin-panel-title">Billing address</h3>
            <div class="form-group">
                <label class="form-check">
                    <input type="checkbox" name="billing_same_as_shipping" value="1" id="billing-same-as-shipping" @checked(old('billing_same_as_shipping', true))>
                    Same as shipping address
                </label>
            </div>
            <div id="billing-address-fields" @if (old('billing_same_as_shipping', true)) hidden @endif>
                @include('admin.orders.partials.address-fields', ['snapshot' => [], 'prefix' => 'billing', 'bag' => 'default', 'fieldPrefix' => 'billing_'])
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Create order</button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var itemRows = document.getElementById('item-rows');
            var addItemBtn = document.getElementById('add-item-row');
            var itemCounter = itemRows.querySelectorAll('.form-row--item').length;
            var productOptionsHtml = itemRows.querySelector('select').innerHTML;

            addItemBtn.addEventListener('click', function () {
                var row = document.createElement('div');
                row.className = 'form-row form-row--item';
                row.innerHTML =
                    '<div class="form-group">' +
                    '<label class="sr-only">Product</label>' +
                    '<select name="items[' + itemCounter + '][product_id]" class="form-control">' + productOptionsHtml + '</select>' +
                    '</div>' +
                    '<div class="form-group">' +
                    '<label class="sr-only">Quantity</label>' +
                    '<input type="number" name="items[' + itemCounter + '][quantity]" class="form-control" value="1" min="1">' +
                    '</div>' +
                    '<button type="button" class="btn btn-sm btn-secondary" data-remove-item>Remove</button>';
                itemRows.appendChild(row);
                itemCounter++;
            });

            itemRows.addEventListener('click', function (event) {
                if (event.target.hasAttribute('data-remove-item')) {
                    event.target.closest('.form-row--item').remove();
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
            }

            modeExisting.addEventListener('change', syncCustomerMode);
            modeNew.addEventListener('change', syncCustomerMode);
            syncCustomerMode();

            var billingSame = document.getElementById('billing-same-as-shipping');
            var billingFields = document.getElementById('billing-address-fields');

            billingSame.addEventListener('change', function () {
                billingFields.hidden = billingSame.checked;
            });
        })();
    </script>
@endpush
