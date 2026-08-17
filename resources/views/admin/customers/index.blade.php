@extends('layouts.admin')

@section('title', 'Customers')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker">Accounts</p>
                    <h2 class="admin-list-title">Customers</h2>
                    <p class="admin-list-lede">People who have created a shop account.</p>
                </div>
                <a href="{{ route('admin.orders.create') }}" class="btn btn-primary">Create manual order</a>
            </div>
            <div class="admin-list-meta">
                <span class="admin-list-chip">{{ number_format($customerCount) }} {{ \Illuminate\Support\Str::plural('customer', $customerCount) }}</span>
                <span class="admin-list-chip">{{ number_format($withOrdersCount) }} with orders</span>
                <span class="admin-list-chip">+{{ number_format($newCustomers30d) }} last 30 days</span>
                @if ($search !== '')
                    <span class="admin-list-chip is-filtered">Filtered</span>
                @endif
            </div>
        </header>

        <nav class="admin-tabs" aria-label="Customer tabs">
            <a href="{{ route('admin.customers.index', array_filter(['tab' => 'all', 'search' => $search ?: null])) }}" class="{{ $tab === 'all' ? 'active' : '' }}">
                All <span class="admin-tab-count">{{ number_format($customerCount) }}</span>
            </a>
            <a href="{{ route('admin.customers.index', array_filter(['tab' => 'with-orders', 'search' => $search ?: null])) }}" class="{{ $tab === 'with-orders' ? 'active' : '' }}">
                With orders <span class="admin-tab-count">{{ number_format($withOrdersCount) }}</span>
            </a>
            <a href="{{ route('admin.customers.index', array_filter(['tab' => 'no-orders', 'search' => $search ?: null])) }}" class="{{ $tab === 'no-orders' ? 'active' : '' }}">
                No orders <span class="admin-tab-count">{{ number_format($noOrdersCount) }}</span>
            </a>
        </nav>

        <form method="GET" action="{{ route('admin.customers.index') }}" class="admin-toolbar">
            @if ($tab !== 'all')
                <input type="hidden" name="tab" value="{{ $tab }}">
            @endif
            <input
                type="search"
                name="search"
                class="form-control admin-toolbar-search"
                placeholder="Search name or email…"
                value="{{ $search }}"
            >
            <button type="submit" class="btn btn-secondary">Search</button>
            @if ($search !== '')
                <a href="{{ route('admin.customers.index', array_filter(['tab' => $tab !== 'all' ? $tab : null])) }}" class="btn btn-secondary">Clear</a>
            @endif
        </form>

        @php
            $tabLabel = match ($tab) {
                'with-orders' => 'customers with orders',
                'no-orders' => 'customers without orders',
                default => 'customers',
            };
        @endphp

        @if ($customers->isEmpty())
            <p class="empty-state">
                @if ($search !== '')
                    No {{ $tabLabel }} match this search.
                @else
                    No {{ $tabLabel }} yet.
                @endif
            </p>
        @else
            <p class="admin-result-count">
                Showing {{ $customers->firstItem() }}–{{ $customers->lastItem() }}
            </p>

            <div class="admin-table-wrap">
                <table class="admin-table admin-customers-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Orders</th>
                            <th class="admin-table-num">Spent</th>
                            <th>Addresses</th>
                            <th>Joined</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($customers as $customer)
                            @php
                                $displayName = $customer->name !== '' ? $customer->name : $customer->email;
                                $lastOrderAt = $customer->last_order_at
                                    ? \Illuminate\Support\Carbon::parse($customer->last_order_at)
                                    : null;
                            @endphp
                            <tr>
                                <td>
                                    <div class="admin-customer-cell">
                                        <span class="admin-customer-avatar" aria-hidden="true">{{ $customer->initials() }}</span>
                                        <span class="admin-customer-identity">
                                            <a href="{{ route('admin.customers.show', $customer) }}" class="admin-table-strong">
                                                {{ $displayName }}
                                            </a>
                                            @if ($customer->name !== '')
                                                <span class="admin-table-sub">{{ $customer->email }}</span>
                                            @endif
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="admin-table-primary">{{ number_format($customer->orders_count) }}</span>
                                    <span class="admin-table-sub">
                                        @if ($lastOrderAt)
                                            Last {{ $lastOrderAt->format('d M Y') }}
                                        @else
                                            No orders yet
                                        @endif
                                    </span>
                                </td>
                                <td class="admin-table-num">
                                    @if ((int) $customer->spent_cents > 0)
                                        {{ format_euros((int) $customer->spent_cents) }}
                                    @else
                                        <span class="admin-table-sub">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($customer->addresses_count > 0)
                                        {{ number_format($customer->addresses_count) }}
                                    @else
                                        <span class="admin-table-sub">None</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="admin-table-primary">{{ $customer->created_at->format('d M Y') }}</span>
                                    <span class="admin-table-sub">{{ $customer->created_at->format('H:i') }}</span>
                                </td>
                                <td>
                                    <div class="admin-table-actions">
                                        <a href="{{ route('admin.customers.show', $customer) }}" class="btn btn-sm btn-primary">View</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('admin.partials.pager', ['paginator' => $customers])
        @endif
    </div>
@endsection
