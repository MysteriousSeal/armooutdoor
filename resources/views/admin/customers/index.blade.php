@extends('layouts.admin')

@section('title', 'Customers')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <p class="admin-list-kicker">Accounts</p>
            <h2 class="admin-list-title">Customers</h2>
            <p class="admin-list-lede">People who have created a shop account.</p>
            <div class="admin-list-meta">
                <span class="admin-list-chip">{{ number_format($customerCount) }} customers</span>
            </div>
        </header>

        <form method="GET" action="{{ route('admin.customers.index') }}" class="admin-toolbar">
            <input
                type="search"
                name="search"
                class="form-control"
                placeholder="Search name or email…"
                value="{{ $search }}"
            >
            <button type="submit" class="btn btn-secondary">Search</button>
            @if ($search !== '')
                <a href="{{ route('admin.customers.index') }}" class="btn btn-secondary">Clear</a>
            @endif
        </form>

        @if ($customers->isEmpty())
            <p class="empty-state">No customers found.</p>
        @else
            <p class="admin-result-count">
                Showing {{ $customers->firstItem() }}–{{ $customers->lastItem() }}
            </p>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Orders</th>
                            <th>Addresses</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($customers as $customer)
                            <tr>
                                <td>{{ $customer->name }}</td>
                                <td>{{ $customer->email }}</td>
                                <td>{{ $customer->orders_count }}</td>
                                <td>{{ $customer->addresses_count }}</td>
                                <td>{{ $customer->created_at->format('Y-m-d') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('admin.partials.pager', ['paginator' => $customers])
        @endif
    </div>
@endsection
