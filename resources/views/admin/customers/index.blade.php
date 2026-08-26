@extends('layouts.admin')

@section('title', 'Customers')

@section('content')
    @php
        $baseFilters = array_filter([
            'search' => $search ?: null,
            'date_from' => $dateFrom ?: null,
            'date_to' => $dateTo ?: null,
        ]);
        $hasFilters = $baseFilters !== [];
        $filterUrl = function (array $overrides = []) use ($tab, $search, $dateFrom, $dateTo): string {
            return route('admin.customers.index', array_filter([
                'tab' => $tab !== 'all' ? $tab : null,
                'search' => array_key_exists('search', $overrides) ? $overrides['search'] : ($search ?: null),
                'date_from' => array_key_exists('date_from', $overrides) ? $overrides['date_from'] : ($dateFrom ?: null),
                'date_to' => array_key_exists('date_to', $overrides) ? $overrides['date_to'] : ($dateTo ?: null),
            ]));
        };
    @endphp

    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker">Accounts</p>
                    <h2 class="admin-list-title">Customers</h2>
                    <p class="admin-list-lede">People who have created a shop account.</p>
                </div>
                <div class="admin-list-hero-actions">
                    <a href="{{ route('admin.customers.export', request()->query()) }}" class="btn btn-secondary">Export CSV</a>
                    <a href="{{ route('admin.orders.create') }}" class="btn btn-primary">Create manual order</a>
                </div>
            </div>
            <div class="admin-list-meta">
                <span class="admin-list-chip">{{ number_format($customerCount) }} {{ \Illuminate\Support\Str::plural('customer', $customerCount) }}</span>
                <span class="admin-list-chip">{{ number_format($withOrdersCount) }} with orders</span>
                <span class="admin-list-chip">+{{ number_format($newCustomers30d) }} last 30 days</span>
                @if ($hasFilters)
                    <span class="admin-list-chip is-filtered">Filtered</span>
                @endif
            </div>
        </header>

        <nav class="admin-tabs" aria-label="Customer tabs">
            <a href="{{ route('admin.customers.index', [...$baseFilters, 'tab' => 'all']) }}" class="{{ $tab === 'all' ? 'active' : '' }}">
                All <span class="admin-tab-count">{{ number_format($customerCount) }}</span>
            </a>
            <a href="{{ route('admin.customers.index', [...$baseFilters, 'tab' => 'with-orders']) }}" class="{{ $tab === 'with-orders' ? 'active' : '' }}">
                With orders <span class="admin-tab-count">{{ number_format($withOrdersCount) }}</span>
            </a>
            <a href="{{ route('admin.customers.index', [...$baseFilters, 'tab' => 'no-orders']) }}" class="{{ $tab === 'no-orders' ? 'active' : '' }}">
                No orders <span class="admin-tab-count">{{ number_format($noOrdersCount) }}</span>
            </a>
        </nav>

        <form method="GET" action="{{ route('admin.customers.index') }}" class="admin-filter-bar">
            @if ($tab !== 'all')
                <input type="hidden" name="tab" value="{{ $tab }}">
            @endif

            <div class="admin-filter-row admin-filter-row--customers">
                <div class="admin-filter-field admin-filter-field--search">
                    <label class="admin-field-label" for="customer-search">Search</label>
                    <input
                        id="customer-search"
                        type="search"
                        name="search"
                        class="form-control admin-toolbar-search"
                        placeholder="Name or email…"
                        value="{{ $search }}"
                    >
                </div>
                <div class="admin-filter-field admin-filter-field--dates">
                    <span class="admin-field-label" id="customer-dates-label">Joined between</span>
                    <div class="admin-filter-date-range" role="group" aria-labelledby="customer-dates-label">
                        <label class="sr-only" for="customer-date-from">From</label>
                        <input
                            id="customer-date-from"
                            type="date"
                            name="date_from"
                            class="form-control"
                            value="{{ $dateFrom }}"
                        >
                        <span class="admin-filter-date-sep" aria-hidden="true">to</span>
                        <label class="sr-only" for="customer-date-to">To</label>
                        <input
                            id="customer-date-to"
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
                        <a href="{{ route('admin.customers.index', ['tab' => $tab !== 'all' ? $tab : null]) }}" class="btn btn-secondary">Clear</a>
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
                'with-orders' => 'customers with orders',
                'no-orders' => 'customers without orders',
                default => 'customers',
            };
        @endphp

        @if ($customers->isEmpty())
            <p class="empty-state">
                @if ($hasFilters)
                    No {{ $tabLabel }} match these filters.
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
                            <th></th>
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
                            <tr class="{{ $customer->admin_viewed_at === null ? 'admin-row--new' : '' }}">
                                <td>
                                    @if ($customer->admin_viewed_at === null)
                                        <span class="admin-row-dot" title="Not opened yet" aria-label="Not opened yet"></span>
                                    @endif
                                </td>
                                <td>
                                    <div class="admin-customer-cell">
                                        <span class="admin-customer-avatar" aria-hidden="true">{{ $customer->initials() }}</span>
                                        <span class="admin-customer-identity">
                                            <span class="admin-customer-name-row">
                                                <a href="{{ route('admin.customers.show', $customer) }}" class="admin-table-strong">
                                                    {{ $displayName }}
                                                </a>
                                                @if ($customer->admin_viewed_at === null)
                                                    <span class="admin-new-chip" title="You haven't opened this profile yet">New</span>
                                                @endif
                                            </span>
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
