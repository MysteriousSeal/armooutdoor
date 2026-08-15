@extends('layouts.app')

@section('title', __('store.order_title', ['number' => $order->number]).' — '.config('app.name'))

@section('content')
    <div class="container">
        <nav class="breadcrumbs" aria-label="breadcrumb">
            <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <a href="{{ localized_route('account.index') }}">{{ __('store.account_title') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <a href="{{ localized_route('orders.index') }}">{{ __('store.account_orders') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <span>{{ __('store.order_title', ['number' => $order->number]) }}</span>
        </nav>

        <header class="order-hero">
            <p class="home-kicker">{{ __('store.order_confirmed') }}</p>
            <h2 class="page-title">{{ $order->number }}</h2>
            <p class="page-lede">{{ $order->statusMessage() }}</p>
        </header>

        <div class="order-layout">
            <div class="order-main">
                <section class="order-panel" aria-labelledby="order-items-title">
                    <h3 class="order-panel-title" id="order-items-title">{{ __('store.order_items') }}</h3>
                    <ul class="order-items">
                        @foreach ($order->items as $item)
                            <li class="order-item">
                                @if ($item->image)
                                    <span class="order-item-media">
                                        <img src="{{ $item->imageUrl() }}" alt="" width="96" height="96">
                                    </span>
                                @endif
                                <div class="order-item-body">
                                    <p class="order-item-name">{{ $item->localizedName() }}</p>
                                    @if ($item->variant_label)
                                        <p class="order-item-sku">{{ $item->variant_label }}</p>
                                    @elseif ($item->product?->sku)
                                        <p class="order-item-sku">SKU : {{ $item->product->sku }}</p>
                                    @endif
                                    <p class="order-item-meta">× {{ $item->quantity }} · {{ format_euros($item->unit_price_cents) }}</p>
                                </div>
                                <p class="order-item-price">{{ $item->formattedLineTotal() }}</p>
                            </li>
                        @endforeach
                    </ul>
                </section>

                <section class="order-panel">
                    <dl class="order-totals">
                        <div>
                            <dt>{{ __('store.subtotal') }}</dt>
                            <dd>{{ $order->formattedSubtotal() }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('store.shipping') }}</dt>
                            <dd>{{ $order->formattedShipping() }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="order-panel order-panel--total" aria-labelledby="order-totals-title">
                    <h3 class="order-panel-title" id="order-totals-title">{{ __('store.order_total') }}</h3>
                    <p class="order-total-amount">{{ $order->formattedTotal() }}</p>
                    @if ($order->status === 'refunded')
                        <p class="order-refunded-note">
                            {{ __('store.order_refunded_amount', ['amount' => $order->formattedTotal()]) }}
                        </p>
                    @endif
                </section>

                @if ($order->invoiceIsAvailable())
                    <div class="order-panel-actions">
                        <a href="{{ localized_route('orders.invoice', ['order' => $order->number]) }}" class="btn btn-secondary">
                            {{ __('store.order_download_invoice') }}
                        </a>
                    </div>
                @endif

                @if ($order->hasTracking())
                    <section class="order-panel" aria-labelledby="order-tracking-title">
                        <h3 class="order-panel-title" id="order-tracking-title">{{ __('store.order_tracking') }}</h3>
                        <dl class="order-totals">
                            @if ($order->trackingCarrierName() !== '')
                                <div>
                                    <dt>{{ __('store.order_tracking_carrier') }}</dt>
                                    <dd>{{ $order->trackingCarrierName() }}</dd>
                                </div>
                            @endif
                            <div>
                                <dt>{{ __('store.order_tracking_number') }}</dt>
                                <dd class="order-tracking-number">{{ $order->tracking_number }}</dd>
                            </div>
                            @if ($order->package_type_name)
                                <div>
                                    <dt>{{ __('store.order_tracking_package_type') }}</dt>
                                    <dd>{{ $order->package_type_name }}</dd>
                                </div>
                            @endif
                        </dl>
                    </section>
                @endif

                <section class="order-panel" aria-labelledby="order-history-title">
                    <h3 class="order-panel-title" id="order-history-title">{{ __('store.order_history') }}</h3>
                    <ol class="order-timeline">
                        @foreach ($order->statusHistories as $entry)
                            <li class="order-timeline-item is-{{ $entry->status }}{{ $loop->first ? ' is-current' : '' }}">
                                <span class="order-timeline-marker" aria-hidden="true"></span>
                                <div class="order-timeline-body">
                                    <span class="order-timeline-status">{{ __('store.order_status_'.$entry->status) }}</span>
                                    <time class="order-timeline-date" datetime="{{ $entry->created_at->toIso8601String() }}">
                                        {{ $entry->created_at->translatedFormat('d F Y · H:i') }}
                                    </time>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </section>
            </div>

            <aside class="order-facts">
                <section class="order-fact">
                    <h3 class="order-fact-title">{{ __('store.order_address') }}</h3>
                    <p>
                        {{ $order->address_snapshot['first_name'] }} {{ $order->address_snapshot['last_name'] }}<br>
                        {{ $order->address_snapshot['line1'] }}
                        @if (! empty($order->address_snapshot['line2']))
                            <br>{{ $order->address_snapshot['line2'] }}
                        @endif
                        <br>{{ $order->address_snapshot['postal_code'] }} {{ $order->address_snapshot['city'] }}
                        <br>{{ __('store.country_'.$order->address_snapshot['country']) }}
                        @if (! empty($order->address_snapshot['phone']))
                            <br>{{ $order->address_snapshot['phone'] }}
                        @endif
                    </p>
                </section>

                @if ($order->billing_address_snapshot)
                    <section class="order-fact">
                        <h3 class="order-fact-title">{{ __('store.order_billing_address') }}</h3>
                        @if ($order->hasSeparateBillingAddress())
                            <p>
                                {{ $order->billing_address_snapshot['first_name'] }} {{ $order->billing_address_snapshot['last_name'] }}<br>
                                {{ $order->billing_address_snapshot['line1'] }}
                                @if (! empty($order->billing_address_snapshot['line2']))
                                    <br>{{ $order->billing_address_snapshot['line2'] }}
                                @endif
                                <br>{{ $order->billing_address_snapshot['postal_code'] }} {{ $order->billing_address_snapshot['city'] }}
                                <br>{{ __('store.country_'.$order->billing_address_snapshot['country']) }}
                                @if (! empty($order->billing_address_snapshot['phone']))
                                    <br>{{ $order->billing_address_snapshot['phone'] }}
                                @endif
                            </p>
                        @else
                            <p>{{ __('store.billing_same_as_shipping') }}</p>
                        @endif
                    </section>
                @endif

                <section class="order-fact">
                    <h3 class="order-fact-title">{{ __('store.order_carrier') }}</h3>
                    <p>
                        {{ $order->carrierName() }}
                        · {{ $order->carrier_method === \App\Enums\DeliveryMethod::Home ? __('store.shipping_home') : __('store.shipping_relay') }}
                    </p>
                    @if ($order->relay_snapshot)
                        <p class="order-fact-extra">
                            {{ $order->relay_snapshot['name'] }}<br>
                            {{ $order->relay_snapshot['line1'] }}<br>
                            {{ $order->relay_snapshot['postal_code'] }} {{ $order->relay_snapshot['city'] }}
                        </p>
                    @endif
                </section>

                @if ($order->payment_method)
                    <section class="order-fact">
                        <h3 class="order-fact-title">{{ __('store.order_payment') }}</h3>
                        <p>{{ $order->payment_method->label() }}</p>
                    </section>
                @endif

                <a href="{{ localized_route('home') }}" class="btn btn-primary btn-block">{{ __('store.back_to_shop') }}</a>
            </aside>
        </div>
    </div>
@endsection
