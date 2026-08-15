@extends('layouts.admin')

@section('title', 'Settings')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <p class="admin-list-kicker">Settings</p>
            <h2 class="admin-list-title">Settings</h2>
            <p class="admin-list-lede">Shop-wide configuration.</p>
        </header>

        <div class="admin-stat-grid">
            <a href="{{ route('admin.settings.shipping.edit') }}" class="admin-stat-card">
                <span class="admin-stat-label">Shipping</span>
                <span class="admin-stat-value admin-stat-value--sm">Free shipping, carrier prices &amp; package types</span>
            </a>
            <a href="{{ route('admin.settings.company.edit') }}" class="admin-stat-card">
                <span class="admin-stat-label">Company &amp; legal</span>
                <span class="admin-stat-value admin-stat-value--sm">Info shown on the legal pages</span>
            </a>
            <a href="{{ route('admin.settings.orders.edit') }}" class="admin-stat-card">
                <span class="admin-stat-label">Orders</span>
                <span class="admin-stat-value admin-stat-value--sm">Marketplaces you also sell on</span>
            </a>
            <a href="{{ route('admin.settings.invoice.edit') }}" class="admin-stat-card">
                <span class="admin-stat-label">Invoice</span>
                <span class="admin-stat-value admin-stat-value--sm">Footer link shown on invoice PDFs</span>
            </a>
        </div>
    </div>
@endsection
