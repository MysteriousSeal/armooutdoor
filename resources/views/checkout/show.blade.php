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

        <form method="POST" action="{{ localized_route('checkout.store') }}" id="checkout-form">@csrf</form>

        <div class="checkout-layout">
            <div class="checkout-main">
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
                                            @foreach (config('shop.countries') as $country)
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
                                            @checked((string) $selectedCarrierId === (string) $carrier->id)
                                        >
                                        <span class="choice-card-body">
                                            <span class="choice-card-title">
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

                        <div class="form-group">
                            <label for="relay-search">{{ __('store.relay_search') }}</label>
                            <input
                                type="search"
                                id="relay-search"
                                class="form-control"
                                placeholder="{{ __('store.relay_search_placeholder') }}"
                                autocomplete="off"
                            >
                        </div>

                        <div class="choice-grid choice-grid--relay">
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
                                        <span class="choice-card-title">{{ $point->name }}</span>
                                        <span class="choice-card-meta">{{ $point->line1 }}</span>
                                        <span class="choice-card-meta">{{ $point->postal_code }} {{ $point->city }}</span>
                                        @if ($point->hours)
                                            <span class="choice-card-meta">{{ __('store.relay_hours') }}: {{ $point->hours }}</span>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('relay_point_id')
                            <p class="form-error">{{ $message }}</p>
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

                    <div class="choice-grid">
                        <label class="choice-card">
                            <input
                                type="radio"
                                name="payment_method"
                                value="card"
                                form="checkout-form"
                                @checked($selectedPaymentMethod === 'card')
                            >
                            <span class="choice-card-body">
                                <span class="choice-card-title">{{ __('store.payment_card') }}</span>
                                <span class="choice-card-meta">{{ __('store.payment_card_lede') }}</span>
                            </span>
                        </label>

                        <label class="choice-card">
                            <input
                                type="radio"
                                name="payment_method"
                                value="paypal"
                                form="checkout-form"
                                @checked($selectedPaymentMethod === 'paypal')
                            >
                            <span class="choice-card-body">
                                <span class="choice-card-title">{{ __('store.payment_paypal') }}</span>
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
                                    src="{{ $line->variant?->imageUrl() ?? $line->product->imageUrl() }}"
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
                                <p class="checkout-line-meta">× {{ $line->quantity }} · {{ $line->formattedUnitPrice() }}</p>
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
                    <div>
                        <dt>{{ __('store.shipping') }}</dt>
                        <dd id="checkout-shipping-price">—</dd>
                    </div>
                    <div class="checkout-totals-grand">
                        <dt>{{ __('store.line_total') }}</dt>
                        <dd id="checkout-grand-total">{{ $subtotal }}</dd>
                    </div>
                </dl>

                <button
                    type="submit"
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
            locale: @json(app()->getLocale()),
            carriers: {
                @foreach ($homeCarriers->concat($relayCarriers) as $carrier)
                    {{ $carrier->id }}: {{ $carrierPricesCents[$carrier->id] }},
                @endforeach
            }
        };
    </script>
    <script src="{{ asset('js/checkout.js') }}" defer></script>
@endpush
