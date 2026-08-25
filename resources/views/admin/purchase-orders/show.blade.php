@extends('layouts.admin')

@section('title', $purchaseOrder->number)

@section('content')
    <div class="admin-order-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker">Purchase order</p>
                    <h2 class="admin-list-title admin-order-title">
                        {{ $purchaseOrder->number }}
                        <span class="po-status-badge is-{{ str_replace('_', '-', $purchaseOrder->status) }}">{{ ucfirst(str_replace('_', ' ', $purchaseOrder->status)) }}</span>
                    </h2>
                    <p class="admin-list-lede">
                        {{ $purchaseOrder->supplier_name }}
                        @if ($purchaseOrder->expected_at)
                            · expected {{ $purchaseOrder->expected_at->format('d M Y') }}
                        @endif
                        @if ($purchaseOrder->hasVat())
                            · supplier prices included {{ rtrim(rtrim(number_format($purchaseOrder->vatRatePercent(), 1), '0'), '.') }}% VAT
                        @endif
                        @if ($purchaseOrder->createdBy)
                            · drafted by {{ $purchaseOrder->createdBy->name }}
                        @endif
                    </p>
                </div>
                <div class="admin-order-actions">
                    <a href="{{ route('admin.purchase-orders.pdf', $purchaseOrder) }}" class="btn btn-secondary">Download PDF</a>
                    @if ($purchaseOrder->isDraft())
                        <button type="button" class="btn btn-primary" data-modal-open="po-send-modal">Mark as sent</button>
                        <a href="{{ route('admin.purchase-orders.edit', $purchaseOrder) }}" class="btn btn-secondary">Edit draft</a>
                        @if (auth()->user()->isOwner())
                            <button type="button" class="btn btn-danger" data-modal-open="po-delete-modal">Delete draft</button>
                        @endif
                    @endif
                    @if ($purchaseOrder->canBeCancelled() && auth()->user()->isOwner())
                        <button type="button" class="btn btn-secondary" data-modal-open="po-cancel-modal">Cancel order</button>
                    @endif
                </div>
            </div>
        </header>

        <div class="order-layout">
            <div class="order-main">
                @php
                    $canReceive = $purchaseOrder->canReceive();
                @endphp

                <section class="order-panel">
                    <h3 class="order-panel-title">Lines</h3>

                    {{-- La saisie de réception vit dans le tableau : c'est en face de
                         la ligne qu'on sait ce qui est arrivé, pas dans une seconde
                         liste des mêmes articles. --}}
                    @if ($canReceive)
                        <form method="POST" action="{{ route('admin.purchase-orders.receive', $purchaseOrder) }}" class="po-receive-form">
                            @csrf
                            @error('lines') <p class="form-error">{{ $message }}</p> @enderror
                    @endif

                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th class="admin-table-media"></th>
                                    <th>Product</th>
                                    <th>Supplier ref</th>
                                    <th>Ordered</th>
                                    <th>Received</th>
                                    <th>Remaining</th>
                                    <th>Unit cost<span class="po-col-note">excl. VAT</span></th>
                                    <th>Line total<span class="po-col-note">excl. VAT</span></th>
                                    @if ($purchaseOrder->hasVat())
                                        <th>Unit cost<span class="po-col-note">incl. VAT</span></th>
                                        <th>Line total<span class="po-col-note">incl. VAT</span></th>
                                    @endif
                                    @if ($canReceive)
                                        <th class="po-receive-cell">Receive<span class="po-col-note">now</span></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($purchaseOrder->items as $item)
                                    <tr>
                                        <td class="admin-table-media">
                                            {{-- Le produit peut avoir été supprimé : la tuile garde
                                                 sa place pour que la colonne reste alignée. Une
                                                 déclinaison qui a sa propre photo montre la sienne :
                                                 c'est elle qui a été commandée. --}}
                                            @if ($item->product)
                                                <a href="{{ route('admin.products.edit', $item->product) }}">
                                                    <img
                                                        class="admin-product-thumb"
                                                        src="{{ filled($item->variant?->image) ? $item->variant->thumbnailUrl() : $item->product->thumbnailUrl() }}"
                                                        alt="{{ $item->name }}"
                                                        loading="lazy"
                                                    >
                                                </a>
                                            @else
                                                <span class="admin-stock-media is-empty" aria-hidden="true"></span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($item->product)
                                                <a href="{{ route('admin.products.edit', $item->product) }}" class="admin-table-strong po-line-name" title="{{ $item->name }}">{{ $item->name }}</a>
                                            @else
                                                <span class="admin-table-strong po-line-name" title="{{ $item->name }}">{{ $item->name }}</span>
                                                <span class="po-line-note">product deleted</span>
                                            @endif
                                            @if (filled($item->sku))
                                                <span class="admin-table-sub">{{ $item->sku }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $item->supplier_reference ?: '—' }}</td>
                                        <td>{{ $item->quantity_ordered }}</td>
                                        <td>
                                            <span class="po-received-ratio {{ $item->isFullyReceived() ? 'is-complete' : ($item->quantity_received > 0 ? 'is-partial' : '') }}">
                                                {{ $item->quantity_received }}
                                            </span>
                                        </td>
                                        <td>{{ $item->quantityRemaining() }}</td>
                                        <td>{{ format_euros($item->unit_cost_cents) }}</td>
                                        <td>{{ format_euros($item->lineTotalCents()) }}</td>
                                        @if ($purchaseOrder->hasVat())
                                            <td class="po-vat-cell">{{ format_euros($purchaseOrder->withVatCents($item->unit_cost_cents)) }}</td>
                                            <td class="po-vat-cell">{{ format_euros($purchaseOrder->lineTotalInclVatCents($item)) }}</td>
                                        @endif
                                        @if ($canReceive)
                                            <td class="po-receive-cell">
                                                @if ($item->isFullyReceived())
                                                    <span class="po-receive-done" aria-hidden="true">✓</span>
                                                    <span class="sr-only">Fully received</span>
                                                @else
                                                    <input
                                                        type="number"
                                                        id="receive-line-{{ $item->id }}"
                                                        name="lines[{{ $item->id }}]"
                                                        class="form-control po-receive-input"
                                                        aria-label="Receive now — {{ $item->name }}"
                                                        {{-- À zéro par défaut : la réception fait monter le stock, donc
                                                             elle se saisit ligne par ligne plutôt qu'elle ne se confirme. --}}
                                                        value="0"
                                                        min="0"
                                                        max="{{ $item->quantityRemaining() }}"
                                                    >
                                                    @error('lines.'.$item->id) <span class="form-error">{{ $message }}</span> @enderror
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($canReceive)
                            <div class="po-receive-actions">
                                <p class="po-receive-hint">Stock rises as goods arrive. Leave a line at 0 to skip it.</p>
                                <button type="submit" class="btn btn-primary">Receive</button>
                            </div>
                        </form>
                    @endif
                </section>

                <section class="order-panel" id="order-timeline">
                    <h3 class="order-panel-title">History</h3>
                    <ol class="order-timeline">
                        @foreach ($purchaseOrder->statusHistories as $entry)
                            <li class="order-timeline-item is-{{ $entry->status }}{{ $loop->first ? ' is-current' : '' }}">
                                <span class="order-timeline-marker" aria-hidden="true"></span>
                                <div class="order-timeline-body">
                                    <span class="order-timeline-status">{{ ucfirst(str_replace('_', ' ', $entry->status)) }}</span>
                                    @if ($entry->note)
                                        <span class="po-timeline-note">{{ $entry->note }}</span>
                                    @endif
                                    <time class="order-timeline-date" datetime="{{ $entry->created_at->toIso8601String() }}">
                                        {{ $entry->created_at->format('d M Y · H:i') }}@if ($entry->user) · {{ $entry->user->name }}@endif
                                    </time>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </section>
            </div>

            <aside class="order-facts">
                <section class="order-fact">
                    <h3 class="order-fact-title">Totals</h3>
                    <dl class="order-totals">
                        <div><dt>Subtotal</dt><dd>{{ format_euros($purchaseOrder->subtotalCents()) }}</dd></div>
                        <div><dt>Shipping</dt><dd>{{ format_euros($purchaseOrder->shipping_cents) }}</dd></div>
                        @if ($purchaseOrder->additional_costs_cents > 0)
                            <div><dt>Additional costs</dt><dd>{{ format_euros($purchaseOrder->additional_costs_cents) }}</dd></div>
                        @endif
                        @if ($purchaseOrder->discount_cents > 0)
                            <div><dt>Discount</dt><dd>−{{ format_euros($purchaseOrder->discount_cents) }}</dd></div>
                        @endif
                        <div><dt>Total excl. VAT</dt><dd>{{ format_euros($purchaseOrder->totalCents()) }}</dd></div>
                        @if ($purchaseOrder->hasVat())
                            <div><dt>VAT {{ rtrim(rtrim(number_format($purchaseOrder->vatRatePercent(), 1), '0'), '.') }}%</dt><dd>{{ format_euros($purchaseOrder->vatAmountCents()) }}</dd></div>
                            <div class="po-total-paid"><dt>Total incl. VAT</dt><dd>{{ format_euros($purchaseOrder->totalInclVatCents()) }}</dd></div>
                        @endif
                        <div><dt>Received value</dt><dd>{{ format_euros($purchaseOrder->receivedValueCents()) }}</dd></div>
                    </dl>
                </section>
                <section class="order-fact">
                    <h3 class="order-fact-title">Supplier</h3>
                    <p>{{ $purchaseOrder->supplier_name }}</p>
                    @if ($purchaseOrder->supplier?->website)
                        <p><a href="{{ $purchaseOrder->supplier->website }}" class="admin-link" target="_blank" rel="noopener">Website</a></p>
                    @endif
                    @if ($purchaseOrder->reference)
                        <p class="po-line-note">Their reference: {{ $purchaseOrder->reference }}</p>
                    @endif
                </section>
                @if ($purchaseOrder->notes)
                    <section class="order-fact">
                        <h3 class="order-fact-title">Notes</h3>
                        <p>{{ $purchaseOrder->notes }}</p>
                    </section>
                @endif
            </aside>
        </div>

        @if ($purchaseOrder->isDraft())
            <dialog id="po-send-modal" class="modal" aria-labelledby="po-send-title">
                <form method="POST" action="{{ route('admin.purchase-orders.send', $purchaseOrder) }}">
                    @csrf
                    @method('PATCH')
                    <p class="modal-kicker">{{ $purchaseOrder->number }}</p>
                    <h3 class="modal-title" id="po-send-title">Mark as sent?</h3>
                    <p class="modal-body">
                        The lines lock and receiving opens. Nothing is emailed —
                        this records that the order went to {{ $purchaseOrder->supplier_name }}.
                    </p>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-primary">Mark as sent</button>
                    </div>
                </form>
            </dialog>

            @if (auth()->user()->isOwner())
                <dialog id="po-delete-modal" class="modal" aria-labelledby="po-delete-title">
                    <form method="POST" action="{{ route('admin.purchase-orders.destroy', $purchaseOrder) }}">
                        @csrf
                        @method('DELETE')
                        <p class="modal-kicker">{{ $purchaseOrder->number }}</p>
                        <h3 class="modal-title" id="po-delete-title">Delete this draft?</h3>
                        <p class="modal-body">
                            Nothing was sent or received, so there is no record to
                            keep — but this cannot be undone.
                        </p>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                            <button type="submit" class="btn btn-danger">Delete draft</button>
                        </div>
                    </form>
                </dialog>
            @endif
        @endif

        @if ($purchaseOrder->canBeCancelled() && auth()->user()->isOwner())
            <dialog id="po-cancel-modal" class="modal" aria-labelledby="po-cancel-title">
                <form method="POST" action="{{ route('admin.purchase-orders.cancel', $purchaseOrder) }}">
                    @csrf
                    @method('PATCH')
                    <p class="modal-kicker">{{ $purchaseOrder->number }}</p>
                    <h3 class="modal-title" id="po-cancel-title">Cancel this order?</h3>
                    <p class="modal-body">
                        The remaining quantities are closed out. Stock already
                        received stays — those goods arrived.
                    </p>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" data-modal-close>Keep it open</button>
                        <button type="submit" class="btn btn-danger">Cancel order</button>
                    </div>
                </form>
            </dialog>
        @endif
    </div>
@endsection
