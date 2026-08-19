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
                <a href="{{ route('admin.settings.index') }}" class="btn btn-secondary">Back to settings</a>
            </div>
        </header>

        <div class="admin-order-create-grid">
            <form
                method="POST"
                action="{{ route('admin.settings.shipping.update') }}"
                class="order-panel"
            >
                @csrf
                @method('PUT')

                <h3 class="order-panel-title">Free shipping</h3>
                <p class="form-hint">Leave the amount blank to turn free shipping off.</p>

                <div class="form-group">
                    <label for="free_shipping_threshold">Free above (EUR)</label>
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
                    @error('free_shipping_threshold') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="form-group">
                    <label>Eligible carriers</label>
                    <p class="form-hint">These carriers become free once the order subtotal reaches the amount. Others keep their price.</p>

                    <div class="shipping-carrier-options">
                        @foreach ($carriers as $carrier)
                            <label class="admin-choice {{ in_array($carrier->id, $selectedCarrierIds) ? 'is-selected' : '' }}">
                                <input
                                    type="checkbox"
                                    name="free_shipping_carrier_ids[]"
                                    value="{{ $carrier->id }}"
                                    @checked(in_array($carrier->id, $selectedCarrierIds))
                                >
                                <span class="shipping-carrier-copy">
                                    <span class="admin-table-strong">{{ $carrier->localizedName() }}</span>
                                    <span class="admin-table-sub">{{ $carrier->formattedStartingPrice() }} · {{ $carrier->method->value }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('free_shipping_carrier_ids') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save free shipping</button>
                </div>
            </form>

            <section class="order-panel">
                <h3 class="order-panel-title">Carrier prices</h3>
                <p class="form-hint">Priced by weight tier, with a default for anything a tier doesn’t cover.</p>

                <ul class="admin-dash-list">
                    @foreach ($carriers as $carrier)
                        <li>
                            <div class="admin-dash-list-main">
                                <span class="admin-table-strong">{{ $carrier->localizedName() }}</span>
                                <span class="admin-table-sub">From {{ $carrier->formattedStartingPrice() }} · {{ $carrier->method->value }}</span>
                            </div>
                            <button type="button" class="btn btn-sm btn-secondary" data-modal-open="carrier-price-tiers-{{ $carrier->id }}">Edit price</button>
                        </li>
                    @endforeach
                </ul>
            </section>
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
                        Tiers price applies from their weight upward, until the next tier. The default price below covers
                        anything lighter than your lowest tier, or every order if you add no tiers at all.
                    </p>

                    <div class="form-group">
                        <label for="carrier-default-price-{{ $carrier->id }}">Default price (€)</label>
                        <input
                            type="number"
                            id="carrier-default-price-{{ $carrier->id }}"
                            name="default_price"
                            class="form-control"
                            min="0"
                            max="9999.99"
                            step="0.01"
                            value="{{ old('default_price', number_format($carrier->price_cents / 100, 2, '.', '')) }}"
                            required
                        >
                        @error('default_price', 'carrierTiers'.$carrier->id) <p class="form-error">{{ $message }}</p> @enderror
                    </div>

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
                            <p class="tier-empty" data-tier-empty>No tiers yet — the default price above applies.</p>
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

        <section class="order-panel admin-shipping-packages">
            <h3 class="order-panel-title">Package types</h3>
            <p class="form-hint">Used when adding tracking to an order. Removing one here doesn’t change orders that already used it.</p>

            @if ($packageTypes->isNotEmpty())
                <ul class="admin-dash-list">
                    @foreach ($packageTypes as $packageType)
                        <li>
                            <span class="admin-table-primary">{{ $packageType->name }}</span>
                            <button type="button" class="footer-text-btn" data-modal-open="package-type-delete-{{ $packageType->id }}">Remove</button>
                            <dialog id="package-type-delete-{{ $packageType->id }}" class="modal" aria-labelledby="package-type-delete-{{ $packageType->id }}-title">
                                <form method="POST" action="{{ route('admin.settings.package-types.destroy', $packageType) }}">
                                    @csrf
                                    @method('DELETE')
                                    <p class="modal-kicker">{{ $packageType->name }}</p>
                                    <h3 class="modal-title" id="package-type-delete-{{ $packageType->id }}-title">Remove this package type?</h3>
                                    <p class="modal-body">Orders that already used it keep it on their tracking.</p>
                                    <div class="modal-actions">
                                        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                                        <button type="submit" class="btn btn-primary">Remove package type</button>
                                    </div>
                                </form>
                            </dialog>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="variant-empty">No package types yet.</p>
            @endif

            <form method="POST" action="{{ route('admin.settings.package-types.store') }}" class="shipping-package-add">
                @csrf
                <div class="form-group">
                    <label for="package_type_name" class="sr-only">Package type name</label>
                    <input type="text" id="package_type_name" name="name" class="form-control" value="{{ old('name') }}" maxlength="80" placeholder="e.g. Boîte en carton">
                    @error('name') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn btn-secondary">Add package type</button>
            </form>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/carrier-price-tiers.js') }}" defer></script>
@endpush
