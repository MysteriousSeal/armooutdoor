@extends('layouts.app')

@section('title', __('store.account_wishlist').' — '.config('app.name'))
@section('canonical', localized_route('account.wishlist.index'))

@section('content')
    <div class="container account-page">
        <nav class="breadcrumbs" aria-label="breadcrumb">
            <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <a href="{{ localized_route('account.index') }}">{{ __('store.account_title') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <span>{{ __('store.account_wishlist') }}</span>
        </nav>

        <header class="page-header">
            <h2 class="page-title">{{ __('store.account_wishlist') }}</h2>
            <p class="page-lede">{{ __('store.account_wishlist_lede') }}</p>
        </header>

        @include('account.nav')

        @if ($products->isEmpty())
            <p class="checkout-hint">{{ __('store.wishlist_empty') }}</p>
        @else
            <div class="product-grid">
                @foreach ($products as $product)
                    @include('partials.product-card', ['product' => $product])
                @endforeach
            </div>
        @endif
    </div>
@endsection
