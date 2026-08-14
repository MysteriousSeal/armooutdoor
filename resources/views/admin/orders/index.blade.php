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

        @if ($orders->isEmpty())
            <p class="empty-state">
                @if ($search !== '')
                    No {{ $tab === 'draft' ? 'drafts' : 'orders' }} match this search.
                @else
                    {{ $tab === 'draft' ? 'No drafts yet.' : 'No orders yet.' }}
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
    </div>
@endsection
