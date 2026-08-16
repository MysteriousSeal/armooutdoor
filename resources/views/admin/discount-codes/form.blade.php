@extends('layouts.admin')

@section('title', $discountCode->exists ? 'Edit discount code' : 'Add discount code')

@section('content')
    @php
        $valueDefault = $discountCode->exists
            ? ($discountCode->type === 'percentage' ? $discountCode->value : number_format($discountCode->value / 100, 2, '.', ''))
            : '';
        $selectedType = old('type', $discountCode->type ?? 'percentage');
        $selectedValue = old('value', $valueDefault);
        $selectedCode = old('code', $discountCode->code);
        $selectedQuantity = old('quantity', $discountCode->quantity);
        $selectedMaxUses = old('max_uses_per_customer', $discountCode->max_uses_per_customer);
        $defaultDeadline = ($discountCode->exists ? $discountCode->created_at : now())->copy()->addDays(30)->setTime(23, 59);
        $selectedEndsAt = old('ends_at', optional($discountCode->ends_at)->format('Y-m-d\TH:i') ?: $defaultDeadline->format('Y-m-d\TH:i'));
        $customerOptions = $customers->map(fn ($customer) => [
            'id' => $customer->id,
            'label' => trim($customer->first_name.' '.$customer->last_name),
            'name' => trim($customer->first_name.' '.$customer->last_name),
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'email' => $customer->email,
            'search' => trim($customer->first_name.' '.$customer->last_name).' '.$customer->email,
        ])->values();
        $selectedCustomerId = old('user_id', $discountCode->user_id);
        $selectedCustomer = $customerOptions->firstWhere('id', (int) $selectedCustomerId);
        $selectedCustomerLabel = $selectedCustomer['label'] ?? '';
        $previewBadge = '';
        if ($selectedValue !== '' && is_numeric($selectedValue)) {
            $previewBadge = $selectedType === 'percentage'
                ? '-'.(int) round((float) $selectedValue).'%'
                : '-'.format_euros((int) round(((float) $selectedValue) * 100));
        }
    @endphp

    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker">
                        <a href="{{ route('admin.discounts.index', ['tab' => 'codes']) }}">Discount codes</a>
                    </p>
                    <h2 class="admin-list-title">{{ $discountCode->exists ? 'Edit discount code' : 'Add discount code' }}</h2>
                    <p class="admin-list-lede">A code customers enter at checkout, taken off the whole cart total — not a single product.</p>
                </div>
                <a href="{{ route('admin.discounts.index', ['tab' => 'codes']) }}" class="btn btn-secondary">Back to codes</a>
            </div>
        </header>

        <form
            method="POST"
            action="{{ $discountCode->exists ? route('admin.discount-codes.update', $discountCode) : route('admin.discount-codes.store') }}"
            class="admin-discount-form"
            id="discount-code-form"
        >
            @csrf
            @if ($discountCode->exists)
                @method('PUT')
            @endif

            <div class="admin-order-create-grid">
                <div class="order-main">
                    <section class="order-panel">
                        <h3 class="order-panel-title">Code</h3>
                        <div class="form-group">
                            <label for="code">Code</label>
                            <div class="discount-code-field">
                                <input
                                    type="text"
                                    id="code"
                                    name="code"
                                    class="form-control"
                                    value="{{ $selectedCode }}"
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
                            <p class="form-hint" id="discount-code-value-hint">
                                {{ $selectedType === 'fixed' ? 'Euro amount to subtract from the cart, for example 5.00.' : 'Percentage to subtract from the cart, for example 10.' }}
                            </p>
                            @error('value') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </section>

                    <section class="order-panel">
                        <h3 class="order-panel-title">Limits</h3>
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

                        <div class="form-row">
                            <div class="form-group">
                                <label for="quantity">Quantity available</label>
                                <input
                                    type="number"
                                    id="quantity"
                                    name="quantity"
                                    class="form-control"
                                    value="{{ $selectedQuantity }}"
                                    min="1"
                                    step="1"
                                    placeholder="Unlimited"
                                >
                                <p class="form-hint">Leave blank for unlimited uses.</p>
                                @error('quantity') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="form-group">
                                <label for="max_uses_per_customer">Max uses per customer</label>
                                <input
                                    type="number"
                                    id="max_uses_per_customer"
                                    name="max_uses_per_customer"
                                    class="form-control"
                                    value="{{ $selectedMaxUses }}"
                                    min="1"
                                    step="1"
                                    placeholder="Unlimited"
                                >
                                <p class="form-hint">Leave blank for no per-customer cap. Cannot exceed the total quantity.</p>
                                @error('max_uses_per_customer') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="ends_at">Deadline</label>
                            <input
                                type="datetime-local"
                                id="ends_at"
                                name="ends_at"
                                class="form-control"
                                value="{{ $selectedEndsAt }}"
                            >
                            <p class="form-hint">Defaults to 30 days from now at 23:59. Clear it for no deadline.</p>
                            @error('ends_at') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </section>
                </div>

                <aside>
                    <section class="order-panel admin-discount-preview">
                        <h3 class="order-panel-title">Preview</h3>

                        <div class="admin-discount-code-preview" id="discount-code-preview">
                            <p class="admin-discount-code-preview-code" id="discount-code-preview-code">
                                {{ $selectedCode !== '' && $selectedCode !== null ? strtoupper($selectedCode) : 'YOURCODE' }}
                            </p>
                            <div class="admin-discount-preview-meta">
                                <span class="order-discount-badge" id="discount-code-preview-badge">
                                    {{ $previewBadge !== '' ? $previewBadge : '—' }}
                                </span>
                                <p class="admin-discount-preview-window" id="discount-code-preview-type">
                                    {{ $selectedType === 'fixed' ? 'Fixed amount off · cart total' : 'Percentage off · cart total' }}
                                </p>
                                <p class="admin-discount-remaining" id="discount-code-preview-customer">
                                    {{ $selectedCustomer['name'] ?? 'Any customer' }}
                                </p>
                                <p class="admin-discount-preview-window" id="discount-code-preview-email" @unless($selectedCustomer['email'] ?? false) hidden @endunless>
                                    {{ $selectedCustomer['email'] ?? '' }}
                                </p>
                                <p class="admin-discount-preview-window" id="discount-code-preview-limits">
                                    {{ $selectedQuantity ? $selectedQuantity.' remaining' : 'Unlimited uses' }}
                                    ·
                                    {{ $selectedMaxUses ? 'Max '.$selectedMaxUses.' per customer' : 'No per-customer limit' }}
                                </p>
                                <p class="admin-discount-preview-window" id="discount-code-preview-deadline">
                                    {{ $selectedEndsAt ? 'Expires '.\Illuminate\Support\Carbon::parse($selectedEndsAt)->format('d M Y · H:i') : 'No deadline' }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <div class="order-panel admin-order-create-actions">
                        <a href="{{ route('admin.discounts.index', ['tab' => 'codes']) }}" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">{{ $discountCode->exists ? 'Save changes' : 'Create code' }}</button>
                    </div>
                </aside>
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
            var customers = AdminSearchSelect.catalogs.customers || [];
            var codeInput = document.getElementById('code');
            var valueInput = document.getElementById('value');
            var customerInput = document.getElementById('discount-code-user-id');
            var quantityInput = document.getElementById('quantity');
            var maxPerCustomerInput = document.getElementById('max_uses_per_customer');
            var endsAtInput = document.getElementById('ends_at');
            var suffix = document.getElementById('discount-code-value-suffix');
            var hint = document.getElementById('discount-code-value-hint');
            var previewCode = document.getElementById('discount-code-preview-code');
            var previewBadge = document.getElementById('discount-code-preview-badge');
            var previewType = document.getElementById('discount-code-preview-type');
            var previewCustomer = document.getElementById('discount-code-preview-customer');
            var previewEmail = document.getElementById('discount-code-preview-email');
            var previewLimits = document.getElementById('discount-code-preview-limits');
            var previewDeadline = document.getElementById('discount-code-preview-deadline');

            function selectedType() {
                var checked = document.querySelector('input[name="type"]:checked');
                return checked ? checked.value : 'percentage';
            }

            function customerById(id) {
                return customers.find(function (item) {
                    return String(item.id) === String(id);
                }) || null;
            }

            function formatEuros(cents) {
                return (Math.max(0, cents) / 100).toFixed(2).replace('.', ',') + ' €';
            }

            function updatePreview() {
                var isFixed = selectedType() === 'fixed';
                suffix.textContent = isFixed ? '€' : '%';
                hint.textContent = isFixed
                    ? 'Euro amount to subtract from the cart, for example 5.00.'
                    : 'Percentage to subtract from the cart, for example 10.';

                var code = (codeInput.value || '').trim().toUpperCase();
                previewCode.textContent = code !== '' ? code : 'YOURCODE';

                var raw = parseFloat(valueInput.value);
                if (! isNaN(raw) && raw > 0) {
                    previewBadge.textContent = isFixed
                        ? '-' + formatEuros(Math.round(raw * 100))
                        : '-' + Math.round(raw) + '%';
                } else {
                    previewBadge.textContent = '—';
                }

                previewType.textContent = isFixed
                    ? 'Fixed amount off · cart total'
                    : 'Percentage off · cart total';

                var customer = customerById(customerInput.value);
                previewCustomer.textContent = customer ? customer.name : 'Any customer';
                previewEmail.textContent = customer && customer.email ? customer.email : '';
                previewEmail.hidden = ! (customer && customer.email);

                var quantity = quantityInput.value;
                var maxUses = maxPerCustomerInput.value;
                previewLimits.textContent = (quantity ? quantity + ' remaining' : 'Unlimited uses')
                    + ' · '
                    + (maxUses ? 'Max ' + maxUses + ' per customer' : 'No per-customer limit');

                var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                var endsAtValue = endsAtInput.value;
                if (endsAtValue) {
                    var parsed = new Date(endsAtValue);
                    var pad = function (n) { return String(n).padStart(2, '0'); };
                    previewDeadline.textContent = 'Expires ' + pad(parsed.getDate()) + ' ' + months[parsed.getMonth()] + ' ' + parsed.getFullYear() + ' · ' + pad(parsed.getHours()) + ':' + pad(parsed.getMinutes());
                } else {
                    previewDeadline.textContent = 'No deadline';
                }
            }

            document.querySelectorAll('input[name="type"]').forEach(function (input) {
                input.addEventListener('change', updatePreview);
            });
            codeInput.addEventListener('input', updatePreview);
            valueInput.addEventListener('input', updatePreview);
            customerInput.addEventListener('search-select:change', updatePreview);
            customerInput.addEventListener('change', updatePreview);
            quantityInput.addEventListener('input', updatePreview);
            maxPerCustomerInput.addEventListener('input', updatePreview);
            endsAtInput.addEventListener('input', updatePreview);

            updatePreview();
        })();

        (function () {
            var button = document.getElementById('generate-code-btn');
            var input = document.getElementById('code');

            if (!button || !input) {
                return;
            }

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
                    input.dispatchEvent(new Event('input'));
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

        (function () {
            var quantityInput = document.getElementById('quantity');
            var maxPerCustomerInput = document.getElementById('max_uses_per_customer');

            function syncMax() {
                if (quantityInput.value) {
                    maxPerCustomerInput.max = quantityInput.value;

                    if (Number(maxPerCustomerInput.value) > Number(quantityInput.value)) {
                        maxPerCustomerInput.value = quantityInput.value;
                        maxPerCustomerInput.dispatchEvent(new Event('input'));
                    }
                } else {
                    maxPerCustomerInput.removeAttribute('max');
                }
            }

            quantityInput.addEventListener('input', syncMax);
            syncMax();
        })();
    </script>
@endpush
