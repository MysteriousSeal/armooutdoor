@extends('layouts.admin')

@section('title', 'Shipping settings')

@section('content')
    @php
        $thresholdValue = old(
            'free_shipping_threshold',
            $setting->free_shipping_threshold_cents !== null
                ? number_format($setting->free_shipping_threshold_cents / 100, 2, '.', '')
                : ''
        );
        $selectedCarrierIds = old('free_shipping_carrier_ids', $setting->free_shipping_carrier_ids ?? []);
    @endphp

    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker"><a href="{{ route('admin.settings.index') }}">Settings</a></p>
                    <h2 class="admin-list-title">Shipping</h2>
                    <p class="admin-list-lede">Free shipping rules, carrier prices, and package types.</p>
                </div>
            </div>
        </header>

        <div class="admin-order-create-grid">
            <form
                method="POST"
                action="{{ route('admin.settings.shipping.update') }}"
                class="admin-form-card"
            >
                @csrf
                @method('PUT')

                <h3 class="admin-panel-title">Free shipping</h3>

                <div class="form-group">
                    <label for="free_shipping_threshold">Free shipping above (EUR)</label>
                    <input
                        type="number"
                        id="free_shipping_threshold"
                        name="free_shipping_threshold"
                        class="form-control"
                        value="{{ $thresholdValue }}"
                        min="0"
                        max="99999.99"
                        step="0.01"
                        placeholder="e.g. 50.00"
                    >
                    <p class="form-hint">Leave blank to disable free shipping entirely.</p>
                    @error('free_shipping_threshold') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="form-group">
                    <label>Eligible carriers</label>
                    <p class="form-hint">Shipping is free on these carriers once the order subtotal reaches the amount above. Other carriers keep their normal price.</p>

                    <div class="admin-check-list">
                        @foreach ($carriers as $carrier)
                            <label class="form-check">
                                <input
                                    type="checkbox"
                                    name="free_shipping_carrier_ids[]"
                                    value="{{ $carrier->id }}"
                                    @checked(in_array($carrier->id, $selectedCarrierIds))
                                >
                                {{ $carrier->localizedName() }}
                                <span class="admin-check-list-meta">— {{ $carrier->formattedStartingPrice() }}, {{ $carrier->method->value }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('free_shipping_carrier_ids') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </form>

            <div class="admin-form-card">
                <h3 class="admin-panel-title">Carrier prices</h3>
                <p class="form-hint">Priced by weight tier. Falls back to a flat price until a carrier has tiers of its own.</p>

                <div class="admin-check-list admin-check-list--rows">
                    @foreach ($carriers as $carrier)
                        <div class="admin-check-list-row">
                            <div>
                                <span class="admin-table-strong">{{ $carrier->localizedName() }}</span>
                                <span class="admin-check-list-meta">— from {{ $carrier->formattedStartingPrice() }}, {{ $carrier->method->value }}</span>
                            </div>
                            <button type="button" class="btn btn-sm btn-secondary" data-modal-open="carrier-price-tiers-{{ $carrier->id }}">Edit price</button>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        @foreach ($carriers as $carrier)
            @php
                $oldTiers = old(
                    'tiers',
                    $carrier->priceTiers->map(fn ($tier) => [
                        'min_weight' => $tier->min_weight_grams,
                        'price' => number_format($tier->price_cents / 100, 2, '.', ''),
                    ])->all(),
                );
                $tierErrorBag = $errors->{'carrierTiers'.$carrier->id};
            @endphp
            <dialog
                id="carrier-price-tiers-{{ $carrier->id }}"
                class="modal modal--form"
                aria-labelledby="carrier-price-tiers-{{ $carrier->id }}-title"
                @if ($tierErrorBag->any()) data-autoopen @endif
            >
                <form method="POST" action="{{ route('admin.settings.carriers.price-tiers.update', $carrier) }}">
                    @csrf
                    @method('PUT')
                    <p class="modal-kicker">{{ $carrier->localizedName() }}</p>
                    <h3 class="modal-title" id="carrier-price-tiers-{{ $carrier->id }}-title">Price tiers</h3>
                    <p class="modal-body">
                        The price applies from its weight upward, until the next tier. Add a 0 g tier to cover every order.
                    </p>

                    <div class="tier-rows" data-tier-rows>
                        @forelse ($oldTiers as $index => $tier)
                            <div class="tier-row" data-tier-row>
                                <div class="form-group">
                                    <label>Min weight (g)</label>
                                    <input type="number" name="tiers[{{ $index }}][min_weight]" class="form-control" min="0" step="1" value="{{ $tier['min_weight'] }}" required>
                                    @error('tiers.'.$index.'.min_weight', 'carrierTiers'.$carrier->id) <p class="form-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="form-group">
                                    <label>Price (€)</label>
                                    <input type="number" name="tiers[{{ $index }}][price]" class="form-control" min="0" step="0.01" value="{{ $tier['price'] }}" required>
                                    @error('tiers.'.$index.'.price', 'carrierTiers'.$carrier->id) <p class="form-error">{{ $message }}</p> @enderror
                                </div>
                                <button type="button" class="btn btn-sm btn-secondary tier-remove" aria-label="Remove tier">Remove</button>
                            </div>
                        @empty
                            <p class="tier-empty" data-tier-empty>No tiers yet — the flat price above applies.</p>
                        @endforelse
                    </div>

                    <button type="button" class="btn btn-sm btn-secondary" data-tier-add>Add tier</button>

                    <template data-tier-template>
                        <div class="tier-row" data-tier-row>
                            <div class="form-group">
                                <label>Min weight (g)</label>
                                <input type="number" name="tiers[__INDEX__][min_weight]" class="form-control" min="0" step="1" required>
                            </div>
                            <div class="form-group">
                                <label>Price (€)</label>
                                <input type="number" name="tiers[__INDEX__][price]" class="form-control" min="0" step="0.01" required>
                            </div>
                            <button type="button" class="btn btn-sm btn-secondary tier-remove" aria-label="Remove tier">Remove</button>
                        </div>
                    </template>

                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-primary">Save tiers</button>
                    </div>
                </form>
            </dialog>
        @endforeach

        <div class="admin-form-card admin-form-card--solo">
            <h3 class="admin-panel-title">Package types</h3>
            <p class="form-hint">Used when adding tracking to an order. Removing one here doesn't change orders that already used it.</p>

            @if ($packageTypes->isNotEmpty())
                <ul class="admin-check-list admin-check-list--rows">
                    @foreach ($packageTypes as $packageType)
                        <li class="admin-check-list-row">
                            <span>{{ $packageType->name }}</span>
                            <form method="POST" action="{{ route('admin.settings.package-types.destroy', $packageType) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="footer-text-btn">Remove</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('admin.settings.package-types.store') }}" class="form-row form-row--inline">
                @csrf
                <div class="form-group">
                    <label for="package_type_name" class="sr-only">Package type name</label>
                    <input type="text" id="package_type_name" name="name" class="form-control" value="{{ old('name') }}" maxlength="80" placeholder="e.g. Boîte en carton">
                    @error('name') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn btn-secondary">Add package type</button>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/carrier-price-tiers.js') }}" defer></script>
@endpush
