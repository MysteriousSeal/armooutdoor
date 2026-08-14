@extends('layouts.admin')

@section('title', 'Orders')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <p class="admin-list-kicker">Sales</p>
            <h2 class="admin-list-title">Orders</h2>
            <p class="admin-list-lede">Every order placed in the shop.</p>
            <div class="admin-list-meta">
                <span class="admin-list-chip">{{ number_format($orderCount) }} orders</span>
                @if ($search !== '')
                    <span class="admin-list-chip is-filtered">Filtered</span>
                @endif
            </div>
        </header>

        <form method="GET" action="{{ route('admin.orders.index') }}" class="admin-toolbar">
            <input
                type="search"
                name="search"
                class="form-control admin-toolbar-search"
                placeholder="Search order number, customer or email…"
                value="{{ $search }}"
            >
            <button type="submit" class="btn btn-secondary">Search</button>
            @if ($search !== '')
                <a href="{{ route('admin.orders.index') }}" class="btn btn-secondary">Clear</a>
            @endif
        </form>

        @if ($orders->isEmpty())
            <p class="empty-state">No orders found.</p>
        @else
            <p class="admin-result-count">
                Showing {{ $orders->firstItem() }}–{{ $orders->lastItem() }}
            </p>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Carrier</th>
                            <th>Payment</th>
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
                                </td>
                                <td>
                                    <span class="admin-table-primary">{{ $order->user?->name ?? '—' }}</span>
                                    @if ($order->user?->email)
                                        <span class="admin-table-sub">{{ $order->user->email }}</span>
                                    @endif
                                </td>
                                <td>{{ $order->created_at->format('d M Y · H:i') }}</td>
                                <td>{{ $order->items_count }}</td>
                                <td>{{ $order->carrierName() !== '' ? $order->carrierName() : '—' }}</td>
                                <td>{{ $order->payment_method?->label() ?? '—' }}</td>
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
