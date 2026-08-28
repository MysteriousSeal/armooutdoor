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
            <a href="{{ route('admin.settings.suppliers.index') }}" class="admin-stat-card">
                <span class="admin-stat-label">Suppliers</span>
                <span class="admin-stat-value admin-stat-value--sm">Suppliers and their lead time</span>
            </a>
            @if (auth()->user()->isOwner())
                <a href="{{ route('admin.settings.admins.index') }}" class="admin-stat-card">
                    <span class="admin-stat-label">Admins</span>
                    <span class="admin-stat-value admin-stat-value--sm">Who can access the back office</span>
                </a>
                <a href="{{ route('admin.stripe.orphaned-payments.index') }}" class="admin-stat-card">
                    <span class="admin-stat-label">Stripe payments</span>
                    <span class="admin-stat-value admin-stat-value--sm">All checkout sessions and their matching orders</span>
                </a>
                <a href="{{ route('admin.settings.email') }}" class="admin-stat-card">
                    <span class="admin-stat-label">Email test</span>
                    <span class="admin-stat-value admin-stat-value--sm">Send a test email and inspect the mail transport</span>
                </a>
            @endif
        </div>
    </div>
@endsection
