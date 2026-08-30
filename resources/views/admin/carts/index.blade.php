@extends('layouts.admin')

@section('title', 'Carts')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <p class="admin-list-kicker">Sales</p>
            <h2 class="admin-list-title">Carts</h2>
            <p class="admin-list-lede">
                What logged-in shoppers currently have sitting in their cart. A cart empties itself the moment its
                order completes, so every row here is still in progress — guest carts live only in their own
                browser session and never reach this list.
            </p>
            <div class="admin-list-meta">
                <span class="admin-list-chip">{{ number_format($cartCount) }} active {{ \Illuminate\Support\Str::plural('cart', $cartCount) }}</span>
            </div>
        </header>

        <form method="GET" action="{{ route('admin.carts.index') }}" class="admin-filter-bar">
            <div class="admin-filter-row">
                <div class="admin-filter-field admin-filter-field--search">
                    <label class="admin-field-label" for="cart-search">Search</label>
                    <input
                        id="cart-search"
                        type="search"
                        name="search"
                        class="form-control admin-toolbar-search"
                        placeholder="Name or email…"
                        value="{{ $search }}"
                    >
                </div>
                <div class="admin-filter-actions">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    @if ($search !== '')
                        <a href="{{ route('admin.carts.index') }}" class="btn btn-secondary">Clear</a>
                    @endif
                </div>
            </div>
        </form>

        @if ($carts->isEmpty())
            <p class="empty-state">
                @if ($search !== '')
                    No carts match “{{ $search }}”.
                @else
                    No one has anything in their cart right now.
                @endif
            </p>
        @else
            <p class="admin-result-count">
                Showing {{ $carts->firstItem() }}–{{ $carts->lastItem() }}
            </p>

            <div class="admin-table-wrap">
                <table class="admin-table admin-carts-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Products</th>
                            <th class="admin-table-num">Qty</th>
                            <th class="admin-table-num">Total</th>
                            <th>Last updated</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($carts as $cart)
                            @php
                                $customer = $cart['user'];
                                $lines = $cart['lines'];
                                $updatedAt = $cart['updatedAt'] ? \Illuminate\Support\Carbon::parse($cart['updatedAt']) : null;
                                $displayName = $customer->name !== '' ? $customer->name : $customer->email;
                                $preview = $lines->take(3);
                                $remaining = $lines->count() - $preview->count();
                            @endphp
                            <tr>
                                <td>
                                    <span class="admin-table-primary">{{ $displayName }}</span>
                                    <span class="admin-table-sub">{{ $customer->email }}</span>
                                </td>
                                <td class="admin-carts-lines">
                                    @foreach ($preview as $line)
                                        <span class="admin-carts-line">
                                            {{ $line->quantity }}&times; {{ $line->product->localizedName() }}
                                            @if ($line->variantLabel())
                                                <span class="admin-table-sub">({{ $line->variantLabel() }})</span>
                                            @endif
                                        </span>
                                    @endforeach
                                    @if ($remaining > 0)
                                        <span class="admin-table-sub">+{{ $remaining }} more</span>
                                    @endif
                                </td>
                                <td class="admin-table-num">{{ number_format($cart['itemCount']) }}</td>
                                <td class="admin-table-num">{{ format_euros($cart['totalCents']) }}</td>
                                <td>
                                    @if ($updatedAt)
                                        <span class="admin-table-primary">{{ $updatedAt->format('d M Y') }}</span>
                                        <span class="admin-table-sub">{{ $updatedAt->format('H:i') }}</span>
                                    @else
                                        <span class="admin-table-sub">—</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="admin-table-actions">
                                        <a href="{{ route('admin.customers.show', $customer) }}" class="btn btn-sm btn-primary">View customer</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('admin.partials.pager', ['paginator' => $carts])
        @endif
    </div>
@endsection
