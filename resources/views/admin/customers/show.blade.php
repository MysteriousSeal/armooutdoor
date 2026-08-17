@extends('layouts.admin')

@section('title', $customer->name !== '' ? $customer->name : $customer->email)

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker"><a href="{{ route('admin.customers.index') }}">Customers</a></p>
                    <div class="admin-customer-hero">
                        <span class="admin-customer-avatar admin-customer-avatar--lg" aria-hidden="true">{{ $customer->initials() }}</span>
                        <div>
                            <h2 class="admin-list-title">{{ $customer->name !== '' ? $customer->name : $customer->email }}</h2>
                            <p class="admin-list-lede">
                                {{ $customer->email }}
                                @if ($customer->external)
                                    · External customer
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
                <a href="{{ route('admin.orders.create') }}" class="btn btn-primary">Create manual order</a>
            </div>
            <div class="admin-list-meta">
                <span class="admin-list-chip">{{ number_format($orders->count()) }} {{ \Illuminate\Support\Str::plural('order', $orders->count()) }}</span>
                <span class="admin-list-chip">{{ format_euros($spentCents) }} spent</span>
                <span class="admin-list-chip">{{ number_format($customer->addresses->count()) }} {{ \Illuminate\Support\Str::plural('address', $customer->addresses->count()) }}</span>
                <span class="admin-list-chip">Joined {{ $customer->created_at->format('d M Y') }}</span>
            </div>
        </header>

        <div class="admin-order-layout">
            <div class="order-main">
                <section class="order-panel">
                    <h3 class="order-panel-title">Orders</h3>
                    @if ($orders->isEmpty())
                        <p class="empty-state">No orders yet.</p>
                    @else
                        <div class="admin-table-wrap">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Order</th>
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
                                                <span class="badge badge-{{ $order->status }}">
                                                    {{ ucfirst($order->status) }}
                                                </span>
                                                @if ($order->isArchived())
                                                    <span class="badge badge-disabled">Archived</span>
                                                @endif
                                            </td>
                                            <td class="admin-table-num">{{ $order->formattedTotal() }}</td>
                                            <td>
                                                <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-sm btn-primary">View</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            </div>

            <aside>
                <section class="order-panel">
                    <h3 class="order-panel-title">Addresses</h3>
                    @if ($customer->addresses->isEmpty())
                        <p class="empty-state">No saved addresses.</p>
                    @else
                        @foreach ($customer->addresses as $address)
                            <div class="customer-address">
                                <p class="admin-table-primary">
                                    {{ $address->recipientName() }}
                                    @if ($address->is_default)
                                        <span class="badge badge-active">Default</span>
                                    @endif
                                </p>
                                @if ($address->label)
                                    <p class="admin-table-sub">{{ $address->label }}</p>
                                @endif
                                <p>
                                    {{ $address->line1 }}<br>
                                    @if ($address->line2)
                                        {{ $address->line2 }}<br>
                                    @endif
                                    {{ $address->postal_code }} {{ $address->city }}<br>
                                    {{ __('store.country_'.$address->country) }}
                                    @if ($address->phone)
                                        <br>{{ $address->phone }}
                                    @endif
                                </p>
                            </div>
                        @endforeach
                    @endif
                </section>
            </aside>
        </div>
    </div>
@endsection
