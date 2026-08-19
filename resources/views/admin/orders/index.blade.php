@extends('layouts.admin')

@section('title', 'Orders')

@section('content')
    @php
        $baseFilters = array_filter([
            'search' => $search ?: null,
            'status' => $status ?: null,
            'marketplace_id' => $marketplaceId ?: null,
            'date_from' => $dateFrom ?: null,
            'date_to' => $dateTo ?: null,
        ]);
        $hasFilters = $baseFilters !== [];
        $filterUrl = function (array $overrides = []) use ($tab, $search, $status, $marketplaceId, $dateFrom, $dateTo): string {
            return route('admin.orders.index', array_filter([
                'tab' => $tab !== 'orders' ? $tab : null,
                'search' => array_key_exists('search', $overrides) ? $overrides['search'] : ($search ?: null),
                'status' => array_key_exists('status', $overrides) ? $overrides['status'] : ($status ?: null),
                'marketplace_id' => array_key_exists('marketplace_id', $overrides) ? $overrides['marketplace_id'] : $marketplaceId,
                'date_from' => array_key_exists('date_from', $overrides) ? $overrides['date_from'] : ($dateFrom ?: null),
                'date_to' => array_key_exists('date_to', $overrides) ? $overrides['date_to'] : ($dateTo ?: null),
            ]));
        };
        $activeMarketplace = $marketplaceId
            ? $marketplaces->firstWhere('id', $marketplaceId)
            : null;
    @endphp

    <div class="admin-list-page admin-orders-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker">Sales</p>
                    <h2 class="admin-list-title">Orders</h2>
                    <p class="admin-list-lede">Every order placed in the shop.</p>
                </div>
                <div class="admin-list-hero-actions">
                    <a href="{{ route('admin.orders.export', request()->query()) }}" class="btn btn-secondary">Export CSV</a>
                    <a href="{{ route('admin.orders.create') }}" class="btn btn-primary">Create manual order</a>
                </div>
            </div>
            <div class="admin-list-meta">
                <span class="admin-list-chip">{{ number_format($kpis['order_count']) }} total orders</span>
                <span class="admin-list-chip">{{ number_format($kpis['to_prepare_count']) }} to prepare</span>
                <span class="admin-list-chip">{{ number_format($kpis['missing_tracking_count']) }} missing tracking</span>
                @if ($hasFilters)
                    <span class="admin-list-chip is-filtered">Filtered</span>
                @endif
            </div>
        </header>

        <div class="admin-stat-grid admin-stat-grid--primary">
            <div class="admin-stat-card">
                <span class="admin-stat-label">Total amount</span>
                <span class="admin-stat-value">{{ format_euros($kpis['amount_cents']) }}</span>
                <span class="admin-stat-value--sm">Order totals</span>
            </div>
            <div class="admin-stat-card">
                <span class="admin-stat-label">Own shipping cost</span>
                <span class="admin-stat-value">{{ format_euros($kpis['shipping_cost_cents']) }}</span>
                <span class="admin-stat-value--sm">Paid out of pocket</span>
            </div>
            <div class="admin-stat-card">
                <span class="admin-stat-label">Commission cost</span>
                <span class="admin-stat-value">{{ format_euros($kpis['commission_cost_cents']) }}</span>
                <span class="admin-stat-value--sm">Marketplace cut</span>
            </div>
            <div class="admin-stat-card">
                <span class="admin-stat-label">Payment fees</span>
                <span class="admin-stat-value">{{ format_euros($kpis['payment_fee_cents']) }}</span>
                <span class="admin-stat-value--sm">Card / PayPal processor</span>
            </div>
            <div class="admin-stat-card admin-stat-card--warning">
                <span class="admin-stat-label">Total costs</span>
                <span class="admin-stat-value">{{ format_euros($kpis['total_costs_cents']) }}</span>
                <span class="admin-stat-value--sm">Shipping + commission + fees</span>
            </div>
            <div class="admin-stat-card admin-stat-card--positive">
                <span class="admin-stat-label">Total perceived</span>
                <span class="admin-stat-value">{{ format_euros($kpis['perceived_total_cents']) }}</span>
                <span class="admin-stat-value--sm">After all costs</span>
            </div>
        </div>

        <nav class="admin-tabs" aria-label="Order tabs">
            <a href="{{ route('admin.orders.index', [...$baseFilters, 'tab' => 'orders']) }}" class="{{ $tab === 'orders' ? 'active' : '' }}">
                Orders <span class="admin-tab-count">{{ number_format($orderCount) }}</span>
            </a>
            <a href="{{ route('admin.orders.index', [...$baseFilters, 'tab' => 'draft']) }}" class="{{ $tab === 'draft' ? 'active' : '' }}">
                Drafts <span class="admin-tab-count">{{ number_format($draftCount) }}</span>
            </a>
            <a href="{{ route('admin.orders.index', [...$baseFilters, 'tab' => 'archived']) }}" class="{{ $tab === 'archived' ? 'active' : '' }}">
                Archived <span class="admin-tab-count">{{ number_format($archivedCount) }}</span>
            </a>
        </nav>

        <form method="GET" action="{{ route('admin.orders.index') }}" class="admin-filter-bar">
            <input type="hidden" name="tab" value="{{ $tab }}">

            <div class="admin-filter-search">
                <label class="admin-filter-label" for="order-search">Search</label>
                <input
                    id="order-search"
                    type="search"
                    name="search"
                    class="form-control admin-toolbar-search"
                    placeholder="Order number, customer or email…"
                    value="{{ $search }}"
                >
            </div>

            <div class="admin-filter-row">
                <div class="admin-filter-field">
                    <label class="admin-filter-label" for="order-status">Status</label>
                    <select id="order-status" name="status" class="form-control">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $statusOption)
                            <option value="{{ $statusOption }}" @selected($status === $statusOption)>{{ ucfirst($statusOption) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-filter-field">
                    <label class="admin-filter-label" for="order-marketplace">Marketplace</label>
                    <select id="order-marketplace" name="marketplace_id" class="form-control">
                        <option value="">All marketplaces</option>
                        @foreach ($marketplaces as $marketplace)
                            <option value="{{ $marketplace->id }}" @selected($marketplaceId === $marketplace->id)>{{ $marketplace->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-filter-field admin-filter-field--dates">
                    <span class="admin-filter-label" id="order-dates-label">Placed between</span>
                    <div class="admin-filter-date-range" role="group" aria-labelledby="order-dates-label">
                        <label class="sr-only" for="order-date-from">From</label>
                        <input
                            id="order-date-from"
                            type="date"
                            name="date_from"
                            class="form-control"
                            value="{{ $dateFrom }}"
                        >
                        <span class="admin-filter-date-sep" aria-hidden="true">to</span>
                        <label class="sr-only" for="order-date-to">To</label>
                        <input
                            id="order-date-to"
                            type="date"
                            name="date_to"
                            class="form-control"
                            value="{{ $dateTo }}"
                        >
                    </div>
                </div>
                <div class="admin-filter-actions">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    @if ($hasFilters)
                        <a href="{{ route('admin.orders.index', ['tab' => $tab !== 'orders' ? $tab : null]) }}" class="btn btn-secondary">Clear</a>
                    @endif
                </div>
            </div>
        </form>

        @if ($hasFilters)
            <div class="admin-filter-chips" aria-label="Active filters">
                @if ($search !== '')
                    <a href="{{ $filterUrl(['search' => null]) }}" class="admin-filter-chip">
                        Search · {{ $search }}
                        <span aria-hidden="true">×</span>
                    </a>
                @endif
                @if ($status !== '')
                    <a href="{{ $filterUrl(['status' => null]) }}" class="admin-filter-chip">
                        Status · {{ ucfirst($status) }}
                        <span aria-hidden="true">×</span>
                    </a>
                @endif
                @if ($activeMarketplace)
                    <a href="{{ $filterUrl(['marketplace_id' => null]) }}" class="admin-filter-chip">
                        Marketplace · {{ $activeMarketplace->name }}
                        <span aria-hidden="true">×</span>
                    </a>
                @endif
                @if ($dateFrom !== '')
                    <a href="{{ $filterUrl(['date_from' => null]) }}" class="admin-filter-chip">
                        From · {{ \Illuminate\Support\Carbon::parse($dateFrom)->format('d M Y') }}
                        <span aria-hidden="true">×</span>
                    </a>
                @endif
                @if ($dateTo !== '')
                    <a href="{{ $filterUrl(['date_to' => null]) }}" class="admin-filter-chip">
                        To · {{ \Illuminate\Support\Carbon::parse($dateTo)->format('d M Y') }}
                        <span aria-hidden="true">×</span>
                    </a>
                @endif
            </div>
        @endif

        @php
            $tabLabel = match ($tab) {
                'draft' => 'drafts',
                'archived' => 'archived orders',
                default => 'orders',
            };
        @endphp

        @if ($orders->isEmpty())
            <p class="empty-state">
                @if ($hasFilters)
                    No {{ $tabLabel }} match these filters.
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
                            <th class="admin-table-num">Various costs</th>
                            <th class="admin-table-num">Total perceived</th>
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
                                        <span class="order-chip order-chip--channel">{{ $order->marketplace_name ?: 'Manuelle' }}</span>
                                    @else
                                        <span class="admin-table-sub">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="order-chip order-chip--{{ $order->status }}">
                                        {{ ucfirst($order->status) }}
                                    </span>
                                </td>
                                <td>
                                    @if ($order->hasTracking())
                                        <span class="order-chip order-chip--tracking-available">Available</span>
                                    @elseif ($order->hasBeenShipped())
                                        <span class="order-chip order-chip--tracking-missing">Missing</span>
                                    @else
                                        <span class="order-chip order-chip--tracking-na">N/A</span>
                                    @endif
                                </td>
                                <td class="admin-table-num">
                                    <span class="admin-order-total">{{ $order->formattedTotal() }}</span>
                                    @if ($order->marketplace_commission_cents || $order->shipping_paid_cents || $order->payment_fee_cents)
                                        <span class="admin-order-deductions">
                                            @if ($order->marketplace_commission_cents)
                                                <span class="admin-order-deduction" title="Commission">−{{ format_euros($order->marketplace_commission_cents) }} comm.</span>
                                            @endif
                                            @if ($order->shipping_paid_cents)
                                                <span class="admin-order-deduction" title="Shipping paid">−{{ format_euros($order->shipping_paid_cents) }} ship.</span>
                                            @endif
                                            @if ($order->payment_fee_cents)
                                                <span class="admin-order-deduction" title="{{ $order->payment_method?->label() }} fee">−{{ format_euros($order->payment_fee_cents) }} fee</span>
                                            @endif
                                        </span>
                                    @endif
                                </td>
                                <td class="admin-table-num">
                                    @if ($order->totalCostsCents() > 0)
                                        <span class="stripe-fee-chip">− {{ $order->formattedTotalCosts() }}</span>
                                    @else
                                        <span class="admin-table-sub">—</span>
                                    @endif
                                </td>
                                <td class="admin-table-num">
                                    <span class="admin-order-perceived">{{ $order->formattedPerceivedTotal() }}</span>
                                </td>
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
