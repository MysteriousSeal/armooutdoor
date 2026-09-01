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
                                @if ($ageMark && $ordersNeedingProof->contains($order->id))
                                    {{-- Two marks, not one: « à fournir » is something the
                                         customer must act on, « en cours » is something
                                         already being seen to. A warning they cannot clear
                                         only makes the real ones harder to spot. --}}
                                    <span class="order-age-mark order-age-mark--{{ $ageMark }}">
                                        <span class="order-age-mark-dot" aria-hidden="true"></span>
                                        {{ $ageMark === 'pending' ? __('store.orders_age_pending') : __('store.orders_age_action') }}
                                    </span>
                                @endif
                            </div>
                            <p class="order-list-meta">
                                {{ $order->created_at->translatedFormat('d F Y') }}
                                · {{ trans_choice('store.cart_count', $order->items_count, ['count' => $order->items_count]) }}
                            </p>
                        </div>
                        <p class="order-list-total">{{ $order->formattedTotal() }}</p>
                        <div class="order-list-actions">
                            @if ($order->invoiceIsAvailable())
                                <a
                                    href="{{ localized_route('orders.invoice', ['order' => $order->number]) }}"
                                    class="admin-table-icon-btn"
                                    title="{{ __('store.order_download_invoice') }}"
                                    aria-label="{{ __('store.order_download_invoice') }}"
                                >
                                    <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
                                        <path d="M12 4v11m0 0-4-4m4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M5 17v2a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-2" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </a>
                            @endif
                            <a href="{{ localized_route('orders.show', ['order' => $order->number]) }}" class="btn btn-sm btn-secondary">
                                {{ __('store.order_view') }}
                            </a>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
