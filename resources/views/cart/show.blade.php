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
                <p class="page-meta">{{ trans_choice('store.cart_count', $itemCount, ['count' => $itemCount]) }}</p>
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
                                        src="{{ $line->product->imageUrl() }}"
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
                                    <p class="cart-line-sku">
                                        {{ $line->variantLabel() }}
                                        @if ($line->variant?->sku ?? $line->product->sku)
                                            · SKU : {{ $line->variant?->sku ?? $line->product->sku }}
                                        @endif
                                    </p>
                                @elseif ($line->product->sku)
                                    <p class="cart-line-sku">SKU : {{ $line->product->sku }}</p>
                                @endif

                                <div class="cart-line-actions">
                                    <form method="POST" action="{{ localized_route('cart.update', ['product' => $line->product->slug]) }}" class="cart-qty-form">
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

                                    <form method="POST" action="{{ localized_route('cart.remove', ['product' => $line->product->slug]) }}">
                                        @csrf
                                        @method('DELETE')
                                        @if ($line->variant)
                                            <input type="hidden" name="variant_id" value="{{ $line->variant->id }}">
                                        @endif
                                        <button type="submit" class="btn btn-sm btn-secondary">{{ __('store.remove') }}</button>
                                    </form>
                                </div>
                            </div>

                            <div class="cart-line-total-slot">
                                @if ($line->quantity > 1)
                                    <p class="cart-line-unit-price">{{ $line->formattedUnitPrice() }} × {{ $line->quantity }}</p>
                                @endif
                                <p class="cart-line-total">{{ $line->formattedLineTotal() }}</p>
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
                    @if ($freeShippingUnlocked)
                        <span class="badge badge-active cart-summary-free-shipping">{{ __('store.free_shipping_badge') }}</span>
                    @endif
                    <p class="cart-summary-note">{{ __('store.cart_note') }}</p>
                    <a href="{{ localized_route('checkout.show') }}" class="btn btn-primary btn-block">{{ __('store.checkout') }}</a>
                    <a href="{{ localized_route('home') }}" class="btn btn-secondary btn-block">{{ __('store.cart_continue') }}</a>
                </aside>
            </div>
        @endif
    </div>
@endsection
