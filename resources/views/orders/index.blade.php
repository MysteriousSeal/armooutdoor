@extends('layouts.app')

@section('title', __('store.account_orders').' — '.config('app.name'))
@section('canonical', localized_route('orders.index'))

@section('content')
    <div class="container account-page">
        <nav class="breadcrumbs" aria-label="breadcrumb">
            <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <a href="{{ localized_route('account.index') }}">{{ __('store.account_title') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <span>{{ __('store.account_orders') }}</span>
        </nav>

        <header class="page-header">
            <h2 class="page-title">{{ __('store.account_orders') }}</h2>
            <p class="page-lede">{{ __('store.account_orders_lede') }}</p>
        </header>

        @include('account.nav')

        @if ($orders->isEmpty())
            <p class="checkout-hint">{{ __('store.orders_empty') }}</p>
        @else
            <ul class="order-list">
                @foreach ($orders as $order)
                    <li class="order-list-item">
                        <div class="order-list-body">
                            <div class="order-list-heading">
                                <p class="order-list-number">{{ $order->number }}</p>
                                <span class="order-status is-{{ $order->status }}">
                                    {{ __('store.order_status_'.$order->status) }}
                                </span>
                            </div>
                            <p class="order-list-meta">
                                {{ $order->created_at->translatedFormat('d F Y') }}
                                · {{ trans_choice('store.cart_count', $order->items_count, ['count' => $order->items_count]) }}
                            </p>
                        </div>
                        <p class="order-list-total">{{ $order->formattedTotal() }}</p>
                        <a href="{{ localized_route('orders.show', ['order' => $order->number]) }}" class="btn btn-sm btn-secondary">
                            {{ __('store.order_view') }}
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
