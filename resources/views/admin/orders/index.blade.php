@extends('layouts.admin')

@section('title', 'Orders')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker">Sales</p>
                    <h2 class="admin-list-title">Orders</h2>
                    <p class="admin-list-lede">Every order placed in the shop.</p>
                </div>
                <a href="{{ route('admin.orders.create') }}" class="btn btn-primary">Create manual order</a>
            </div>
            <div class="admin-list-meta">
                <span class="admin-list-chip">{{ number_format($toPrepareCount) }} to prepare</span>
                <span class="admin-list-chip">{{ number_format($missingTrackingCount) }} missing tracking</span>
                @if ($search !== '')
                    <span class="admin-list-chip is-filtered">Filtered</span>
                @endif
            </div>
        </header>

        <nav class="admin-tabs" aria-label="Order tabs">
            <a href="{{ route('admin.orders.index', array_filter(['tab' => 'orders', 'search' => $search ?: null])) }}" class="{{ $tab === 'orders' ? 'active' : '' }}">
                Orders <span class="admin-tab-count">{{ number_format($orderCount) }}</span>
            </a>
            <a href="{{ route('admin.orders.index', array_filter(['tab' => 'draft', 'search' => $search ?: null])) }}" class="{{ $tab === 'draft' ? 'active' : '' }}">
                Drafts <span class="admin-tab-count">{{ number_format($draftCount) }}</span>
            </a>
            <a href="{{ route('admin.orders.index', array_filter(['tab' => 'archived', 'search' => $search ?: null])) }}" class="{{ $tab === 'archived' ? 'active' : '' }}">
                Archived <span class="admin-tab-count">{{ number_format($archivedCount) }}</span>
            </a>
        </nav>

        <form method="GET" action="{{ route('admin.orders.index') }}" class="admin-toolbar">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input
                type="search"
                name="search"
                class="form-control admin-toolbar-search"
                placeholder="Search order number, customer or email…"
                value="{{ $search }}"
            >
            <button type="submit" class="btn btn-secondary">Search</button>
            @if ($search !== '')
                <a href="{{ route('admin.orders.index', array_filter(['tab' => $tab !== 'orders' ? $tab : null])) }}" class="btn btn-secondary">Clear</a>
            @endif
        </form>

        @php
            $tabLabel = match ($tab) {
                'draft' => 'drafts',
                'archived' => 'archived orders',
                default => 'orders',
            };
        @endphp

        @if ($orders->isEmpty())
            <p class="empty-state">
                @if ($search !== '')
                    No {{ $tabLabel }} match this search.
                @else
                    No {{ $tabLabel }} yet.
                @endif
            </p>
        @else
            <p class="admin-result-count">
                Showing {{ $orders->firstItem() }}–{{ $orders->lastItem() }}
            </p>

            <div class="admin-table-wrap">
                <table class="admin-table admin-orders-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Shipping</th>
                            <th>Channel</th>
                            <th>Status</th>
                            <th>Tracking</th>
                            <th class="admin-table-num">Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.orders.show', $order) }}" class="admin-table-strong">
                                        {{ $order->number }}
                                    </a>
                                    <span class="admin-table-sub">{{ $order->created_at->format('d M Y · H:i') }}</span>
                                </td>
                                <td>
                                    <span class="admin-table-primary">{{ $order->user?->name ?? '—' }}</span>
                                    @if ($order->user?->email)
                                        <span class="admin-table-sub">{{ $order->user->email }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="admin-table-primary">
                                        {{ $order->carrierName() !== '' ? $order->carrierName() : '—' }}
                                    </span>
                                    <span class="admin-table-sub">
                                        {{ $order->payment_method?->label() ?? '—' }}
                                        · {{ $order->items_count }} {{ \Illuminate\Support\Str::plural('item', $order->items_count) }}
                                    </span>
                                </td>
                                <td>
                                    @if ($order->is_manual)
                                        <span class="admin-list-chip">{{ $order->marketplace_name ?: 'Manuelle' }}</span>
                                    @else
                                        <span class="admin-table-sub">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-{{ $order->status }}">
                                        {{ ucfirst($order->status) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="tracking-flag {{ $order->hasTracking() ? 'is-available' : 'is-missing' }}">
                                        {{ $order->hasTracking() ? 'Available' : 'Missing' }}
                                    </span>
                                </td>
                                <td class="admin-table-num">{{ $order->formattedTotal() }}</td>
                                <td>
                                    <div class="admin-table-actions">
                                        @if ($order->invoiceIsAvailable())
                                            <a
                                                href="{{ route('admin.orders.invoice', $order) }}"
                                                class="admin-table-icon-btn"
                                                title="Download invoice"
                                                aria-label="Download invoice"
                                            >
                                                <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
                                                    <path d="M12 4v11m0 0-4-4m4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                                    <path d="M5 17v2a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-2" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </a>
                                        @else
                                            <span class="admin-table-icon-btn is-disabled" title="Invoice not available yet" aria-hidden="true">
                                                <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
                                                    <path d="M12 4v11m0 0-4-4m4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                                    <path d="M5 17v2a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-2" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </span>
                                        @endif
                                        <button
                                            type="button"
                                            class="admin-table-icon-btn"
                                            data-archive-toggle
                                            data-action="{{ route($order->isArchived() ? 'admin.orders.unarchive' : 'admin.orders.archive', $order) }}"
                                            data-order="{{ $order->number }}"
                                            data-archived="{{ $order->isArchived() ? '1' : '0' }}"
                                            title="{{ $order->isArchived() ? 'Unarchive order' : 'Archive order' }}"
                                            aria-label="{{ $order->isArchived() ? 'Unarchive order' : 'Archive order' }}"
                                        >
                                            @if ($order->isArchived())
                                                <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
                                                    <rect x="4" y="4" width="16" height="4" rx="1" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/>
                                                    <path d="M5 8v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/>
                                                    <path d="M14 12.5 12 10.5 10 12.5m2-2v6" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            @else
                                                <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
                                                    <rect x="4" y="4" width="16" height="4" rx="1" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/>
                                                    <path d="M5 8v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/>
                                                    <path d="M10 14.5 12 16.5l2-2m-2 2v-6" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            @endif
                                        </button>
                                        <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-sm btn-primary">View</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('admin.partials.pager', ['paginator' => $orders])
        @endif

        <dialog id="archive-confirm-modal" class="modal" aria-labelledby="archive-confirm-title">
            <form method="POST" id="archive-confirm-form">
                @csrf
                @method('PATCH')
                <h3 class="modal-title" id="archive-confirm-title">Are you sure?</h3>
                <p class="modal-body" id="archive-confirm-body"></p>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                    <button type="submit" class="btn btn-primary" id="archive-confirm-submit"></button>
                </div>
            </form>
        </dialog>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var modal = document.getElementById('archive-confirm-modal');
            var form = document.getElementById('archive-confirm-form');
            var body = document.getElementById('archive-confirm-body');
            var submitBtn = document.getElementById('archive-confirm-submit');

            document.querySelectorAll('[data-archive-toggle]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var archived = btn.getAttribute('data-archived') === '1';
                    var number = btn.getAttribute('data-order');

                    form.action = btn.getAttribute('data-action');
                    body.textContent = archived
                        ? 'Are you sure you want to unarchive order ' + number + '?'
                        : 'Are you sure you want to archive order ' + number + '?';
                    submitBtn.textContent = archived ? 'Unarchive order' : 'Archive order';

                    modal.showModal();
                });
            });
        })();
    </script>
@endpush
