@extends('layouts.admin')

@section('title', $discountCode->exists ? 'Edit discount code' : 'Add discount code')

@section('content')
    @php
        $valueDefault = $discountCode->exists
            ? ($discountCode->type === 'percentage' ? $discountCode->value : number_format($discountCode->value / 100, 2, '.', ''))
            : '';
        $selectedType = old('type', $discountCode->type ?? 'percentage');
        $selectedValue = old('value', $valueDefault);
        $customerOptions = $customers->map(fn ($customer) => [
            'id' => $customer->id,
            'label' => $customer->name.' ('.$customer->email.')',
            'search' => $customer->name.' '.$customer->email,
        ])->values();
        $selectedCustomerId = old('user_id', $discountCode->user_id);
        $selectedCustomerLabel = optional($customerOptions->firstWhere('id', (int) $selectedCustomerId))['label'] ?? '';
    @endphp

    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker">
                        <a href="{{ route('admin.discounts.index', ['tab' => 'codes']) }}">Discounts</a>
                    </p>
                    <h2 class="admin-list-title">{{ $discountCode->exists ? 'Edit discount code' : 'Add discount code' }}</h2>
                    <p class="admin-list-lede">A code customers enter at checkout — applies to the whole cart total, not a single product.</p>
                </div>
                <a href="{{ route('admin.discounts.index', ['tab' => 'codes']) }}" class="btn btn-secondary">Back to discounts</a>
            </div>
        </header>

        <form
            method="POST"
            action="{{ $discountCode->exists ? route('admin.discount-codes.update', $discountCode) : route('admin.discount-codes.store') }}"
            class="admin-form-card admin-form-card--solo"
        >
            @csrf
            @if ($discountCode->exists)
                @method('PUT')
            @endif

            <div class="form-group">
                <label for="code">Code</label>
                <div class="discount-code-field">
                    <input
                        type="text"
                        id="code"
                        name="code"
                        class="form-control"
                        value="{{ old('code', $discountCode->code) }}"
                        maxlength="40"
                        placeholder="e.g. SUMMER10"
                        style="text-transform: uppercase;"
                        required
                    >
                    <button type="button" class="btn btn-secondary" id="generate-code-btn">Generate</button>
                </div>
                <p class="form-hint">Letters, numbers, and hyphens only. Not case-sensitive — stored uppercase.</p>
                @error('code') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Type</label>
                    <div class="admin-choice-row">
                        <label class="admin-choice">
                            <input type="radio" name="type" value="percentage" @checked($selectedType === 'percentage')>
                            <span class="discount-type-copy">
                                <span class="admin-table-strong">Percentage</span>
                                <span class="admin-table-sub">e.g. 10% off the cart</span>
                            </span>
                        </label>
                        <label class="admin-choice">
                            <input type="radio" name="type" value="fixed" @checked($selectedType === 'fixed')>
                            <span class="discount-type-copy">
                                <span class="admin-table-strong">Fixed amount</span>
                                <span class="admin-table-sub">e.g. 5,00 € off the cart</span>
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
                        <span class="discount-value-suffix" id="discount-code-value-suffix">{{ $selectedType === 'fixed' ? '€' : '%' }}</span>
                    </div>
                    @error('value') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="form-group">
                <label for="user_id">Customer</label>
                <div class="search-select" data-search-select data-source="customers">
                    <input type="hidden" name="user_id" id="discount-code-user-id" value="{{ $selectedCustomerId }}">
                    <input
                        type="text"
                        id="user_id"
                        class="form-control search-select-input"
                        placeholder="Any customer…"
                        value="{{ $selectedCustomerLabel }}"
                        autocomplete="off"
                        spellcheck="false"
                    >
                    <ul class="search-select-list" hidden></ul>
                </div>
                <p class="form-hint">Leave blank to let any customer use this code.</p>
                @error('user_id') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-group">
                <label for="quantity">Quantity available</label>
                <input
                    type="number"
                    id="quantity"
                    name="quantity"
                    class="form-control"
                    value="{{ old('quantity', $discountCode->quantity) }}"
                    min="1"
                    step="1"
                    placeholder="Unlimited"
                >
                <p class="form-hint">Leave blank for unlimited uses.</p>
                @error('quantity') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">{{ $discountCode->exists ? 'Save changes' : 'Create code' }}</button>
                <a href="{{ route('admin.discounts.index', ['tab' => 'codes']) }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/admin-search-select.js') }}"></script>
    <script>
        AdminSearchSelect.catalogs.customers = @json($customerOptions);
        AdminSearchSelect.mountAll();

        (function () {
            var suffix = document.getElementById('discount-code-value-suffix');

            function updateSuffix() {
                var checked = document.querySelector('input[name="type"]:checked');
                suffix.textContent = checked && checked.value === 'fixed' ? '€' : '%';
            }

            document.querySelectorAll('input[name="type"]').forEach(function (input) {
                input.addEventListener('change', updateSuffix);
            });
        })();

        (function () {
            var button = document.getElementById('generate-code-btn');
            var input = document.getElementById('code');

            if (!button || !input) {
                return;
            }

            // Excludes 0/O, 1/I/L — characters easily confused with each other.
            var chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

            function randomCode() {
                var code = '';
                for (var i = 0; i < 8; i++) {
                    code += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                return code;
            }

            function checkExists(code) {
                return fetch('{{ route('admin.discount-codes.check-code') }}?code=' + encodeURIComponent(code))
                    .then(function (response) { return response.json(); })
                    .then(function (data) { return !!data.exists; });
            }

            function generate(attempt) {
                var code = randomCode();

                checkExists(code).then(function (exists) {
                    if (exists && attempt < 10) {
                        generate(attempt + 1);
                        return;
                    }

                    input.value = code;
                    button.disabled = false;
                    button.textContent = 'Generate';
                });
            }

            button.addEventListener('click', function () {
                button.disabled = true;
                button.textContent = 'Generating…';
                generate(0);
            });
        })();
    </script>
@endpush
