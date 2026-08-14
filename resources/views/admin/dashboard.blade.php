@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <p class="admin-list-kicker">Overview</p>
            <h2 class="admin-list-title">Dashboard</h2>
            <p class="admin-list-lede">Sales, stock and customers at a glance.</p>
        </header>

        <div class="admin-stat-grid" style="margin-bottom: 1.5rem">
            <a href="{{ route('admin.orders.index') }}" class="admin-stat-card">
                <span class="admin-stat-label">Revenue (net)</span>
                <span class="admin-stat-value">{{ format_euros($netRevenueCents) }}</span>
                <span class="admin-stat-value--sm">{{ number_format($nonRefundedCount) }} paid {{ \Illuminate\Support\Str::plural('order', $nonRefundedCount) }}</span>
            </a>
            <a href="{{ route('admin.orders.index') }}" class="admin-stat-card">
                <span class="admin-stat-label">Orders</span>
                <span class="admin-stat-value">{{ number_format($orderCount) }}</span>
                <span class="admin-stat-value--sm">Avg. {{ format_euros($averageOrderCents) }} / order</span>
            </a>
            <a href="{{ route('admin.orders.index') }}" class="admin-stat-card">
                <span class="admin-stat-label">Refunded</span>
                <span class="admin-stat-value">{{ format_euros($refundedCents) }}</span>
                <span class="admin-stat-value--sm">{{ number_format($statusCounts['refunded'] ?? 0) }} {{ \Illuminate\Support\Str::plural('order', $statusCounts['refunded'] ?? 0) }}</span>
            </a>
            <a href="{{ route('admin.orders.index') }}" class="admin-stat-card">
                <span class="admin-stat-label">To prepare</span>
                <span class="admin-stat-value">{{ number_format($toPrepareCount) }}</span>
            </a>
            <a href="{{ route('admin.orders.index') }}" class="admin-stat-card">
                <span class="admin-stat-label">Missing tracking</span>
                <span class="admin-stat-value">{{ number_format($missingTrackingCount) }}</span>
            </a>
            <a href="{{ route('admin.customers.index') }}" class="admin-stat-card">
                <span class="admin-stat-label">Customers</span>
                <span class="admin-stat-value">{{ number_format($customerCount) }}</span>
                <span class="admin-stat-value--sm">+{{ number_format($newCustomers30d) }} last 30 days</span>
            </a>
            <a href="{{ route('admin.customers.index') }}" class="admin-stat-card">
                <span class="admin-stat-label">External customers</span>
                <span class="admin-stat-value">{{ number_format($externalCustomerCount) }}</span>
                <span class="admin-stat-value--sm">From manual orders</span>
            </a>
            <a href="{{ route('admin.products.index') }}" class="admin-stat-card">
                <span class="admin-stat-label">Products</span>
                <span class="admin-stat-value">{{ number_format($productCount) }}</span>
                <span class="admin-stat-value--sm">{{ number_format($activeProductCount) }} active</span>
            </a>
            <a href="{{ route('admin.products.index') }}" class="admin-stat-card">
                <span class="admin-stat-label">Low / out of stock</span>
                <span class="admin-stat-value">{{ number_format($lowStockCount) }} / {{ number_format($outOfStockCount) }}</span>
            </a>
        </div>

        <div class="admin-order-layout">
            <div class="order-main">
                <section class="order-panel">
                    <h3 class="order-panel-title">Last 7 days</h3>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Day</th>
                                    <th class="admin-table-num">Orders</th>
                                    <th class="admin-table-num">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($last7Days as $day)
                                    <tr>
                                        <td>{{ $day['date']->format('D d M') }}</td>
                                        <td class="admin-table-num">{{ number_format($day['orders']) }}</td>
                                        <td class="admin-table-num">{{ format_euros($day['revenue_cents']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="order-panel">
                    <h3 class="order-panel-title">Top products</h3>
                    @if ($topProducts->isEmpty())
                        <p class="empty-state">No sales yet.</p>
                    @else
                        <div class="admin-table-wrap">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th class="admin-table-num">Qty sold</th>
                                        <th class="admin-table-num">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($topProducts as $row)
                                        <tr>
                                            <td>
                                                @if ($row['product'])
                                                    <a href="{{ route('admin.products.edit', $row['product']) }}" class="admin-table-strong">{{ $row['name'] }}</a>
                                                @else
                                                    <span class="admin-table-primary">{{ $row['name'] }}</span>
                                                @endif
                                            </td>
                                            <td class="admin-table-num">{{ number_format($row['quantity']) }}</td>
                                            <td class="admin-table-num">{{ format_euros($row['revenue_cents']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>

                <section class="order-panel">
                    <h3 class="order-panel-title">Recent orders</h3>
                    @if ($recentOrders->isEmpty())
                        <p class="empty-state">No orders yet.</p>
                    @else
                        <div class="admin-table-wrap">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Customer</th>
                                        <th>Status</th>
                                        <th class="admin-table-num">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentOrders as $order)
                                        <tr>
                                            <td>
                                                <a href="{{ route('admin.orders.show', $order) }}" class="admin-table-strong">{{ $order->number }}</a>
                                                <span class="admin-table-sub">{{ $order->created_at->format('d M Y · H:i') }}</span>
                                            </td>
                                            <td>{{ $order->user?->name ?? '—' }}</td>
                                            <td><span class="badge badge-{{ $order->status }}">{{ ucfirst($order->status) }}</span></td>
                                            <td class="admin-table-num">{{ $order->formattedTotal() }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            </div>

            <aside class="order-facts">
                <section class="order-fact">
                    <h3 class="order-fact-title">Orders by status</h3>
                    @foreach (['placed', 'preparing', 'shipped', 'refunded'] as $status)
                        <p style="display: flex; justify-content: space-between; margin: 0 0 0.4rem">
                            <span class="badge badge-{{ $status }}">{{ ucfirst($status) }}</span>
                            <span>{{ number_format($statusCounts[$status] ?? 0) }}</span>
                        </p>
                    @endforeach
                </section>

                <section class="order-fact">
                    <h3 class="order-fact-title">Sales by marketplace</h3>
                    @if ($marketplaceStats->isEmpty())
                        <p>No paid orders yet.</p>
                    @else
                        @foreach ($marketplaceStats as $row)
                            <p style="display: flex; justify-content: space-between; margin: 0 0 0.5rem">
                                <span>{{ $row->label }} <span class="admin-table-sub">({{ number_format($row->orders) }})</span></span>
                                <span>{{ format_euros($row->revenue_cents) }}</span>
                            </p>
                        @endforeach
                    @endif
                </section>

                <section class="order-fact">
                    <h3 class="order-fact-title">Low stock</h3>
                    @if ($lowStockProducts->isEmpty())
                        <p>Nothing running low.</p>
                    @else
                        @foreach ($lowStockProducts as $product)
                            <p style="display: flex; justify-content: space-between; margin: 0 0 0.5rem">
                                <a href="{{ route('admin.products.edit', $product) }}">{{ $product->localizedName() }}</a>
                                <span>{{ $product->quantity }} left</span>
                            </p>
                        @endforeach
                    @endif
                </section>
            </aside>
        </div>
    </div>
@endsection
