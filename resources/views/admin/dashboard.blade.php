@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <p class="admin-list-kicker">Overview</p>
            <h2 class="admin-list-title">Dashboard</h2>
            <p class="admin-list-lede">A quiet look at the shop — customers and products.</p>
        </header>

        <div class="admin-stat-grid">
            <a href="{{ route('admin.customers.index') }}" class="admin-stat-card">
                <span class="admin-stat-label">Customers</span>
                <span class="admin-stat-value">{{ number_format($customerCount) }}</span>
            </a>
            <a href="{{ route('admin.products.index') }}" class="admin-stat-card">
                <span class="admin-stat-label">Products</span>
                <span class="admin-stat-value">{{ number_format($productCount) }}</span>
            </a>
            <a href="{{ route('admin.orders.index') }}" class="admin-stat-card">
                <span class="admin-stat-label">Orders</span>
                <span class="admin-stat-value">{{ number_format($orderCount) }}</span>
            </a>
        </div>
    </div>
@endsection
