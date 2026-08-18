@extends('layouts.app')

@section('title', __('store.cart_title').' — '.config('app.name'))
@section('canonical', localized_route('cart.show'))

@section('content')
    <div class="container">
        <nav class="breadcrumbs" aria-label="breadcrumb">
            <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <span>{{ __('store.cart_title') }}</span>
        </nav>

        <header class="page-header">
            <h2 class="page-title">{{ __('store.cart_title') }}</h2>
            @if ($itemCount > 0)
                <p class="page-meta" id="cart-item-count-header">{{ trans_choice('store.cart_count', $itemCount, ['count' => $itemCount]) }}</p>
            @endif
        </header>

        @if ($lines->isEmpty())
            <div class="empty-state">
                <p>{{ __('store.cart_empty') }}</p>
                <a href="{{ localized_route('home') }}" class="btn btn-primary">{{ __('store.cart_empty_cta') }}</a>
            </div>
        @else
            <div class="cart-layout">
                <ul class="cart-list">
                    @foreach ($lines as $line)
                        <li class="cart-line">
                            <div class="cart-line-media-slot">
                                <a href="{{ localized_route('products.show', ['product' => $line->product->slug]) }}" class="cart-line-media">
                                    <img
                                        src="{{ $line->variant?->thumbnailUrl() ?? $line->product->thumbnailUrl() }}"
                                        alt="{{ $line->product->localizedName() }}"
                                        width="180"
                                        height="180"
                                    >
                                </a>
                            </div>

                            <div class="cart-line-body">
                                <p class="cart-line-category">
                                    @if ($line->product->category->parent)
                                        {{ $line->product->category->parent->localizedName() }}
                                        <span aria-hidden="true">/</span>
                                    @endif
                                    {{ $line->product->category->localizedName() }}
                                </p>
                                <h3 class="cart-line-title">
                                    <a href="{{ localized_route('products.show', ['product' => $line->product->slug]) }}">
                                        {{ $line->product->localizedName() }}
                                    </a>
                                </h3>
                                @if ($line->variantLabel())
                                    <p class="cart-line-variant">
                                        <span class="cart-line-variant-kicker">{{ __('store.cart_variant') }}</span>
                                        <span class="cart-line-variant-value">{{ $line->variantLabel() }}</span>
                                    </p>
                                @endif
                                @if ($line->variant?->sku ?? $line->product->sku)
                                    <p class="cart-line-sku">SKU : {{ $line->variant?->sku ?? $line->product->sku }}</p>
                                @endif

                                @php
                                    $lineAvailableAtSupplier = ! $line->product->inStock()
                                        && $line->product->supplier_id !== null
                                        && $line->product->available_at_supplier;
                                @endphp
                                @if ($lineAvailableAtSupplier)
                                    <p class="cart-line-supplier">
                                        <span class="cart-line-supplier-icon" aria-hidden="true">
                                            @include('partials.icon', ['name' => 'hourglass-half', 'size' => 14])
                                        </span>
                                        <span class="cart-line-supplier-copy">
                                            <strong>{{ __('store.available_at_supplier') }}</strong>
                                            <span>
                                                {{ __('store.supplier_lead_time_label') }}
                                                ·
                                                {{ $line->product->supplier->lead_time_days !== null
                                                    ? trans_choice('store.supplier_lead_time_value', $line->product->supplier->lead_time_days, ['days' => $line->product->supplier->lead_time_days])
                                                    : __('store.supplier_lead_time_unknown') }}
                                            </span>
                                        </span>
                                    </p>
                                @endif

                                <div class="cart-line-actions">
                                    <form
                                        method="POST"
                                        action="{{ localized_route('cart.update', ['product' => $line->product->slug]) }}"
                                        class="cart-qty-form"
                                        novalidate
                                        data-stock-limit-label="{{ __('store.stock_limit') }}"
                                    >
                                        @csrf
                                        @method('PATCH')
                                        @if ($line->variant)
                                            <input type="hidden" name="variant_id" value="{{ $line->variant->id }}">
                                        @endif
                                        <label class="sr-only" for="qty-{{ $line->product->id }}-{{ $line->variant->id ?? 0 }}">{{ __('store.quantity') }}</label>
                                        <input
                                            type="number"
                                            id="qty-{{ $line->product->id }}-{{ $line->variant->id ?? 0 }}"
                                            name="quantity"
                                            class="form-control cart-qty-input"
                                            value="{{ $line->quantity }}"
                                            min="0"
                                            max="{{ $line->variant?->maxPurchasable() ?? $line->product->maxPurchasable() }}"
                                        >
                                        <button type="submit" class="btn btn-sm btn-secondary">{{ __('store.update_quantity') }}</button>
                                    </form>
                                </div>
                            </div>

                            <div class="cart-line-total-slot">
                                @if ($line->hasDiscount())
                                    <span class="badge badge-active cart-line-discount-badge">{{ $line->product->discount->label() }}</span>
                                @endif
                                <p class="cart-line-unit-price" @if ($line->quantity <= 1) hidden @endif>
                                    @if ($line->hasDiscount())
                                        <span class="card-price-original">{{ $line->formattedOriginalUnitPrice() }}</span>
                                    @endif
                                    <span class="cart-line-unit-price-value">{{ $line->formattedUnitPrice() }}</span> × <span class="cart-line-unit-price-qty">{{ $line->quantity }}</span>
                                </p>
                                <p class="cart-line-total" data-has-discount="{{ $line->hasDiscount() ? '1' : '0' }}">
                                    <span class="card-price-original" @if (! ($line->hasDiscount() && $line->quantity <= 1)) hidden @endif>{{ $line->formattedOriginalUnitPrice() }}</span>
                                    <span class="cart-line-total-value">{{ $line->formattedLineTotal() }}</span>
                                </p>
                                <form method="POST" action="{{ localized_route('cart.remove', ['product' => $line->product->slug]) }}" class="cart-line-remove">
                                    @csrf
                                    @method('DELETE')
                                    @if ($line->variant)
                                        <input type="hidden" name="variant_id" value="{{ $line->variant->id }}">
                                    @endif
                                    <button type="submit" class="btn btn-sm btn-secondary">{{ __('store.remove') }}</button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <aside class="cart-summary">
                    <div class="cart-summary-heading">
                        <h3>{{ __('store.subtotal') }}</h3>
                        <span class="cart-summary-count">{{ trans_choice('store.cart_count', $itemCount, ['count' => $itemCount]) }}</span>
                    </div>
                    <p class="cart-summary-total">{{ $total }}</p>
                    <div class="cart-summary-shipping {{ ($freeShippingUnlocked || $cheapestShippingCents === 0) ? 'is-free' : '' }}" @if ($cheapestShippingCents === null && ! $freeShippingUnlocked) hidden @endif>
                        <span class="cart-summary-shipping-label">{{ __('store.shipping') }}</span>
                        <span class="cart-summary-shipping-value">
                            {{ ($freeShippingUnlocked || $cheapestShippingCents === 0) ? __('store.shipping_free') : __('store.shipping_from_amount', ['price' => format_euros($cheapestShippingCents ?? 0)]) }}
                        </span>
                    </div>
                    <ul class="cart-summary-hints">
                        <li>
                            <span class="cart-summary-hints-label">{{ __('store.shipping') }}</span>
                            <span class="cart-summary-hints-text">{{ __('store.cart_note') }}</span>
                        </li>
                        <li>
                            <span class="cart-summary-hints-label">{{ __('store.cart_discount_code_kicker') }}</span>
                            <span class="cart-summary-hints-text">{{ __('store.cart_discount_code_note') }}</span>
                        </li>
                    </ul>
                    <a href="{{ localized_route('checkout.show') }}" class="btn btn-primary btn-block">{{ __('store.checkout') }}</a>
                    <a href="{{ localized_route('home') }}" class="btn btn-secondary btn-block">{{ __('store.cart_continue') }}</a>
                </aside>
            </div>
        @endif
    </div>
@endsection
