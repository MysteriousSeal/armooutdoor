@extends('layouts.app')

@section('title', __('store.checkout_title').' — '.config('app.name'))
@section('canonical', localized_route('checkout.show'))

@section('content')
    <div class="container">
        <nav class="breadcrumbs" aria-label="breadcrumb">
            <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <a href="{{ localized_route('cart.show') }}">{{ __('store.cart_title') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <span>{{ __('store.checkout_title') }}</span>
        </nav>

        <header class="page-header">
            <h2 class="page-title">{{ __('store.checkout_title') }}</h2>
            <p class="page-lede">{{ __('store.checkout_intro') }}</p>
        </header>

        @if ($paymentCanceled)
            <div class="flash flash-warning" role="status">{{ __('store.payment_canceled') }}</div>
        @endif

        <form method="POST" action="{{ localized_route('checkout.store') }}" id="checkout-form">@csrf</form>

        <div class="checkout-layout">
            <div class="checkout-main">
                    <section class="checkout-section checkout-section--discount-code">
                        <h3 class="discount-code-heading">{{ __('store.discount_code_section') }}</h3>

                        <div id="discount-code-section-body">
                            @include('partials.checkout-discount-code')
                        </div>
                    </section>

                    <section class="checkout-section checkout-section--address">
                        <h3 class="checkout-heading">
                            <span class="home-kicker">01</span>
                            {{ __('store.address_section') }}
                        </h3>

                        @if ($addresses->isEmpty())
                            <p class="checkout-hint">{{ __('store.address_empty') }}</p>
                        @else
                            <div class="choice-grid">
                                @foreach ($addresses as $address)
                                    <label class="choice-card">
                                        <input
                                            type="radio"
                                            name="address_id"
                                            value="{{ $address->id }}"
                                            form="checkout-form"
                                            data-postal-code="{{ $address->postal_code }}"
                                            data-country="{{ $address->country }}"
                                            @checked((string) $selectedAddressId === (string) $address->id)
                                        >
                                        <span class="choice-card-body">
                                            <span class="choice-card-title">
                                                {{ $address->recipientName() }}
                                                @if ($address->label)
                                                    <span class="choice-card-label">{{ $address->label }}</span>
                                                @endif
                                                @if ($address->is_default)
                                                    <span class="choice-card-badge">{{ __('store.default_badge') }}</span>
                                                @endif
                                            </span>
                                            <span class="choice-card-meta">
                                                {{ $address->line1 }}@if ($address->line2), {{ $address->line2 }}@endif
                                            </span>
                                            <span class="choice-card-meta">
                                                {{ $address->postal_code }} {{ $address->city }} · {{ $address->countryName() }}
                                            </span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('address_id')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        @endif

                        <details class="address-form-wrap" @if ($addresses->isEmpty() || $errors->hasAny(['first_name', 'last_name', 'line1', 'postal_code', 'city', 'country'])) open @endif>
                            <summary>{{ __('store.address_new') }}</summary>

                            <form method="POST" action="{{ localized_route('checkout.addresses.store') }}" class="auth-form address-form">
                                @csrf

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="first_name">{{ __('store.first_name') }}</label>
                                        <input type="text" id="first_name" name="first_name" class="form-control" value="{{ old('first_name', auth()->user()->first_name) }}" required maxlength="80" autocomplete="given-name">
                                        @error('first_name') <p class="form-error">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="form-group">
                                        <label for="last_name">{{ __('store.last_name') }}</label>
                                        <input type="text" id="last_name" name="last_name" class="form-control" value="{{ old('last_name') }}" required maxlength="80" autocomplete="family-name">
                                        @error('last_name') <p class="form-error">{{ $message }}</p> @enderror
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="line1">{{ __('store.line1') }}</label>
                                    <input type="text" id="line1" name="line1" class="form-control" value="{{ old('line1') }}" required maxlength="120" autocomplete="address-line1">
                                    @error('line1') <p class="form-error">{{ $message }}</p> @enderror
                                </div>

                                <div class="form-group">
                                    <label for="line2">{{ __('store.line2') }}</label>
                                    <input type="text" id="line2" name="line2" class="form-control" value="{{ old('line2') }}" maxlength="120" autocomplete="address-line2">
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="postal_code">{{ __('store.postal_code') }}</label>
                                        <input type="text" id="postal_code" name="postal_code" class="form-control" value="{{ old('postal_code') }}" required maxlength="12" autocomplete="postal-code">
                                        @error('postal_code') <p class="form-error">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="form-group">
                                        <label for="city">{{ __('store.city') }}</label>
                                        <input type="text" id="city" name="city" class="form-control" value="{{ old('city') }}" required maxlength="80" autocomplete="address-level2">
                                        @error('city') <p class="form-error">{{ $message }}</p> @enderror
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="country">{{ __('store.country') }}</label>
                                        <select id="country" name="country" class="form-control" required autocomplete="country">
                                            @foreach (config('shop.customer_countries') as $country)
                                                <option value="{{ $country }}" @selected(old('country', 'FR') === $country)>{{ __('store.country_'.$country) }}</option>
                                            @endforeach
                                        </select>
                                        @error('country') <p class="form-error">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="form-group">
                                        <label for="phone">{{ __('store.phone') }}</label>
                                        <input type="tel" id="phone" name="phone" class="form-control" value="{{ old('phone') }}" required maxlength="30" autocomplete="tel">
                                        @error('phone') <p class="form-error">{{ $message }}</p> @enderror
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="label">{{ __('store.address_label') }}</label>
                                    <input type="text" id="label" name="label" class="form-control" value="{{ old('label') }}" maxlength="40" placeholder="{{ __('store.address_label_placeholder') }}">
                                </div>

                                <div class="form-group">
                                    <label class="form-check">
                                        <input type="checkbox" name="is_default" value="1" @checked(old('is_default', true))>
                                        {{ __('store.make_default') }}
                                    </label>
                                </div>

                                <button type="submit" class="btn btn-secondary">{{ __('store.address_save') }}</button>
                            </form>
                        </details>
                    </section>

                    <section class="checkout-section checkout-section--address">
                        <h3 class="checkout-heading">
                            <span class="home-kicker">02</span>
                            {{ __('store.billing_address_section') }}
                        </h3>

                        @if ($addresses->isNotEmpty())
                            <div class="form-group billing-toggle">
                                <label class="form-check">
                                    <input
                                        type="checkbox"
                                        name="same_billing_address"
                                        value="1"
                                        form="checkout-form"
                                        id="same-billing-address"
                                        @checked($sameBillingAddress)
                                    >
                                    {{ __('store.billing_same_as_shipping') }}
                                </label>
                            </div>

                            <div id="billing-address-picker" class="billing-address-picker" @if ($sameBillingAddress) hidden @endif>
                                <div class="choice-grid">
                                    @foreach ($addresses as $address)
                                        <label class="choice-card">
                                            <input
                                                type="radio"
                                                name="billing_address_id"
                                                value="{{ $address->id }}"
                                                form="checkout-form"
                                                @checked((string) $selectedBillingAddressId === (string) $address->id)
                                                @disabled($sameBillingAddress)
                                            >
                                            <span class="choice-card-body">
                                                <span class="choice-card-title">
                                                    {{ $address->recipientName() }}
                                                    @if ($address->label)
                                                        <span class="choice-card-label">{{ $address->label }}</span>
                                                    @endif
                                                </span>
                                                <span class="choice-card-meta">
                                                    {{ $address->line1 }}@if ($address->line2), {{ $address->line2 }}@endif
                                                </span>
                                                <span class="choice-card-meta">
                                                    {{ $address->postal_code }} {{ $address->city }} · {{ $address->countryName() }}
                                                </span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('billing_address_id')
                                    <p class="form-error">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif
                    </section>

                <section class="checkout-section">
                    <h3 class="checkout-heading">
                        <span class="home-kicker">03</span>
                        {{ __('store.shipping_section') }}
                    </h3>

                    @php
                        $carrierLogos = [
                            'lettre-suivie' => 'poste.png',
                            'colissimo-home' => 'colissimo.png',
                            'chronopost-home' => 'chronopost.png',
                            'relais-pickup' => 'chronopost.png',
                            'mondial-relay' => 'mondialrelay.png',
                        ];
                    @endphp

                    @if ($homeCarriers->isNotEmpty())
                        <div class="shipping-group">
                            <h4 class="shipping-group-title">{{ __('store.shipping_home') }}</h4>
                            <div class="choice-grid">
                                @foreach ($homeCarriers as $carrier)
                                    <label class="choice-card">
                                        <input
                                            type="radio"
                                            name="carrier_id"
                                            value="{{ $carrier->id }}"
                                            form="checkout-form"
                                            data-method="home"
                                            @checked((string) $selectedCarrierId === (string) $carrier->id)
                                        >
                                        <span class="choice-card-body">
                                            <span class="choice-card-title">
                                                @if (isset($carrierLogos[$carrier->slug]))
                                                    <img src="{{ asset('images/carriers/'.$carrierLogos[$carrier->slug]) }}" alt="" class="carrier-logo">
                                                @endif
                                                {{ $carrier->localizedName() }}
                                                <span class="choice-card-price">
                                                    {{ $carrierPricesCents[$carrier->id] === 0 ? __('store.shipping_free') : format_euros($carrierPricesCents[$carrier->id]) }}
                                                </span>
                                            </span>
                                            <span class="choice-card-meta">{{ $carrier->localizedDescription() }}</span>
                                            <span class="choice-card-meta">{{ $carrier->localizedEta() }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($relayCarriers->isNotEmpty())
                        <div class="shipping-group">
                            <h4 class="shipping-group-title">{{ __('store.shipping_relay') }}</h4>
                            <div class="choice-grid">
                                @foreach ($relayCarriers as $carrier)
                                    <label class="choice-card">
                                        <input
                                            type="radio"
                                            name="carrier_id"
                                            value="{{ $carrier->id }}"
                                            form="checkout-form"
                                            data-method="relay"
                                            data-carrier-slug="{{ $carrier->slug }}"
                                            @checked((string) $selectedCarrierId === (string) $carrier->id)
                                        >
                                        <span class="choice-card-body">
                                            <span class="choice-card-title">
                                                @if (isset($carrierLogos[$carrier->slug]))
                                                    <img src="{{ asset('images/carriers/'.$carrierLogos[$carrier->slug]) }}" alt="" class="carrier-logo">
                                                @endif
                                                {{ $carrier->localizedName() }}
                                                <span class="choice-card-price">
                                                    {{ $carrierPricesCents[$carrier->id] === 0 ? __('store.shipping_free') : format_euros($carrierPricesCents[$carrier->id]) }}
                                                </span>
                                            </span>
                                            <span class="choice-card-meta">{{ $carrier->localizedDescription() }}</span>
                                            <span class="choice-card-meta">{{ $carrier->localizedEta() }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @error('carrier_id')
                        <p class="form-error">{{ $message }}</p>
                    @enderror

                    <div id="relay-picker" class="relay-picker" @if (! $selectedCarrierIsRelay) hidden @endif>
                        <h4 class="shipping-group-title">{{ __('store.relay_section') }}</h4>

                        <div id="relay-list" @if ($selectedRelayPointId) hidden @endif>
                            <div class="form-group relay-search-wrap">
                                <label for="relay-search">{{ __('store.relay_search') }}</label>
                                <input
                                    type="search"
                                    id="relay-search"
                                    class="form-control"
                                    placeholder="{{ __('store.relay_search_placeholder') }}"
                                    autocomplete="off"
                                >
                                <ul class="relay-search-results" id="relay-search-results" hidden></ul>
                            </div>

                            <noscript>
                                <div class="form-row relay-noscript-search">
                                    <div class="form-group">
                                        <label for="relay-noscript-postal-code">{{ __('store.relay_noscript_label') }}</label>
                                        <input
                                            type="text"
                                            id="relay-noscript-postal-code"
                                            name="relay_postal_code"
                                            form="checkout-form"
                                            class="form-control"
                                            value="{{ $relayPostalCode }}"
                                            maxlength="12"
                                        >
                                    </div>
                                    <button
                                        type="submit"
                                        form="checkout-form"
                                        formaction="{{ localized_route('checkout.show') }}"
                                        formmethod="GET"
                                        formnovalidate
                                        class="btn btn-secondary"
                                    >
                                        {{ __('store.relay_search_submit') }}
                                    </button>
                                </div>
                            </noscript>

                            <div class="choice-grid choice-grid--relay" id="relay-points-grid">
                                @foreach ($relayPoints as $point)
                                    <label class="choice-card relay-option" data-search="{{ $point->searchBlob() }}">
                                        <input
                                            type="radio"
                                            name="relay_point_id"
                                            value="{{ $point->id }}"
                                            form="checkout-form"
                                            @checked((string) $selectedRelayPointId === (string) $point->id)
                                            @disabled(! $selectedCarrierIsRelay)
                                        >
                                        <span class="choice-card-body">
                                            <span class="choice-card-title relay-title">{{ $point->name }}</span>
                                            <span class="choice-card-meta relay-address">{{ $point->line1 }}</span>
                                            <span class="choice-card-meta relay-address">{{ $point->postal_code }} {{ $point->city }}</span>
                                            @if ($point->hoursLines() !== [])
                                                <span class="choice-card-meta relay-hours-label">{{ __('store.relay_hours') }}</span>
                                                <ul class="relay-hours">
                                                    @foreach ($point->hoursLines() as [$day, $range])
                                                        <li><span class="relay-hours-day">{{ $day }}</span><span class="relay-hours-range">{{ $range }}</span></li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            <p class="checkout-hint" id="relay-points-empty" @unless ($relayPoints->isEmpty()) hidden @endunless>
                                {{ __('store.relay_empty') }}
                            </p>
                            <div class="relay-points-more-wrap">
                                <button type="button" class="btn btn-secondary" id="relay-points-more" hidden>
                                    {{ __('store.relay_show_more') }}
                                </button>
                            </div>
                        </div>

                        <div class="relay-selected" id="relay-selected" @unless ($selectedRelayPointId) hidden @endunless>
                            <p class="relay-selected-kicker">{{ __('store.relay_selected_kicker') }}</p>
                            <div class="relay-selected-card">
                                <div class="relay-selected-copy" id="relay-selected-body"></div>
                                <button type="button" class="btn btn-secondary" id="relay-selected-change">
                                    {{ __('store.relay_change') }}
                                </button>
                            </div>
                        </div>

                        @error('relay_point_id')
                            <p class="form-error" id="relay-point-error">{{ $message }}</p>
                        @enderror
                    </div>
                </section>

                <section
                    id="payment-section"
                    class="checkout-section"
                    @if (! $selectedCarrierId) hidden @endif
                >
                    <h3 class="checkout-heading">
                        <span class="home-kicker">04</span>
                        {{ __('store.payment_section') }}
                    </h3>

                    <p class="checkout-hint payment-locked-hint" id="payment-locked-hint" hidden>
                        {{ __('store.payment_locked_relay') }}
                    </p>

                    <div class="choice-grid">
                        <label class="choice-card">
                            <input
                                type="radio"
                                name="payment_method"
                                value="card"
                                form="checkout-form"
                                disabled
                            >
                            <span class="choice-card-body">
                                <span class="choice-card-title">
                                    <img src="{{ asset('images/payments/cb.png') }}" alt="" class="carrier-logo">
                                    {{ __('store.payment_card') }}
                                    <span class="choice-card-badge">{{ __('store.payment_card_soon') }}</span>
                                </span>
                                <span class="choice-card-meta">{{ __('store.payment_card_lede') }}</span>
                            </span>
                        </label>

                        <label class="choice-card">
                            <input
                                type="radio"
                                name="payment_method"
                                value="paypal"
                                form="checkout-form"
                                disabled
                            >
                            <span class="choice-card-body">
                                <span class="choice-card-title">
                                    <img src="{{ asset('images/payments/paypal.png') }}" alt="" class="carrier-logo">
                                    {{ __('store.payment_paypal') }}
                                    <span class="choice-card-badge">{{ __('store.payment_paypal_soon') }}</span>
                                </span>
                                <span class="choice-card-meta">{{ __('store.payment_paypal_lede') }}</span>
                            </span>
                        </label>
                    </div>
                    @error('payment_method')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </section>
            </div>

            <aside class="cart-summary checkout-summary">
                <h3 class="checkout-summary-title">{{ __('store.line_total') }}</h3>

                <ul class="checkout-lines">
                    @foreach ($lines as $line)
                        <li class="checkout-line">
                            <a href="{{ localized_route('products.show', ['product' => $line->product->slug]) }}" class="checkout-line-media">
                                <img
                                    src="{{ $line->variant?->thumbnailUrl() ?? $line->product->thumbnailUrl() }}"
                                    alt=""
                                    width="72"
                                    height="72"
                                >
                            </a>
                            <div class="checkout-line-body">
                                <p class="checkout-line-name">
                                    <a href="{{ localized_route('products.show', ['product' => $line->product->slug]) }}">
                                        {{ $line->product->localizedName() }}
                                    </a>
                                </p>
                                @if ($line->variantLabel())
                                    <p class="checkout-line-variant">
                                        <span class="checkout-line-variant-value">{{ $line->variantLabel() }}</span>
                                    </p>
                                @endif
                                @if ($line->hasDiscount())
                                    <span class="badge badge-active cart-line-discount-badge">{{ $line->product->discount->label() }}</span>
                                @endif
                                <p class="checkout-line-meta">
                                    × {{ $line->quantity }} ·
                                    @if ($line->hasDiscount())
                                        <span class="card-price-original">{{ $line->formattedOriginalUnitPrice() }}</span>
                                    @endif
                                    {{ $line->formattedUnitPrice() }}
                                </p>
                            </div>
                            <p class="checkout-line-price">{{ $line->formattedLineTotal() }}</p>
                        </li>
                    @endforeach
                </ul>

                <dl class="checkout-totals">
                    <div>
                        <dt>{{ __('store.subtotal') }}</dt>
                        <dd>{{ $subtotal }}</dd>
                    </div>
                    <div id="checkout-discount-row" @unless ($discountCode) hidden @endunless>
                        <dt id="checkout-discount-label">{{ $discountCode ? __('store.order_discount_code', ['code' => $discountCode->code]) : '' }}</dt>
                        {{-- A shipping code has no goods amount; it is worth
                             whatever the relay carrier costs, shown on its own
                             line below once one is chosen. --}}
                        <dd id="checkout-discount-value">
                            {{ $discountCode?->isFreeRelayShipping()
                                ? __('store.discount_code_free_relay_label')
                                : '-'.format_euros($discountCents) }}
                        </dd>
                    </div>
                    <div>
                        <dt>{{ __('store.shipping') }}</dt>
                        <dd id="checkout-shipping-price">—</dd>
                    </div>
                    <div id="checkout-shipping-discount-row" hidden>
                        <dt>{{ __('store.checkout_shipping_discount') }}</dt>
                        <dd id="checkout-shipping-discount-value">—</dd>
                    </div>
                    <div class="checkout-totals-grand">
                        <dt>{{ __('store.line_total') }}</dt>
                        <dd id="checkout-grand-total">{{ format_euros($subtotalCents - $discountCents) }}</dd>
                    </div>
                </dl>

                <button
                    type="submit"
                    id="checkout-submit"
                    form="checkout-form"
                    class="btn btn-primary btn-block"
                    @disabled($addresses->isEmpty())
                >
                    {{ __('store.place_order') }}
                </button>
                <a href="{{ localized_route('cart.show') }}" class="btn btn-secondary btn-block">{{ __('store.cart') }}</a>
            </aside>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        window.armoCheckout = {
            subtotalCents: {{ $subtotalCents }},
            discountCents: {{ $discountCents }},
            locale: @json(app()->getLocale()),
            carriers: {
                @foreach ($homeCarriers->concat($relayCarriers) as $carrier)
                    {{ $carrier->id }}: {{ $carrierPricesCents[$carrier->id] }},
                @endforeach
            },
            // Carriers this cart's code waives delivery on. The total is
            // computed here as well as in PHP, so both must know.
            freeShippingCarrierIds: @json($freeShippingCarrierIds),
            relayPointsUrl: @json(localized_route('checkout.relay-points')),
            relayHoursLabel: @json(__('store.relay_hours')),
            postalCodesSearchUrl: @json(localized_route('checkout.postal-codes')),
            selectedRelayPointId: @json($selectedRelayPointId),
        };
    </script>
    <script src="{{ asset('js/checkout.js') }}" defer></script>
    <script src="{{ asset('js/checkout-discount-code.js') }}" defer></script>
@endpush
