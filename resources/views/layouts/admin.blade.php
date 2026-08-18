<!DOCTYPE html>
@php
    $theme = \App\Support\ThemePreference::resolve(request());
@endphp
<html lang="en" data-theme="{{ $theme }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        (function () {
            var match = document.cookie.match(/(?:^|; )theme=(light|dark)/);
            if (match) {
                document.documentElement.setAttribute('data-theme', match[1]);
            }
        })();
    </script>
    <title>@yield('title', 'Admin') — Armo Outdoor</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <meta name="theme-color" content="#8b7e74">
    <link rel="preload" href="{{ asset('fonts/inter-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ versioned_asset('css/base.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('css/admin.css') }}">
    @stack('styles')
</head>
<body class="admin-body">
    <header class="admin-nav">
        <div class="admin-nav-bar">
            <div class="admin-brand-wrap">
                <a href="{{ route('admin.dashboard') }}" class="admin-brand">
                    <span class="admin-brand-mark">
                        <span class="logo-primary">Armo</span><span class="logo-secondary">Outdoor</span>
                    </span>
                    <span class="admin-brand-badge">Admin</span>
                </a>
                <a href="{{ route('admin.changelog') }}" class="admin-brand-version">v{{ config('shop.version') }}</a>
            </div>

            <div class="admin-nav-actions">
                <form action="{{ route('admin.search') }}" method="GET" class="admin-nav-search">
                    <input
                        type="search"
                        name="q"
                        class="admin-nav-search-input"
                        placeholder="Search orders, customers, products…"
                        value="{{ request()->routeIs('admin.search') ? request()->query('q') : '' }}"
                    >
                </form>
                <button
                    type="button"
                    class="theme-toggle-btn"
                    id="theme-toggle"
                    data-theme="{{ $theme }}"
                    title="Toggle dark mode"
                    aria-label="Toggle dark mode"
                >
                    <span class="theme-toggle-icon theme-toggle-icon-sun" aria-hidden="true">☀</span>
                    <span class="theme-toggle-icon theme-toggle-icon-moon" aria-hidden="true">☾</span>
                </button>
                <a href="{{ route('home') }}" class="admin-nav-utility" target="_blank" rel="noopener noreferrer">View shop</a>
                <form action="{{ route('admin.logout') }}" method="POST" class="admin-nav-logout">
                    @csrf
                    <button type="submit" class="admin-nav-utility">Logout</button>
                </form>
            </div>
        </div>

        <nav class="admin-nav-links" aria-label="Admin sections">
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">Dashboard</a>
            <a href="{{ route('admin.customers.index') }}" class="{{ request()->routeIs('admin.customers.*') ? 'active' : '' }}">Customers</a>
            <a href="{{ route('admin.orders.index') }}" class="{{ request()->routeIs('admin.orders.*') ? 'active' : '' }}">Orders</a>
            <a href="{{ route('admin.products.index') }}" class="{{ request()->routeIs('admin.products.*') ? 'active' : '' }}">Products</a>
            <a href="{{ route('admin.categories.index') }}" class="{{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">Categories</a>
            <a href="{{ route('admin.discounts.index') }}" class="{{ request()->routeIs('admin.discounts.*', 'admin.discount-codes.*') ? 'active' : '' }}">Discounts</a>
            <a href="{{ route('admin.settings.index') }}" class="{{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">Settings</a>
            <a href="{{ route('admin.activity') }}" class="{{ request()->routeIs('admin.activity') ? 'active' : '' }}">Activity</a>
            <a href="{{ route('admin.changelog') }}" class="{{ request()->routeIs('admin.changelog') ? 'active' : '' }}">Changelog</a>
        </nav>
    </header>

    <main class="admin-main">
        @if (session('status'))
            <div class="flash flash-success" role="status">{{ session('status') }}</div>
        @endif
        @yield('content')
    </main>

    <script src="{{ asset('js/pretty-select.js') }}" defer></script>
    <script src="{{ asset('js/theme-toggle.js') }}" defer></script>
    <script src="{{ asset('js/admin-modal.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
