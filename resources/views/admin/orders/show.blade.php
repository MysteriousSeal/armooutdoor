@extends('layouts.admin')

@section('title', 'Order '.$order->number)

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker"><a href="{{ route('admin.orders.index') }}">Orders</a></p>
                    <div class="admin-order-heading">
                        <h2 class="admin-list-title">{{ $order->number }}</h2>
                        <span class="badge badge-{{ $order->status }}">
                            {{ ucfirst($order->status) }}
                        </span>
                    </div>
                </div>
                @if ($order->status === 'placed')
                    <button type="button" class="btn btn-primary" data-modal-open="prepare-confirm-modal">Mark as being prepared</button>
                @elseif ($order->status === 'preparing')
                    <button type="button" class="btn btn-primary" data-modal-open="ship-confirm-modal">Mark as shipped</button>
                @endif
            </div>
            <p class="admin-list-lede admin-list-lede--wide">
                {{ $order->created_at->format('d M Y · H:i') }}
                @if ($order->user)
                    · {{ $order->user->name }} · {{ $order->user->email }}
                @else
                    · Deleted customer
                @endif
            </p>
            @if ($order->payment_method || $order->carrierName() !== '')
                <div class="admin-list-meta">
                    @if ($order->payment_method)
                        <span class="admin-list-chip">{{ $order->payment_method->label() }}</span>
                    @endif
                    @if ($order->carrierName() !== '')
                        <span class="admin-list-chip">{{ $order->carrierName() }}</span>
                    @endif
                </div>
            @endif
        </header>

        <div class="admin-order-layout">
            <div class="order-main">
                <section class="order-panel">
                    <h3 class="order-panel-title">Items</h3>
                    <ul class="order-items">
                        @foreach ($order->items as $item)
                            <li class="order-item">
                                @if ($item->image)
                                    <span class="order-item-media">
                                        <img src="{{ $item->imageUrl() }}" alt="" width="96" height="96">
                                    </span>
                                @endif
                                <div class="order-item-body">
                                    <p class="order-item-name">
                                        @if ($item->product)
                                            <a href="{{ route('admin.products.edit', $item->product) }}">{{ $item->localizedName() }}</a>
                                        @else
                                            {{ $item->localizedName() }}
                                        @endif
                                    </p>
                                    @if ($item->product?->sku)
                                        <p class="order-item-sku">SKU {{ $item->product->sku }}</p>
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
                            <dt>Subtotal</dt>
                            <dd>{{ $order->formattedSubtotal() }}</dd>
                        </div>
                        <div>
                            <dt>Shipping</dt>
                            <dd>{{ $order->formattedShipping() }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="order-panel order-panel--total">
                    <h3 class="order-panel-title">Total</h3>
                    <p class="order-total-amount">{{ $order->formattedTotal() }}</p>
                </section>

                <section class="order-panel">
                    <h3 class="order-panel-title">Status history</h3>
                    <ol class="order-timeline">
                        @foreach ($order->statusHistories as $entry)
                            <li class="order-timeline-item is-{{ $entry->status }}{{ $loop->first ? ' is-current' : '' }}">
                                <span class="order-timeline-marker" aria-hidden="true"></span>
                                <div class="order-timeline-body">
                                    <span class="order-timeline-status">{{ ucfirst($entry->status) }}</span>
                                    <time class="order-timeline-date" datetime="{{ $entry->created_at->toIso8601String() }}">
                                        {{ $entry->created_at->format('d M Y · H:i') }}
                                    </time>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </section>
            </div>

            <aside class="order-facts">
                <section class="order-fact">
                    <h3 class="order-fact-title">Shipping</h3>
                    <p class="order-shipping-summary">
                        {{ $order->carrierName() !== '' ? $order->carrierName() : 'No carrier' }}
                        <span>
                            · {{ $order->carrier_method === \App\Enums\DeliveryMethod::Home ? 'Home delivery' : 'Relay point' }}
                        </span>
                    </p>
                    @if ($order->relay_snapshot)
                        <p class="order-fact-extra">
                            {{ $order->relay_snapshot['name'] }}<br>
                            {{ $order->relay_snapshot['line1'] }}<br>
                            {{ $order->relay_snapshot['postal_code'] }} {{ $order->relay_snapshot['city'] }}
                        </p>
                    @endif

                    <form method="POST" action="{{ route('admin.orders.tracking.update', $order) }}" class="order-shipping-form">
                        @csrf
                        @method('PATCH')
                        <div class="order-shipping-field">
                            <label for="tracking_carrier_id">Tracking carrier</label>
                            <div class="order-shipping-select-wrap">
                                <select id="tracking_carrier_id" name="tracking_carrier_id" class="order-shipping-select">
                                    <option value="">Select a carrier</option>
                                    @foreach ($carriers as $carrier)
                                        <option
                                            value="{{ $carrier->id }}"
                                            @selected(old('tracking_carrier_id', $order->tracking_carrier_id ?? $order->carrier_id) == $carrier->id)
                                        >
                                            {{ $carrier->localizedName() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="order-shipping-field">
                            <label for="tracking_number">Tracking number</label>
                            <input
                                type="text"
                                id="tracking_number"
                                name="tracking_number"
                                class="order-shipping-input"
                                value="{{ old('tracking_number', $order->tracking_number) }}"
                                maxlength="100"
                                placeholder="Add a tracking number"
                                autocomplete="off"
                            >
                        </div>
                        <button type="submit" class="btn btn-secondary btn-block">Save tracking</button>
                    </form>
                </section>

                <section class="order-fact">
                    <h3 class="order-fact-title">Customer</h3>
                    @if ($order->user)
                        <p>
                            {{ $order->user->name }}<br>
                            {{ $order->user->email }}
                        </p>
                    @else
                        <p>Deleted customer</p>
                    @endif
                </section>

                <section class="order-fact">
                    <h3 class="order-fact-title">Shipping address</h3>
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
                        <h3 class="order-fact-title">Billing address</h3>
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
                    </section>
                @endif

                @if ($order->payment_method)
                    <section class="order-fact">
                        <h3 class="order-fact-title">Payment</h3>
                        <p>{{ $order->payment_method->label() }}</p>
                    </section>
                @endif
            </aside>
        </div>

        @if ($order->status === 'placed')
            <dialog id="prepare-confirm-modal" class="modal" aria-labelledby="prepare-confirm-title">
                <form method="POST" action="{{ route('admin.orders.prepare', $order) }}">
                    @csrf
                    @method('PATCH')
                    <p class="modal-kicker">{{ $order->number }}</p>
                    <h3 class="modal-title" id="prepare-confirm-title">Mark as being prepared?</h3>
                    <p class="modal-body">
                        This will set the order status to <strong>Preparing</strong>.
                        The customer will see the update on their order.
                    </p>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-primary">Mark as being prepared</button>
                    </div>
                </form>
            </dialog>
        @elseif ($order->status === 'preparing')
            <dialog id="ship-confirm-modal" class="modal" aria-labelledby="ship-confirm-title">
                <form method="POST" action="{{ route('admin.orders.ship', $order) }}">
                    @csrf
                    @method('PATCH')
                    <p class="modal-kicker">{{ $order->number }}</p>
                    <h3 class="modal-title" id="ship-confirm-title">Mark as shipped?</h3>
                    <p class="modal-body">
                        This will set the order status to <strong>Shipped</strong>.
                        The customer will see the update on their order.
                    </p>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-primary">Mark as shipped</button>
                    </div>
                </form>
            </dialog>
        @endif
    </div>
@endsection
