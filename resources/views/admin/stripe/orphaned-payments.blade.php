@extends('layouts.admin')

@section('title', 'Stripe payments')

@section('content')
    <div class="admin-list-page admin-stripe-payments">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker"><a href="{{ route('admin.settings.index') }}">Settings</a></p>
                    <h2 class="admin-list-title">Stripe payments</h2>
                    <p class="admin-list-lede">
                        Every Stripe Checkout Session from the last 30 days — successful, failed, or still pending —
                        with the order it produced, if any. A paid session with no order means the customer was
                        charged but the order was never created.
                    </p>
                </div>
            </div>
        </header>

        <div class="admin-stat-grid admin-stat-grid--primary">
            <a href="{{ route('admin.stripe.orphaned-payments.index') }}" class="admin-stat-card">
                <span class="admin-stat-label">Revenue</span>
                <span class="admin-stat-value">{{ format_euros($kpis['revenue_cents']) }}</span>
                <span class="admin-stat-value--sm">{{ number_format($kpis['paid_count']) }} paid {{ \Illuminate\Support\Str::plural('session', $kpis['paid_count']) }}</span>
            </a>
            <a href="{{ route('admin.stripe.orphaned-payments.index') }}" class="admin-stat-card">
                <span class="admin-stat-label">Success rate</span>
                <span class="admin-stat-value">{{ $kpis['success_rate'] !== null ? $kpis['success_rate'].'%' : '—' }}</span>
                <span class="admin-stat-value--sm">Of {{ number_format($totalCount) }} sessions</span>
            </a>
            <a href="{{ route('admin.stripe.orphaned-payments.index', ['tab' => 'failed']) }}" class="admin-stat-card">
                <span class="admin-stat-label">Failed</span>
                <span class="admin-stat-value">{{ number_format($kpis['failed_count']) }}</span>
                <span class="admin-stat-value--sm">Expired without payment</span>
            </a>
            <a href="{{ route('admin.stripe.orphaned-payments.index', ['tab' => 'pending']) }}" class="admin-stat-card">
                <span class="admin-stat-label">Pending</span>
                <span class="admin-stat-value">{{ number_format($kpis['pending_count']) }}</span>
                <span class="admin-stat-value--sm">Still open</span>
            </a>
            <a href="{{ route('admin.stripe.orphaned-payments.index', ['tab' => 'orphaned']) }}" class="admin-stat-card {{ $kpis['orphaned_count'] > 0 ? 'admin-stat-card--warning' : '' }}">
                <span class="admin-stat-label">Orphaned</span>
                <span class="admin-stat-value">{{ number_format($kpis['orphaned_count']) }}</span>
                <span class="admin-stat-value--sm">Paid with no order</span>
            </a>
        </div>

        <nav class="admin-tabs" aria-label="Payment status tabs">
            <a href="{{ route('admin.stripe.orphaned-payments.index') }}" class="{{ $tab === 'all' ? 'active' : '' }}">
                All <span class="admin-tab-count">{{ number_format($totalCount) }}</span>
            </a>
            <a href="{{ route('admin.stripe.orphaned-payments.index', ['tab' => 'paid']) }}" class="{{ $tab === 'paid' ? 'active' : '' }}">
                Paid <span class="admin-tab-count">{{ number_format($kpis['paid_count']) }}</span>
            </a>
            <a href="{{ route('admin.stripe.orphaned-payments.index', ['tab' => 'pending']) }}" class="{{ $tab === 'pending' ? 'active' : '' }}">
                Pending <span class="admin-tab-count">{{ number_format($kpis['pending_count']) }}</span>
            </a>
            <a href="{{ route('admin.stripe.orphaned-payments.index', ['tab' => 'failed']) }}" class="{{ $tab === 'failed' ? 'active' : '' }}">
                Failed <span class="admin-tab-count">{{ number_format($kpis['failed_count']) }}</span>
            </a>
            <a href="{{ route('admin.stripe.orphaned-payments.index', ['tab' => 'orphaned']) }}" class="{{ $tab === 'orphaned' ? 'active' : '' }}">
                Orphaned <span class="admin-tab-count">{{ number_format($kpis['orphaned_count']) }}</span>
            </a>
        </nav>

        @if ($payments->isEmpty())
            <div class="empty-state">
                <p>No Stripe payments in this view.</p>
            </div>
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Email</th>
                            <th>Amount</th>
                            <th>Stripe fee</th>
                            <th>Status</th>
                            <th>Payment intent</th>
                            <th>Stripe customer</th>
                            <th>Order</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $payment)
                            <tr>
                                <td>{{ $payment['created_at']->format('d M Y · H:i') }}</td>
                                <td>{{ $payment['email'] }}</td>
                                <td>{{ $payment['amount'] }}</td>
                                <td>
                                    @if ($payment['fee'])
                                        <span class="stripe-fee-chip">− {{ $payment['fee'] }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    <span class="stripe-status-chip stripe-status-chip--{{ $payment['status'] }}">{{ ucfirst($payment['status']) }}</span>
                                </td>
                                <td>
                                    @if ($payment['payment_intent_url'])
                                        <a href="{{ $payment['payment_intent_url'] }}" target="_blank" rel="noopener noreferrer" class="stripe-meta-chip">
                                            {{ $payment['payment_intent'] }}
                                        </a>
                                    @elseif ($payment['payment_intent'])
                                        <span class="stripe-meta-chip">{{ $payment['payment_intent'] }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if ($payment['customer_url'])
                                        <a href="{{ $payment['customer_url'] }}" target="_blank" rel="noopener noreferrer" class="stripe-meta-chip">
                                            {{ $payment['customer'] }}
                                        </a>
                                    @elseif ($payment['customer'])
                                        <span class="stripe-meta-chip">{{ $payment['customer'] }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if ($payment['order_number'])
                                        <a href="{{ route('admin.orders.show', $payment['order_number']) }}" class="admin-table-strong">
                                            {{ $payment['order_number'] }}
                                        </a>
                                        @if ($payment['order_archived'])
                                            <span class="badge badge-disabled badge-inline">Archived</span>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
