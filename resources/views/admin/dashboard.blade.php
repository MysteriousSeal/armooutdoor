@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    @php
        $maxWeekRevenue = max(1, (int) $last7Days->max('revenue_cents'));
    @endphp

    <div class="admin-list-page admin-dashboard">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker">Overview</p>
                    <h2 class="admin-list-title">Dashboard</h2>
                    <p class="admin-list-lede">Sales, stock and customers at a glance.</p>
                </div>
                <a href="{{ route('admin.orders.create') }}" class="btn btn-primary">Create manual order</a>
            </div>
        </header>

        <div class="admin-stat-grid admin-stat-grid--primary">
            <a href="{{ route('admin.orders.index') }}" class="admin-stat-card">
                <span class="admin-stat-label">Revenue</span>
                <span class="admin-stat-value">{{ format_euros($netRevenueCents) }}</span>
                <span class="admin-stat-value--sm">{{ number_format($nonRefundedCount) }} paid {{ \Illuminate\Support\Str::plural('order', $nonRefundedCount) }}</span>
            </a>
            <a href="{{ route('admin.orders.index') }}" class="admin-stat-card">
                <span class="admin-stat-label">Orders</span>
                <span class="admin-stat-value">{{ number_format($orderCount) }}</span>
                <span class="admin-stat-value--sm">Avg. {{ format_euros($averageOrderCents) }}</span>
            </a>
            <a href="{{ route('admin.orders.index') }}" class="admin-stat-card">
                <span class="admin-stat-label">To prepare</span>
                <span class="admin-stat-value">{{ number_format($toPrepareCount) }}</span>
                <span class="admin-stat-value--sm">Placed and preparing</span>
            </a>
            <a href="{{ route('admin.orders.index') }}" class="admin-stat-card">
                <span class="admin-stat-label">Missing tracking</span>
                <span class="admin-stat-value">{{ number_format($missingTrackingCount) }}</span>
                <span class="admin-stat-value--sm">Shipped without a number</span>
            </a>
        </div>

        <div class="admin-stat-grid admin-stat-grid--secondary">
            <a href="{{ route('admin.orders.index') }}" class="admin-stat-card admin-stat-card--compact">
                <span class="admin-stat-label">Refunded</span>
                <span class="admin-stat-value">{{ format_euros($refundedCents) }}</span>
                <span class="admin-stat-value--sm">{{ number_format($statusCounts['refunded'] ?? 0) }} {{ \Illuminate\Support\Str::plural('order', $statusCounts['refunded'] ?? 0) }}</span>
            </a>
            <a href="{{ route('admin.customers.index') }}" class="admin-stat-card admin-stat-card--compact">
                <span class="admin-stat-label">Customers</span>
                <span class="admin-stat-value">{{ number_format($customerCount) }}</span>
                <span class="admin-stat-value--sm">+{{ number_format($newCustomers30d) }} last 30 days</span>
            </a>
            <a href="{{ route('admin.customers.index') }}" class="admin-stat-card admin-stat-card--compact">
                <span class="admin-stat-label">External</span>
                <span class="admin-stat-value">{{ number_format($externalCustomerCount) }}</span>
                <span class="admin-stat-value--sm">Manual orders</span>
            </a>
            <a href="{{ route('admin.products.index') }}" class="admin-stat-card admin-stat-card--compact">
                <span class="admin-stat-label">Products</span>
                <span class="admin-stat-value">{{ number_format($productCount) }}</span>
                <span class="admin-stat-value--sm">{{ number_format($activeProductCount) }} active</span>
            </a>
            <a href="{{ route('admin.products.index') }}" class="admin-stat-card admin-stat-card--compact">
                <span class="admin-stat-label">Stock alerts</span>
                <span class="admin-stat-value">{{ number_format($lowStockCount) }} / {{ number_format($outOfStockCount) }}</span>
                <span class="admin-stat-value--sm">Low / out</span>
            </a>
        </div>

        <div class="admin-order-layout">
            <div class="order-main">
                <section class="order-panel">
                    <h3 class="order-panel-title">Last 7 days</h3>
                    <div class="admin-week" role="img" aria-label="Revenue over the last 7 days">
                        @foreach ($last7Days as $day)
                            @php($pct = max(4, (int) round(100 * $day['revenue_cents'] / $maxWeekRevenue)))
                            <div class="admin-week-col">
                                <span class="admin-week-value">{{ $day['revenue_cents'] > 0 ? format_euros($day['revenue_cents']) : '—' }}</span>
                                <span class="admin-week-track">
                                    <span class="admin-week-bar" style="height: {{ $pct }}%"></span>
                                </span>
                                <span class="admin-week-label">{{ $day['date']->format('D') }}</span>
                                <span class="admin-week-sub">{{ number_format($day['orders']) }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="order-panel">
                    <h3 class="order-panel-title">Recent orders</h3>
                    @if ($recentOrders->isEmpty())
                        <p class="empty-state">No orders yet.</p>
                    @else
                        <ul class="admin-dash-list">
                            @foreach ($recentOrders as $order)
                                <li>
                                    <div class="admin-dash-list-main">
                                        <a href="{{ route('admin.orders.show', $order) }}" class="admin-table-strong">{{ $order->number }}</a>
                                        <span class="admin-table-sub">
                                            {{ $order->user?->name ?? '—' }}
                                            · {{ $order->created_at->format('d M Y · H:i') }}
                                        </span>
                                    </div>
                                    <span class="badge badge-{{ $order->status }}">{{ ucfirst($order->status) }}</span>
                                    <span class="admin-dash-list-value">{{ $order->formattedTotal() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                <section class="order-panel">
                    <h3 class="order-panel-title">Top products</h3>
                    @if ($topProducts->isEmpty())
                        <p class="empty-state">No sales yet.</p>
                    @else
                        <ul class="admin-dash-list">
                            @foreach ($topProducts as $row)
                                <li>
                                    <div class="admin-dash-list-main">
                                        @if ($row['product'])
                                            <a href="{{ route('admin.products.edit', $row['product']) }}" class="admin-table-strong">{{ $row['name'] }}</a>
                                        @else
                                            <span class="admin-table-primary">{{ $row['name'] }}</span>
                                        @endif
                                        <span class="admin-table-sub">{{ number_format($row['quantity']) }} sold</span>
                                    </div>
                                    <span class="admin-dash-list-value">{{ format_euros($row['revenue_cents']) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>

            <aside class="order-facts">
                <section class="order-fact">
                    <h3 class="order-fact-title">Orders by status</h3>
                    <ul class="admin-dash-list">
                        @foreach (['placed', 'preparing', 'shipped', 'refunded'] as $status)
                            <li>
                                <span class="badge badge-{{ $status }}">{{ ucfirst($status) }}</span>
                                <span class="admin-dash-list-value">{{ number_format($statusCounts[$status] ?? 0) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>

                <section class="order-fact">
                    <h3 class="order-fact-title">Sales by marketplace</h3>
                    @if ($marketplaceStats->isEmpty())
                        <p>No paid orders yet.</p>
                    @else
                        <ul class="admin-dash-list">
                            @foreach ($marketplaceStats as $row)
                                <li>
                                    <div class="admin-dash-list-main">
                                        <span class="admin-table-primary">{{ $row->label }}</span>
                                        <span class="admin-table-sub">{{ number_format($row->orders) }} {{ \Illuminate\Support\Str::plural('order', $row->orders) }}</span>
                                    </div>
                                    <span class="admin-dash-list-value">{{ format_euros($row->revenue_cents) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                <section class="order-fact">
                    <h3 class="order-fact-title">Low stock</h3>
                    @if ($lowStockProducts->isEmpty())
                        <p>Nothing running low.</p>
                    @else
                        <ul class="admin-dash-list">
                            @foreach ($lowStockProducts as $product)
                                <li>
                                    <a href="{{ route('admin.products.edit', $product) }}" class="admin-dash-list-main">
                                        <span class="admin-table-primary">{{ $product->localizedName() }}</span>
                                    </a>
                                    <span class="admin-dash-list-value">{{ $product->quantity }} left</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </aside>
        </div>
    </div>
@endsection
