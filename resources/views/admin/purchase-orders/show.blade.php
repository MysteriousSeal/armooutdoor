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
                        @if ($purchaseOrder->createdBy)
                            · drafted by {{ $purchaseOrder->createdBy->name }}
                        @endif
                    </p>
                </div>
                <div class="admin-order-actions">
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
                <section class="order-panel">
                    <h3 class="order-panel-title">Lines</h3>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>SKU</th>
                                    <th>Supplier ref</th>
                                    <th>Ordered</th>
                                    <th>Received</th>
                                    <th>Remaining</th>
                                    <th>Unit cost</th>
                                    <th>Line total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($purchaseOrder->items as $item)
                                    <tr>
                                        <td>
                                            @if ($item->product)
                                                <a href="{{ route('admin.products.edit', $item->product) }}" class="admin-link">{{ $item->name }}</a>
                                            @else
                                                {{ $item->name }}
                                                <span class="po-line-note">product deleted</span>
                                            @endif
                                        </td>
                                        <td>{{ $item->sku ?: '—' }}</td>
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
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                @if ($purchaseOrder->canReceive())
                    <section class="order-panel">
                        <h3 class="order-panel-title">Receive stock</h3>
                        <p class="admin-list-lede">Stock rises as goods arrive. Leave a line at 0 to skip it.</p>
                        <form method="POST" action="{{ route('admin.purchase-orders.receive', $purchaseOrder) }}" class="po-receive-form">
                            @csrf
                            @error('lines') <p class="form-error">{{ $message }}</p> @enderror
                            @foreach ($purchaseOrder->items as $item)
                                @continue($item->isFullyReceived())
                                <div class="po-receive-row">
                                    <label for="receive-line-{{ $item->id }}" class="po-receive-label">
                                        {{ $item->name }}
                                        <span class="po-line-note">{{ $item->quantityRemaining() }} outstanding</span>
                                    </label>
                                    <input
                                        type="number"
                                        id="receive-line-{{ $item->id }}"
                                        name="lines[{{ $item->id }}]"
                                        class="form-control po-receive-input"
                                        value="{{ $item->quantityRemaining() }}"
                                        min="0"
                                        max="{{ $item->quantityRemaining() }}"
                                    >
                                    @error('lines.'.$item->id) <p class="form-error">{{ $message }}</p> @enderror
                                </div>
                            @endforeach
                            <button type="submit" class="btn btn-primary">Receive</button>
                        </form>
                    </section>
                @endif

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
                    <h3 class="order-fact-title">Totals (excl. VAT)</h3>
                    <dl class="order-totals">
                        <div><dt>Subtotal</dt><dd>{{ format_euros($purchaseOrder->subtotalCents()) }}</dd></div>
                        <div><dt>Shipping</dt><dd>{{ format_euros($purchaseOrder->shipping_cents) }}</dd></div>
                        <div><dt>Total</dt><dd>{{ format_euros($purchaseOrder->totalCents()) }}</dd></div>
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
