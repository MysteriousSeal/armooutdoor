@extends('layouts.admin')

@section('title', 'Order '.$order->number)

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker"><a href="{{ route('admin.orders.index') }}">Orders</a></p>
                    <div class="admin-order-heading" id="order-heading">
                        {{-- Un voisin manquant reste en place, désactivé : la
                             barre ne doit pas se réorganiser sur la première
                             et la dernière commande. --}}
                        <span class="order-stepper" role="group" aria-label="Browse orders">
                            @if ($previousOrder)
                                <a
                                    href="{{ route('admin.orders.show', $previousOrder) }}"
                                    class="order-stepper-btn"
                                    title="Newer order · {{ $previousOrder->number }}"
                                    aria-label="Newer order {{ $previousOrder->number }}"
                                    rel="prev"
                                >&#8249;</a>
                            @else
                                <span class="order-stepper-btn is-disabled" aria-hidden="true">&#8249;</span>
                            @endif
                            @if ($nextOrder)
                                <a
                                    href="{{ route('admin.orders.show', $nextOrder) }}"
                                    class="order-stepper-btn"
                                    title="Older order · {{ $nextOrder->number }}"
                                    aria-label="Older order {{ $nextOrder->number }}"
                                    rel="next"
                                >&#8250;</a>
                            @else
                                <span class="order-stepper-btn is-disabled" aria-hidden="true">&#8250;</span>
                            @endif
                        </span>
                        {{-- Le titre reste un titre : le bouton est à
                             l'intérieur. Avec `role="button"` sur le h2, les
                             lecteurs d'écran annonçaient un bouton et perdaient
                             l'en-tête de la page. --}}
                        <h2 class="admin-list-title">
                            <button
                                type="button"
                                class="admin-title-copy"
                                data-copy-code="{{ $order->number }}"
                                title="Copy this order number"
                                aria-label="Copy order number {{ $order->number }}"
                            >
                                <span class="admin-title-copy-value">{{ $order->number }}</span>
                                <svg class="admin-title-copy-icon" viewBox="0 0 24 24" width="15" height="15" aria-hidden="true">
                                    <rect x="9" y="9" width="11" height="11" rx="2" fill="none" stroke="currentColor" stroke-width="2"/>
                                    <path d="M5 15V5a2 2 0 0 1 2-2h10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </button>
                        </h2>
                        <span class="badge badge-{{ $order->status }}">
                            {{ $order->statusLabel() }}
                        </span>
                        @if ($order->isArchived())
                            <span class="badge badge-disabled">Archived</span>
                        @endif
                        @if ($order->isTest())
                            <span class="badge badge-test" title="Kept as a record of testing; left out of every figure">Test</span>
                        @endif
                    </div>
                </div>
                <div class="admin-order-actions" id="order-actions">
                    @if ($order->isDraft())
                        {{-- Le bouton n'apparaît qu'avec JavaScript : sans lui la
                             modale ne s'ouvre pas, et valider une commande sans
                             confirmation ne se rattrape pas. --}}
                        <button type="button" class="btn btn-primary" data-modal-open="validate-draft-modal" data-draft-validate hidden>Validate draft</button>
                        <a href="{{ route('admin.orders.edit', $order) }}" class="btn btn-secondary">Edit draft</a>
                    @else
                        @if ($order->status === 'placed')
                            <button type="button" class="btn btn-primary" data-modal-open="prepare-confirm-modal">Mark as being prepared</button>
                        @elseif ($order->status === 'preparing')
                            <button type="button" class="btn btn-primary" data-modal-open="ship-confirm-modal">Mark as shipped</button>
                        @elseif ($order->status === 'shipped')
                            <button type="button" class="btn btn-primary" data-modal-open="in-transit-confirm-modal">Mark as in transit</button>
                        @elseif ($order->status === 'in_transit')
                            <button type="button" class="btn btn-primary" data-modal-open="deliver-confirm-modal">Mark as delivered</button>
                        @endif
                        @if ($order->status !== 'refunded' && auth()->user()->isOwner())
                            <button type="button" class="btn btn-secondary" data-modal-open="refund-confirm-modal">Mark as refunded</button>
                        @endif
                    @endif
                    @if ($order->canBeDeleted())
                        @if (auth()->user()->isOwner())
                            <button type="button" class="btn btn-danger" data-modal-open="delete-confirm-modal">Delete draft</button>
                        @endif
                    @elseif ($order->isArchived())
                        <button type="button" class="btn btn-secondary" data-modal-open="unarchive-confirm-modal">Unarchive</button>
                    @else
                        <button type="button" class="btn btn-secondary" data-modal-open="archive-confirm-modal">Archive</button>
                    @endif
                    @if (auth()->user()->isOwner() && $order->canBeMarkedAsTest())
                        @if ($order->isTest())
                            <button type="button" class="btn btn-secondary" data-modal-open="untest-confirm-modal">Unmark as test</button>
                        @else
                            <button type="button" class="btn btn-secondary" data-modal-open="test-confirm-modal">Mark as test</button>
                        @endif
                    @endif
                </div>
            </div>
            <p class="admin-list-lede admin-list-lede--wide">
                {{ $order->created_at->format('d M Y · H:i') }}
                @if ($order->user)
                    · <a href="{{ route('admin.customers.show', $order->user) }}">{{ $order->user->name }}</a>
                    @if ($order->user->email)
                        · {{ $order->user->email }}
                    @endif
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
                                <span class="order-item-media">
                                    @if ($item->image)
                                        <img src="{{ $item->imageUrl() }}" alt="" width="96" height="96">
                                    @endif
                                </span>
                                <div class="order-item-body">
                                    <p class="order-item-name">
                                        @if ($item->product)
                                            <a href="{{ route('admin.products.edit', $item->product) }}">{{ $item->localizedName() }}</a>
                                        @else
                                            {{ $item->localizedName() }}
                                        @endif
                                    </p>
                                    @if ($item->variant_label)
                                        <p class="order-item-variant">
                                            <span class="order-item-variant-value">{{ $item->variant_label }}</span>
                                        </p>
                                    @endif
                                    @if ($item->resolvedSku())
                                        <p class="order-item-sku">
                                            SKU
                                            {{-- Le même mécanisme que le numéro de commande, en
                                                 plus visible : ici rien n'annoncerait qu'on peut
                                                 cliquer. --}}
                                            <button
                                                type="button"
                                                class="order-item-sku-copy"
                                                data-copy-code="{{ $item->resolvedSku() }}"
                                                title="Copy this SKU"
                                                aria-label="Copy SKU {{ $item->resolvedSku() }}"
                                            >
                                                <span class="order-item-sku-value">{{ $item->resolvedSku() }}</span>
                                                <svg class="order-item-sku-icon" viewBox="0 0 24 24" width="12" height="12" aria-hidden="true">
                                                    <rect x="9" y="9" width="11" height="11" rx="2" fill="none" stroke="currentColor" stroke-width="2"/>
                                                    <path d="M5 15V5a2 2 0 0 1 2-2h10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                </svg>
                                            </button>
                                        </p>
                                    @endif
                                    @if ($item->product && ! $item->product->inStock() && $item->product->supplier_id && $item->product->available_at_supplier)
                                        <p class="order-item-supplier-note">
                                            Out of stock — available from {{ $item->product->supplier->name }}
                                            @if ($item->product->supplier->lead_time_days !== null)
                                                ({{ $item->product->supplier->lead_time_days }} day{{ $item->product->supplier->lead_time_days === 1 ? '' : 's' }} lead time)
                                            @endif
                                        </p>
                                    @endif
                                    @if ($item->hasDiscount() && $item->discount_label)
                                        <div class="order-item-discount">
                                            <span class="order-discount-badge">{{ $item->discount_label }}</span>
                                        </div>
                                    @endif
                                    @if ($order->status === 'refunded' && $item->product)
                                        @if ($item->quantityRestockable() > 0)
                                            <form method="POST" action="{{ route('admin.orders.items.restock', [$order, $item]) }}" class="order-restock-form">
                                                @csrf
                                                @method('PATCH')
                                                <label class="sr-only" for="restock-qty-{{ $item->id }}">Quantity to restock for {{ $item->localizedName() }}</label>
                                                <input
                                                    type="number"
                                                    id="restock-qty-{{ $item->id }}"
                                                    name="quantity_{{ $item->id }}"
                                                    value="{{ old('quantity_'.$item->id, $item->quantityRestockable()) }}"
                                                    min="1"
                                                    max="{{ $item->quantityRestockable() }}"
                                                    class="form-control order-restock-input"
                                                >
                                                <button type="submit" class="btn btn-sm btn-secondary">Restock</button>
                                                @if ($item->restocked_quantity > 0)
                                                    <span class="order-restock-note">{{ $item->restocked_quantity }} already back on the shelf</span>
                                                @endif
                                            </form>
                                            @error('quantity_'.$item->id) <p class="form-error">{{ $message }}</p> @enderror
                                        @else
                                            <p class="order-restock-done">
                                                Restocked
                                                @if ($item->restocked_at)
                                                    {{ $item->restocked_at->format('d/m/Y') }}
                                                @endif
                                                @if ($item->restockedBy)
                                                    by {{ $item->restockedBy->name }}
                                                @endif
                                            </p>
                                        @endif
                                    @endif
                                </div>
                                <div class="order-item-pricing">
                                    @if ($item->quantity > 1 || $item->hasDiscount())
                                        <p class="order-item-qty">× {{ $item->quantity }}</p>
                                        <p class="order-item-unit">
                                            @if ($item->hasDiscount())
                                                <span class="card-price-original">{{ $item->formattedOriginalUnitPrice() }}</span>
                                            @endif
                                            {{ format_euros($item->unit_price_cents) }}
                                        </p>
                                    @endif
                                    <p class="order-item-price">{{ $item->formattedLineTotal() }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>

                <section class="order-panel">
                    @php($discountedItems = $order->discountedItems())
                    <dl class="order-totals">
                        <div>
                            <dt>Subtotal</dt>
                            <dd>{{ $order->formattedFullSubtotal() }}</dd>
                        </div>
                        @if ($order->hasDiscountCode())
                            <div>
                                <dt>{{ $order->discountCodeCode() ? 'Code '.$order->discountCodeCode() : 'Discount' }}</dt>
                                {{-- A shipping code takes nothing off the goods; its value is
                                     on the "Free delivery" line below. --}}
                                <dd>
                                    {{ $order->discountCodeWasFreeRelayShipping()
                                        ? 'Free relay delivery'
                                        : '-'.$order->formattedDiscountCents() }}
                                </dd>
                            </div>
                        @endif
                        @if ($order->shipping_discount_cents > 0)
                            <div>
                                <dt>Free delivery</dt>
                                <dd>-{{ format_euros($order->shipping_discount_cents) }}</dd>
                            </div>
                        @endif
                    </dl>
                    @if ($discountedItems->isNotEmpty())
                        <div class="order-reductions">
                            <p class="order-reductions-title">Discount</p>
                            <ul class="order-reductions-list">
                                @foreach ($discountedItems as $item)
                                    <li class="order-reductions-row">
                                        <div class="order-reductions-copy">
                                            <p class="order-reductions-name">{{ $item->localizedName() }}</p>
                                            <span class="order-discount-badge">{{ $item->discount_label }}</span>
                                        </div>
                                        <p class="order-reductions-amount">−{{ format_euros($item->discountCents()) }}</p>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <dl class="order-totals">
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

                {{-- Ce que la commande n'a pas encore : la facture peut se
                     télécharger avant, mais l'admin doit le savoir avant de
                     l'envoyer au client. --}}
                @php($missingInvoiceFields = array_filter([$order->tracking_carrier_id === null ? 'Tracking carrier' : null, blank($order->tracking_number) ? 'Tracking number' : null, $order->package_type_id === null ? 'Package type' : null]))
                <div id="order-downloads">
                @if (! $order->isDraft() || $order->adminInvoiceIsAvailable())
                    <div class="order-panel-actions">
                        @if (! $order->isDraft())
                            <a href="{{ route('admin.orders.delivery-slip', $order) }}" class="btn btn-secondary">Download delivery slip</a>
                        @endif
                        @if ($order->adminInvoiceIsAvailable())
                            <a
                                href="{{ route('admin.orders.invoice', $order) }}"
                                class="btn btn-secondary"
                                @if ($missingInvoiceFields !== [])
                                    data-confirm-modal="invoice-warning-modal"
                                @endif
                            >Download invoice</a>
                        @endif
                    </div>
                @endif
                </div>

                <div class="order-facts-row">
                    <section class="order-fact">
                        <h3 class="order-fact-title">Customer</h3>
                        @if ($order->user)
                            <p>
                                {{ $order->user->name }}
                                @if ($order->user->email)
                                    <br>{{ $order->user->email }}
                                @endif
                            </p>
                        @else
                            <p>Deleted customer</p>
                        @endif
                    </section>

                    <section class="order-fact">
                        <div class="order-fact-heading">
                            <h3 class="order-fact-title">Shipping address</h3>
                            @if ($order->addressIsEditable())
                                <button type="button" class="footer-text-btn" data-modal-open="edit-shipping-address-modal">Edit</button>
                            @endif
                        </div>
                        <p>
                            {{ format_person_name($order->address_snapshot['first_name'], $order->address_snapshot['last_name']) }}<br>
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
                            <div class="order-fact-heading">
                                <h3 class="order-fact-title">Billing address</h3>
                                @if ($order->addressIsEditable())
                                    <button type="button" class="footer-text-btn" data-modal-open="edit-billing-address-modal">Edit</button>
                                @endif
                            </div>
                            <p>
                                {{ format_person_name($order->billing_address_snapshot['first_name'], $order->billing_address_snapshot['last_name']) }}<br>
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
                            <p>
                                {{ $order->payment_method->label() }}
                                @if ($order->payment_method === \App\Enums\PaymentMethod::Card)
                                    (Stripe)
                                @endif
                            </p>

                            @if (($order->stripe_payment_intent_id || $order->stripe_customer_id || $order->payment_fee_cents) && auth()->user()->isOwner())
                                <dl class="stripe-meta">
                                    @if ($order->payment_fee_cents)
                                        <div class="stripe-meta-row">
                                            <dt>Payment processing fee</dt>
                                            <dd>
                                                <span class="stripe-fee-chip">− {{ format_euros($order->payment_fee_cents) }}</span>
                                                @if ($order->formattedPaymentFeePercentage())
                                                    <span class="stripe-fee-percentage">{{ $order->formattedPaymentFeePercentage() }} of total</span>
                                                @endif
                                            </dd>
                                        </div>
                                    @endif
                                    @if ($order->stripe_payment_intent_id)
                                        <div class="stripe-meta-row">
                                            <dt>Payment intent</dt>
                                            <dd>
                                                @if ($stripePaymentIntentUrl)
                                                    <a href="{{ $stripePaymentIntentUrl }}" target="_blank" rel="noopener noreferrer" class="stripe-meta-chip">
                                                        {{ $order->stripe_payment_intent_id }}
                                                    </a>
                                                @else
                                                    <span class="stripe-meta-chip">{{ $order->stripe_payment_intent_id }}</span>
                                                @endif
                                            </dd>
                                        </div>
                                    @endif
                                    @if ($order->stripe_customer_id)
                                        <div class="stripe-meta-row">
                                            <dt>Stripe customer</dt>
                                            <dd>
                                                @if ($stripeCustomerUrl)
                                                    <a href="{{ $stripeCustomerUrl }}" target="_blank" rel="noopener noreferrer" class="stripe-meta-chip">
                                                        {{ $order->stripe_customer_id }}
                                                    </a>
                                                @else
                                                    <span class="stripe-meta-chip">{{ $order->stripe_customer_id }}</span>
                                                @endif
                                            </dd>
                                        </div>
                                    @endif
                                </dl>
                            @endif
                        </section>
                    @endif
                </div>

                <section class="order-panel" id="order-timeline">
                    <h3 class="order-panel-title">Status history</h3>
                    <ol class="order-timeline">
                        @foreach ($order->statusHistories as $entry)
                            <li class="order-timeline-item is-{{ $entry->status }}{{ $loop->first ? ' is-current' : '' }}">
                                <span class="order-timeline-marker" aria-hidden="true"></span>
                                <div class="order-timeline-body">
                                    <span class="order-timeline-status">{{ \App\Models\Order::labelForStatus($entry->status) }}</span>
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
                @if ($order->is_manual && $order->marketplace_id)
                    <section class="order-fact">
                        <h3 class="order-fact-title">Marketplace</h3>
                        <p>{{ $order->marketplace_name }}</p>

                        <form method="POST" action="{{ route('admin.orders.marketplace-commission.update', $order) }}" class="order-shipping-form">
                            @csrf
                            @method('PATCH')
                            <div class="order-shipping-field">
                                <label for="marketplace_commission">Commission (EUR)</label>
                                <input
                                    type="number"
                                    id="marketplace_commission"
                                    name="marketplace_commission"
                                    class="order-shipping-input"
                                    value="{{ old('marketplace_commission', $order->marketplace_commission_cents !== null ? number_format($order->marketplace_commission_cents / 100, 2, '.', '') : '') }}"
                                    min="0"
                                    max="99999.99"
                                    step="0.01"
                                    placeholder="e.g. 5.00"
                                >
                                @error('marketplace_commission') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <button type="submit" class="btn btn-secondary btn-block">Save commission</button>
                        </form>

                        <form method="POST" action="{{ route('admin.orders.shipping-paid.update', $order) }}" class="order-shipping-form">
                            @csrf
                            @method('PATCH')
                            <div class="order-shipping-field">
                                <label for="shipping_paid">Shipping paid (EUR)</label>
                                <input
                                    type="number"
                                    id="shipping_paid"
                                    name="shipping_paid"
                                    class="order-shipping-input"
                                    value="{{ old('shipping_paid', $order->shipping_paid_cents !== null ? number_format($order->shipping_paid_cents / 100, 2, '.', '') : '') }}"
                                    min="0"
                                    max="99999.99"
                                    step="0.01"
                                    placeholder="e.g. 5.00"
                                >
                                @error('shipping_paid') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <button type="submit" class="btn btn-secondary btn-block">Save shipping paid</button>
                        </form>
                    </section>
                @endif

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

                    @if ($order->hasTracking())
                        <div class="order-tracking-link">
                            <span class="order-tracking-link-label">Tracking number</span>
                            @if ($order->trackingUrl())
                                <a
                                    href="{{ $order->trackingUrl() }}"
                                    class="order-tracking-link-value"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    title="Follow this parcel on {{ $order->trackingCarrierName() }}"
                                >
                                    <span>{{ $order->tracking_number }}</span>
                                    <svg viewBox="0 0 24 24" width="13" height="13" aria-hidden="true">
                                        <path d="M14 4h6v6" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M20 4 11 13" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                                        <path d="M18 14.5V19a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 4 19V8a1.5 1.5 0 0 1 1.5-1.5H10" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </a>
                            @else
                                {{-- Transporteur sans page de suivi connue : le
                                     numéro reste lisible et copiable. --}}
                                <span class="order-tracking-link-value is-plain">{{ $order->tracking_number }}</span>
                            @endif
                        </div>
                    @endif

                    @unless ($order->isDraft())
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
                            <div class="order-shipping-field">
                                <label for="package_type_id">Package type</label>
                                <div class="order-shipping-select-wrap">
                                    <select id="package_type_id" name="package_type_id" class="order-shipping-select">
                                        <option value="">Select a package type</option>
                                        @foreach ($packageTypes as $packageType)
                                            <option
                                                value="{{ $packageType->id }}"
                                                @selected(old('package_type_id', $order->package_type_id) == $packageType->id)
                                            >
                                                {{ $packageType->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                @if ($order->package_type_name && ! $order->packageType)
                                    <p class="form-hint">Previously saved as "{{ $order->package_type_name }}" (removed from the package type list).</p>
                                @endif
                            </div>
                            <button type="submit" class="btn btn-secondary btn-block">Save tracking</button>
                        </form>
                    @endunless
                </section>

                @unless ($order->isDraft())
                    <section class="order-fact">
                        <h3 class="order-fact-title">Thank-you code</h3>
                        @php($giftCode = $order->generatedDiscountCode)
                        @if ($giftCode)
                            <div class="order-gift-code">
                                <span class="order-gift-code-value">{{ $giftCode->code }}</span>
                                <span class="order-gift-code-meta">10% · single use · any customer</span>
                                <span class="order-gift-code-meta">
                                    @if ($giftCode->isExpired())
                                        Expired {{ $giftCode->formattedEndsAt() }}
                                    @elseif ($giftCode->hasLimitedQuantity() && $giftCode->quantity <= 0)
                                        Used
                                    @else
                                        Valid until {{ $giftCode->formattedEndsAt() }}
                                    @endif
                                </span>
                                <div class="order-gift-code-actions">
                                    <a href="{{ route('admin.discount-codes.label', $giftCode) }}" class="btn btn-sm btn-secondary">PDF</a>
                                    <a href="{{ route('admin.discount-codes.edit', $giftCode) }}" class="btn btn-sm btn-secondary">Edit</a>
                                </div>
                            </div>
                        @else
                            <p class="order-fact-extra">
                                A 10% code for the next order — anyone can use it, once,
                                for three months from this order's date.
                            </p>
                            <form method="POST" action="{{ route('admin.orders.discount-code.store', $order) }}" class="order-gift-code-form">
                                @csrf
                                <button type="submit" class="btn btn-secondary btn-block">Create a discount code</button>
                            </form>
                        @endif
                    </section>
                @endunless

            </aside>
        </div>

        <div id="order-modals">
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

        @if ($order->status === 'shipped')
            <dialog id="in-transit-confirm-modal" class="modal" aria-labelledby="in-transit-confirm-title">
                <form method="POST" action="{{ route('admin.orders.in-transit', $order) }}">
                    @csrf
                    @method('PATCH')
                    <p class="modal-kicker">{{ $order->number }}</p>
                    <h3 class="modal-title" id="in-transit-confirm-title">Mark as in transit?</h3>
                    <p class="modal-body">
                        This will set the order status to <strong>In transit</strong>.
                        The customer will see the update on their order.
                    </p>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-primary">Mark as in transit</button>
                    </div>
                </form>
            </dialog>
        @endif

        @if ($order->status === 'in_transit')
            <dialog id="deliver-confirm-modal" class="modal" aria-labelledby="deliver-confirm-title">
                <form method="POST" action="{{ route('admin.orders.deliver', $order) }}">
                    @csrf
                    @method('PATCH')
                    <p class="modal-kicker">{{ $order->number }}</p>
                    <h3 class="modal-title" id="deliver-confirm-title">Mark as delivered?</h3>
                    <p class="modal-body">
                        This will set the order status to <strong>Delivered</strong>.
                        The customer will see the update on their order, and can still be
                        refunded afterwards.
                    </p>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-primary">Mark as delivered</button>
                    </div>
                </form>
            </dialog>
        @endif

        @if (! $order->isDraft() && $order->status !== 'refunded')
            <dialog id="refund-confirm-modal" class="modal" aria-labelledby="refund-confirm-title">
                <form method="POST" action="{{ route('admin.orders.refund', $order) }}">
                    @csrf
                    @method('PATCH')
                    <p class="modal-kicker">{{ $order->number }}</p>
                    <h3 class="modal-title" id="refund-confirm-title">Mark as refunded?</h3>
                    <p class="modal-body">
                        This will set the order status to <strong>Refunded</strong>, regardless of its current status.
                        The customer will see the update on their order.
                    </p>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-primary">Mark as refunded</button>
                    </div>
                </form>
            </dialog>
        @endif

        {{-- Gated like the buttons above: a form staff cannot submit has no
             business being in the page. --}}
        @if (auth()->user()->isOwner() && $order->canBeMarkedAsTest())
        @if ($order->isTest())
            <dialog id="untest-confirm-modal" class="modal" aria-labelledby="untest-confirm-title">
                <form method="POST" action="{{ route('admin.orders.untest', $order) }}">
                    @csrf
                    @method('PATCH')
                    <p class="modal-kicker">{{ $order->number }}</p>
                    <h3 class="modal-title" id="untest-confirm-title">No longer a test order?</h3>
                    <p class="modal-body">
                        It will count towards revenue, the order counts and this customer's
                        lifetime spend again.
                    </p>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-primary">Unmark as test</button>
                    </div>
                </form>
            </dialog>
        @else
            <dialog id="test-confirm-modal" class="modal" aria-labelledby="test-confirm-title">
                <form method="POST" action="{{ route('admin.orders.test', $order) }}">
                    @csrf
                    @method('PATCH')
                    <p class="modal-kicker">{{ $order->number }}</p>
                    <h3 class="modal-title" id="test-confirm-title">Mark this as a test order?</h3>
                    <p class="modal-body">
                        It is kept in full but leaves every figure: revenue, order counts and
                        this customer's lifetime spend. Nothing is undone — the stock it took
                        and the invoice number it used are not given back.
                    </p>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-primary">Mark as test</button>
                    </div>
                </form>
            </dialog>
        @endif
        @endif

        @if ($order->isDraft())
            <dialog id="validate-draft-modal" class="modal" aria-labelledby="validate-draft-title">
                <form method="POST" action="{{ route('admin.orders.validate-draft', $order) }}">
                    @csrf
                    @method('PATCH')
                    <p class="modal-kicker">{{ $order->number }}</p>
                    <h3 class="modal-title" id="validate-draft-title">Validate this draft?</h3>
                    <p class="modal-body">
                        It becomes a real order, waiting to be prepared, and takes its
                        stock — {{ $order->items->sum('quantity') }} {{ Str::plural('unit', $order->items->sum('quantity')) }} off the shelf. There is no button to turn it
                        back into a draft.
                    </p>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-primary">Validate draft</button>
                    </div>
                </form>
            </dialog>
        @endif

        @if ($order->canBeDeleted() && auth()->user()->isOwner())
            <dialog id="delete-confirm-modal" class="modal" aria-labelledby="delete-confirm-title">
                <form method="POST" action="{{ route('admin.orders.destroy', $order) }}">
                    @csrf
                    @method('DELETE')
                    <p class="modal-kicker">{{ $order->number }}</p>
                    <h3 class="modal-title" id="delete-confirm-title">Delete this draft?</h3>
                    <p class="modal-body">
                        The draft and its lines go for good. Nothing here was ever charged
                        or shipped, so there is no record to keep — but this cannot be
                        undone.
                    </p>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete draft</button>
                    </div>
                </form>
            </dialog>
        @endif

        @if ($order->isArchived())
            <dialog id="unarchive-confirm-modal" class="modal" aria-labelledby="unarchive-confirm-title">
                <form method="POST" action="{{ route('admin.orders.unarchive', $order) }}">
                    @csrf
                    @method('PATCH')
                    <p class="modal-kicker">{{ $order->number }}</p>
                    <h3 class="modal-title" id="unarchive-confirm-title">Unarchive this order?</h3>
                    <p class="modal-body">
                        It will reappear in the orders list and dashboard stats.
                    </p>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-primary">Unarchive order</button>
                    </div>
                </form>
            </dialog>
        @else
            <dialog id="archive-confirm-modal" class="modal" aria-labelledby="archive-confirm-title">
                <form method="POST" action="{{ route('admin.orders.archive', $order) }}">
                    @csrf
                    @method('PATCH')
                    <p class="modal-kicker">{{ $order->number }}</p>
                    <h3 class="modal-title" id="archive-confirm-title">Archive this order?</h3>
                    <p class="modal-body">
                        It will be hidden from the orders list and dashboard stats until unarchived.
                    </p>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-primary">Archive order</button>
                    </div>
                </form>
            </dialog>
        @endif

        @if ($order->adminInvoiceIsAvailable() && $missingInvoiceFields !== [])
            <dialog id="invoice-warning-modal" class="modal" aria-labelledby="invoice-warning-title">
                <p class="modal-kicker">{{ $order->number }}</p>
                <h3 class="modal-title" id="invoice-warning-title">Shipping details are incomplete</h3>
                <div class="order-invoice-warning">
                    <p class="order-invoice-warning-lede">Missing before this order can be shipped:</p>
                    <ul class="order-invoice-warning-list">
                        @foreach ($missingInvoiceFields as $field)
                            <li>{{ $field }}</li>
                        @endforeach
                    </ul>
                </div>
                <p class="modal-body">
                    You can still download the invoice now, but it will go out without these details.
                </p>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                    <a href="{{ route('admin.orders.invoice', $order) }}" class="btn btn-primary">Download anyway</a>
                </div>
            </dialog>
        @endif
        </div>

        @if ($order->addressIsEditable())
            <dialog
                id="edit-shipping-address-modal"
                class="modal modal--form"
                aria-labelledby="edit-shipping-address-title"
                @if ($errors->shippingAddress->any()) data-autoopen @endif
            >
                <form method="POST" action="{{ route('admin.orders.address.shipping', $order) }}">
                    @csrf
                    @method('PATCH')
                    <p class="modal-kicker">{{ $order->number }}</p>
                    <h3 class="modal-title" id="edit-shipping-address-title">Edit shipping address</h3>
                    <p class="modal-body">
                        This only changes the address on this order — the customer's saved address is not affected.
                    </p>
                    @include('admin.orders.partials.address-fields', [
                        'snapshot' => $order->address_snapshot,
                        'prefix' => 'shipping',
                        'bag' => 'shippingAddress',
                    ])
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-primary">Save address</button>
                    </div>
                </form>
            </dialog>

            <dialog
                id="edit-billing-address-modal"
                class="modal modal--form"
                aria-labelledby="edit-billing-address-title"
                @if ($errors->billingAddress->any()) data-autoopen @endif
            >
                <form method="POST" action="{{ route('admin.orders.address.billing', $order) }}">
                    @csrf
                    @method('PATCH')
                    <p class="modal-kicker">{{ $order->number }}</p>
                    <h3 class="modal-title" id="edit-billing-address-title">Edit billing address</h3>
                    <p class="modal-body">
                        This only changes the address on this order — the customer's saved address is not affected.
                    </p>
                    @include('admin.orders.partials.address-fields', [
                        'snapshot' => $order->billing_address_snapshot ?? $order->address_snapshot,
                        'prefix' => 'billing',
                        'bag' => 'billingAddress',
                    ])
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-primary">Save address</button>
                    </div>
                </form>
            </dialog>
        @endif
    </div>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/admin-copy-code.js') }}" defer></script>
    <script src="{{ versioned_asset('js/admin-order-status.js') }}" defer></script>
    <script src="{{ versioned_asset('js/admin-draft-validate.js') }}" defer></script>
@endpush
