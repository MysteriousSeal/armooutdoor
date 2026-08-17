@extends('layouts.admin')

@section('title', $term !== '' ? 'Search “'.$term.'”' : 'Search')

@section('content')
    @php
        $orderCount = $orders->count();
        $customerCount = $customers->count();
        $productCount = $products->count();
        $resultCount = $orderCount + $customerCount + $productCount;
        $hasResults = $term !== '' && $resultCount > 0;
    @endphp

    <div class="admin-list-page admin-search-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker">Admin</p>
                    <h2 class="admin-list-title">Search</h2>
                    <p class="admin-list-lede">
                        @if ($term !== '')
                            Results for “{{ $term }}”.
                        @else
                            Find an order, customer or product from one place.
                        @endif
                    </p>
                </div>
            </div>
            @if ($term !== '')
                <div class="admin-list-meta">
                    <span class="admin-list-chip">{{ number_format($resultCount) }} {{ \Illuminate\Support\Str::plural('result', $resultCount) }}</span>
                    <span class="admin-list-chip">{{ number_format($orderCount) }} {{ \Illuminate\Support\Str::plural('order', $orderCount) }}</span>
                    <span class="admin-list-chip">{{ number_format($customerCount) }} {{ \Illuminate\Support\Str::plural('customer', $customerCount) }}</span>
                    <span class="admin-list-chip">{{ number_format($productCount) }} {{ \Illuminate\Support\Str::plural('product', $productCount) }}</span>
                </div>
            @endif
        </header>

        <form method="GET" action="{{ route('admin.search') }}" class="admin-toolbar">
            <input
                type="search"
                name="q"
                class="form-control admin-toolbar-search"
                placeholder="Order number, customer, product name or SKU…"
                value="{{ $term }}"
                autofocus
            >
            <button type="submit" class="btn btn-secondary">Search</button>
            @if ($term !== '')
                <a href="{{ route('admin.search') }}" class="btn btn-secondary">Clear</a>
            @endif
        </form>

        @if ($term === '')
            <div class="admin-search-empty">
                <p class="admin-search-empty-title">Type something above to search.</p>
                <p class="admin-search-empty-copy">You can look up any of these:</p>
                <div class="admin-list-meta">
                    <span class="admin-list-chip">Order number</span>
                    <span class="admin-list-chip">Customer name or email</span>
                    <span class="admin-list-chip">Product name or SKU</span>
                </div>
            </div>
        @elseif (! $hasResults)
            <div class="admin-search-empty">
                <p class="admin-search-empty-title">No matches for “{{ $term }}”.</p>
                <p class="admin-search-empty-copy">Try another spelling, or search by order number, email or SKU.</p>
                <a href="{{ route('admin.search') }}" class="btn btn-secondary">Clear search</a>
            </div>
        @else
            @if (($orderCount > 0) + ($customerCount > 0) + ($productCount > 0) > 1)
                <nav class="admin-tabs" aria-label="Search result sections">
                    @if ($orderCount > 0)
                        <a href="#search-orders">Orders <span class="admin-tab-count">{{ number_format($orderCount) }}</span></a>
                    @endif
                    @if ($customerCount > 0)
                        <a href="#search-customers">Customers <span class="admin-tab-count">{{ number_format($customerCount) }}</span></a>
                    @endif
                    @if ($productCount > 0)
                        <a href="#search-products">Products <span class="admin-tab-count">{{ number_format($productCount) }}</span></a>
                    @endif
                </nav>
            @endif

            <div class="admin-search-sections">
                @if ($orderCount > 0)
                    <section class="admin-search-section" id="search-orders">
                        <div class="admin-search-section-head">
                            <h3 class="admin-search-section-title">Orders</h3>
                            <span class="admin-tab-count">{{ number_format($orderCount) }}{{ $orderCount === 10 ? '+' : '' }}</span>
                            <a href="{{ route('admin.orders.index', ['search' => $term]) }}" class="admin-search-section-link">View in orders</a>
                        </div>
                        <div class="admin-table-wrap">
                            <table class="admin-table admin-orders-table">
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Customer</th>
                                        <th>Status</th>
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
                                                <span class="admin-table-primary">{{ $order->user?->name !== '' ? ($order->user?->name ?? '—') : ($order->user?->email ?? '—') }}</span>
                                                @if ($order->user?->email && $order->user?->name !== '')
                                                    <span class="admin-table-sub">{{ $order->user->email }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge badge-{{ $order->status }}">{{ ucfirst($order->status) }}</span>
                                                <span class="admin-table-sub">
                                                    {{ $order->items_count }} {{ \Illuminate\Support\Str::plural('item', $order->items_count) }}
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
                    </section>
                @endif

                @if ($customerCount > 0)
                    <section class="admin-search-section" id="search-customers">
                        <div class="admin-search-section-head">
                            <h3 class="admin-search-section-title">Customers</h3>
                            <span class="admin-tab-count">{{ number_format($customerCount) }}{{ $customerCount === 10 ? '+' : '' }}</span>
                            <a href="{{ route('admin.customers.index', ['search' => $term]) }}" class="admin-search-section-link">View in customers</a>
                        </div>
                        <div class="admin-table-wrap">
                            <table class="admin-table admin-customers-table">
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Orders</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($customers as $customer)
                                        @php
                                            $displayName = $customer->name !== '' ? $customer->name : $customer->email;
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
                                                @if ($customer->external)
                                                    <span class="admin-table-sub">External</span>
                                                @endif
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
                    </section>
                @endif

                @if ($productCount > 0)
                    <section class="admin-search-section" id="search-products">
                        <div class="admin-search-section-head">
                            <h3 class="admin-search-section-title">Products</h3>
                            <span class="admin-tab-count">{{ number_format($productCount) }}{{ $productCount === 10 ? '+' : '' }}</span>
                            <a href="{{ route('admin.products.index', ['search' => $term]) }}" class="admin-search-section-link">View in products</a>
                        </div>
                        <div class="admin-table-wrap">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th></th>
                                        <th>Product</th>
                                        <th>Category</th>
                                        <th class="admin-table-num">Price</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($products as $product)
                                        <tr>
                                            <td>
                                                <a href="{{ route('admin.products.edit', $product) }}">
                                                    <img
                                                        class="admin-product-thumb"
                                                        src="{{ $product->imageUrl() }}"
                                                        alt=""
                                                        loading="lazy"
                                                    >
                                                </a>
                                            </td>
                                            <td>
                                                <a href="{{ route('admin.products.edit', $product) }}" class="admin-table-strong admin-table-truncate" title="{{ $product->name['fr'] ?? $product->localizedName() }}">
                                                    {{ $product->name['fr'] ?? $product->localizedName() }}
                                                </a>
                                                <span class="admin-table-sub">{{ filled($product->sku) ? $product->sku : 'No SKU' }}</span>
                                            </td>
                                            <td>{{ $product->category?->name['fr'] ?? '—' }}</td>
                                            <td class="admin-table-num">{{ $product->formattedPrice() }}</td>
                                            <td>
                                                <span class="badge {{ $product->is_active ? 'badge-active' : 'badge-disabled' }}">
                                                    {{ $product->is_active ? 'Active' : 'Disabled' }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="admin-table-actions">
                                                    <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-sm btn-primary">Edit</a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endif
            </div>
        @endif
    </div>
@endsection
