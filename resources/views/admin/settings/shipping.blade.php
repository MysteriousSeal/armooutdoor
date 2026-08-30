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
            {{-- Colonne de gauche : deux panneaux courts empilés, pour que la
                 hauteur du panneau transporteurs à droite ne creuse pas de
                 blanc entre eux. --}}
            <div class="shipping-settings-col">
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

                    @php
                        $freeShippingLogos = [
                            'lettre-suivie' => 'poste.png',
                            'colissimo-home' => 'colissimo.png',
                            'chronopost-home' => 'chronopost.png',
                            'relais-pickup' => 'chronopost.png',
                            'mondial-relay' => 'mondialrelay.png',
                        ];
                    @endphp
                    <div class="shipping-carrier-options">
                        @foreach ($carriers as $carrier)
                            <label class="admin-choice {{ in_array($carrier->id, $selectedCarrierIds) ? 'is-selected' : '' }}">
                                <input
                                    type="checkbox"
                                    name="free_shipping_carrier_ids[]"
                                    value="{{ $carrier->id }}"
                                    @checked(in_array($carrier->id, $selectedCarrierIds))
                                >
                                @if (isset($freeShippingLogos[$carrier->slug]))
                                    <img src="{{ asset('images/carriers/'.$freeShippingLogos[$carrier->slug]) }}" alt="" class="shipping-carrier-option-logo">
                                @endif
                                <span class="shipping-carrier-copy">
                                    <span class="admin-table-strong">{{ $carrier->localizedName() }}</span>
                                    <span class="admin-table-sub">{{ $carrier->formattedStartingPrice() }} · {{ $carrier->method->value === 'relay' ? 'relay point' : 'home' }}</span>
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

            <section class="order-panel admin-shipping-packages">
                <h3 class="order-panel-title">Package types</h3>
                <p class="form-hint">Used when adding tracking to an order. Removing one here doesn’t change orders that already used it.</p>

                @if ($packageTypes->isNotEmpty())
                    <div class="package-type-tags">
                        @foreach ($packageTypes as $packageType)
                            @php $packageTypeCount = $packageTypeUsage[$packageType->id] ?? 0; @endphp
                            <span class="package-type-tag">
                                <span class="package-type-tag-name">{{ $packageType->name }}</span>
                                <span
                                    class="package-type-tag-count"
                                    title="{{ $packageTypeCount === 1 ? '1 order used this type' : $packageTypeCount.' orders used this type' }}"
                                >{{ $packageTypeCount }}</span>
                                <button
                                    type="button"
                                    class="package-type-tag-remove"
                                    data-modal-open="package-type-delete-{{ $packageType->id }}"
                                    aria-label="Remove {{ $packageType->name }}"
                                >
                                    <svg viewBox="0 0 24 24" width="11" height="11" aria-hidden="true">
                                        <path d="M6 6l12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                                    </svg>
                                </button>
                            </span>
                        @endforeach
                    </div>

                    @foreach ($packageTypes as $packageType)
                        @php $packageTypeCount = $packageTypeUsage[$packageType->id] ?? 0; @endphp
                        <dialog id="package-type-delete-{{ $packageType->id }}" class="modal" aria-labelledby="package-type-delete-{{ $packageType->id }}-title">
                            <form method="POST" action="{{ route('admin.settings.package-types.destroy', $packageType) }}">
                                @csrf
                                @method('DELETE')
                                <p class="modal-kicker">{{ $packageType->name }}</p>
                                <h3 class="modal-title" id="package-type-delete-{{ $packageType->id }}-title">Remove this package type?</h3>
                                <p class="modal-body">
                                    {{ $packageTypeCount === 0 ? 'No order ever used it.' : ($packageTypeCount === 1 ? '1 order used it and keeps it on its tracking.' : $packageTypeCount.' orders used it and keep it on their tracking.') }}
                                </p>
                                <div class="modal-actions">
                                    <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                                    <button type="submit" class="btn btn-primary">Remove package type</button>
                                </div>
                            </form>
                        </dialog>
                    @endforeach
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

            <section class="order-panel">
                <h3 class="order-panel-title">Carrier prices</h3>
                <p class="form-hint">Priced by weight tier, with a default for anything a tier doesn’t cover.</p>

                @php
                    $carrierLogos = [
                        'lettre-suivie' => 'poste.png',
                        'colissimo-home' => 'colissimo.png',
                        'chronopost-home' => 'chronopost.png',
                        'relais-pickup' => 'chronopost.png',
                        'mondial-relay' => 'mondialrelay.png',
                    ];

                    // Chaque carte répond « que facture-t-on ? » sans ouvrir
                    // la fenêtre : le prix par défaut devient la première
                    // tranche, chaque palier borne la précédente, et la
                    // limite de poids ferme la dernière quand elle existe.
                    $rateRowsFor = function ($carrier): array {
                        $grams = fn (int $value): string => number_format($value, 0, ',', ' ').' g';
                        $tiers = $carrier->priceTiers->sortBy('min_weight_grams')->values();
                        $cap = $carrier->max_weight_grams;

                        if ($tiers->isEmpty()) {
                            return [[
                                'range' => $cap !== null ? 'Up to '.$grams($cap) : 'All weights',
                                'price' => $carrier->price_cents,
                            ]];
                        }

                        $rows = [['range' => 'Under '.$grams($tiers->first()->min_weight_grams), 'price' => $carrier->price_cents]];

                        foreach ($tiers as $index => $tier) {
                            $next = $tiers[$index + 1] ?? null;
                            $rows[] = [
                                'range' => match (true) {
                                    $next !== null => $grams($tier->min_weight_grams).' – '.$grams($next->min_weight_grams - 1),
                                    $cap !== null => $grams($tier->min_weight_grams).' – '.$grams($cap),
                                    default => $grams($tier->min_weight_grams).' and up',
                                },
                                'price' => $tier->price_cents,
                            ];
                        }

                        return $rows;
                    };
                @endphp

                <div class="shipping-carrier-cards">
                    @foreach ($carriers as $carrier)
                        <article class="shipping-carrier-card">
                            <header class="shipping-carrier-card-head">
                                @if (isset($carrierLogos[$carrier->slug]))
                                    <img src="{{ asset('images/carriers/'.$carrierLogos[$carrier->slug]) }}" alt="" class="shipping-carrier-card-logo">
                                @endif
                                <span class="admin-table-strong">{{ $carrier->localizedName() }}</span>
                                <span class="shipping-method-chip is-{{ $carrier->method->value }}">{{ $carrier->method->value === 'relay' ? 'Relay point' : 'Home' }}</span>
                                @if ($setting->free_shipping_threshold_cents !== null && in_array($carrier->id, $setting->free_shipping_carrier_ids ?? [], true))
                                    <span class="shipping-free-chip">Free above {{ format_euros($setting->free_shipping_threshold_cents) }}</span>
                                @endif
                                <button type="button" class="btn btn-sm btn-secondary shipping-carrier-card-edit" data-modal-open="carrier-price-tiers-{{ $carrier->id }}">Edit</button>
                            </header>

                            <dl class="shipping-rate-table">
                                @foreach ($rateRowsFor($carrier) as $row)
                                    <div class="shipping-rate-row">
                                        <dt>{{ $row['range'] }}</dt>
                                        <dd>{{ format_euros($row['price']) }}</dd>
                                    </div>
                                @endforeach
                                <div class="shipping-rate-row shipping-rate-row--limit">
                                    <dt>Max weight</dt>
                                    <dd>{{ $carrier->max_weight_grams !== null ? number_format($carrier->max_weight_grams, 0, ',', ' ').' g' : 'No limit' }}</dd>
                                </div>
                            </dl>
                        </article>
                    @endforeach
                </div>
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

                    <div class="form-group">
                        <label for="carrier-max-weight-{{ $carrier->id }}">Max weight (g)</label>
                        <input
                            type="number"
                            id="carrier-max-weight-{{ $carrier->id }}"
                            name="max_weight"
                            class="form-control"
                            min="1"
                            max="999999"
                            step="1"
                            value="{{ old('max_weight', $carrier->max_weight_grams) }}"
                            placeholder="No limit"
                        >
                        <p class="form-hint">Above this weight the carrier shows greyed out at checkout and can't be picked. Leave empty for no limit.</p>
                        @error('max_weight', 'carrierTiers'.$carrier->id) <p class="form-error">{{ $message }}</p> @enderror
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

    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/carrier-price-tiers.js') }}" defer></script>
@endpush
