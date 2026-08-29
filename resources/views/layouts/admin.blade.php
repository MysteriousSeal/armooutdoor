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
                <a
                    href="{{ route('admin.analytics.index') }}"
                    class="admin-list-chip admin-active-now-chip admin-nav-active-now"
                    id="admin-nav-active-now"
                    title="Distinct visitors with a page view in the last 2 minutes"
                >
                    <span class="admin-active-now-dot" aria-hidden="true"></span>
                    <span class="admin-active-now-count">&hellip;</span>
                    active
                </a>
                <form action="{{ route('admin.search') }}" method="GET" class="admin-nav-search">
                    <input
                        type="search"
                        name="q"
                        class="admin-nav-search-input"
                        placeholder="Search…"
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
            @php
                // Le badge d'un groupe additionne ceux de ses entrées : replié,
                // il doit encore dire qu'il y a quelque chose à traiter dedans.
                $salesBadge = $ordersAwaitingStartCount + $unviewedCustomerCount + $unreadMessageCount;
                $salesActive = request()->routeIs('admin.orders.*', 'admin.customers.*', 'admin.conversations.*', 'admin.discounts.*', 'admin.discount-codes.*');
                $catalogueActive = request()->routeIs('admin.products.*', 'admin.categories.*', 'admin.labels.*', 'admin.reviews.*', 'admin.purchase-orders.*', 'admin.marketplaces.*');
                $systemActive = request()->routeIs('admin.settings.*', 'admin.stripe.*', 'admin.activity', 'admin.changelog', 'admin.backups.*');
                $accountingActive = request()->routeIs('admin.accounting.*');
                // Accounting and Backups are the owner's alone, and the group
                // that opens the right-hand block carries the margin: without
                // Accounting, System pushes, as before.
                $isOwner = auth()->user()?->isOwner() === true;
            @endphp

            <a href="{{ route('admin.dashboard') }}" class="admin-nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">Dashboard</a>

            <div class="admin-nav-group">
                <button type="button" class="admin-nav-link admin-nav-trigger {{ $salesActive ? 'active' : '' }}" data-nav-toggle aria-haspopup="true" aria-expanded="false">
                    Sales
                    @if ($salesBadge > 0)
                        <span class="admin-nav-badge" title="{{ $salesBadge }} waiting in this section">{{ $salesBadge }}</span>
                    @endif
                    <svg class="admin-nav-chevron" viewBox="0 0 24 24" width="11" height="11" aria-hidden="true">
                        <path d="m6 9 6 6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                <div class="admin-nav-menu" data-nav-menu hidden>
                    <a href="{{ route('admin.orders.index') }}" class="admin-nav-menu-item {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}">
                        Orders
                        @if ($ordersAwaitingStartCount > 0)
                            <span class="admin-nav-badge" title="{{ $ordersAwaitingStartCount }} not started yet">{{ $ordersAwaitingStartCount }}</span>
                        @endif
                    </a>
                    <a href="{{ route('admin.customers.index') }}" class="admin-nav-menu-item {{ request()->routeIs('admin.customers.*') ? 'active' : '' }}">
                        Customers
                        @if ($unviewedCustomerCount > 0)
                            <span class="admin-nav-badge" title="{{ $unviewedCustomerCount }} not looked at yet">{{ $unviewedCustomerCount }}</span>
                        @endif
                    </a>
                    <a href="{{ route('admin.conversations.index') }}" class="admin-nav-menu-item {{ request()->routeIs('admin.conversations.*') ? 'active' : '' }}">
                        Messages
                        @if ($unreadMessageCount > 0)
                            <span class="admin-nav-badge">{{ $unreadMessageCount }}</span>
                        @endif
                    </a>
                    <a href="{{ route('admin.discounts.index') }}" class="admin-nav-menu-item {{ request()->routeIs('admin.discounts.*', 'admin.discount-codes.*') ? 'active' : '' }}">Discounts</a>
                </div>
            </div>

            <div class="admin-nav-group">
                <button type="button" class="admin-nav-link admin-nav-trigger {{ $catalogueActive ? 'active' : '' }}" data-nav-toggle aria-haspopup="true" aria-expanded="false">
                    Catalog
                    @if ($purchaseOrdersAwaitingReceiptCount > 0)
                        <span class="admin-nav-badge" title="{{ $purchaseOrdersAwaitingReceiptCount }} awaiting receipt">{{ $purchaseOrdersAwaitingReceiptCount }}</span>
                    @endif
                    <svg class="admin-nav-chevron" viewBox="0 0 24 24" width="11" height="11" aria-hidden="true">
                        <path d="m6 9 6 6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                <div class="admin-nav-menu" data-nav-menu hidden>
                    <a href="{{ route('admin.products.index') }}" class="admin-nav-menu-item {{ request()->routeIs('admin.products.*') ? 'active' : '' }}">Products</a>
                    <a href="{{ route('admin.categories.index') }}" class="admin-nav-menu-item {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">Categories</a>
                    <a href="{{ route('admin.labels.index') }}" class="admin-nav-menu-item {{ request()->routeIs('admin.labels.*') ? 'active' : '' }}">Labels</a>
                    <a href="{{ route('admin.reviews.index') }}" class="admin-nav-menu-item {{ request()->routeIs('admin.reviews.*') ? 'active' : '' }}">Reviews</a>
                    <a href="{{ route('admin.purchase-orders.index') }}" class="admin-nav-menu-item {{ request()->routeIs('admin.purchase-orders.*') ? 'active' : '' }}">
                        Purchase orders
                        @if ($purchaseOrdersAwaitingReceiptCount > 0)
                            <span class="admin-nav-badge" title="{{ $purchaseOrdersAwaitingReceiptCount }} awaiting receipt">{{ $purchaseOrdersAwaitingReceiptCount }}</span>
                        @endif
                    </a>
                    <a href="{{ route('admin.marketplaces.index') }}" class="admin-nav-menu-item {{ request()->routeIs('admin.marketplaces.*') ? 'active' : '' }}">Marketplaces</a>
                </div>
            </div>

            <a href="{{ route('admin.blog.index') }}" class="admin-nav-link {{ request()->routeIs('admin.blog.*') ? 'active' : '' }}">Blog</a>

            @if ($isOwner)
                <div class="admin-nav-group admin-nav-group--end">
                    <button type="button" class="admin-nav-link admin-nav-trigger {{ $accountingActive ? 'active' : '' }}" data-nav-toggle aria-haspopup="true" aria-expanded="false">
                        Accounting
                        <svg class="admin-nav-chevron" viewBox="0 0 24 24" width="11" height="11" aria-hidden="true">
                            <path d="m6 9 6 6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <div class="admin-nav-menu" data-nav-menu hidden>
                        <a href="{{ route('admin.accounting.sales') }}" class="admin-nav-menu-item {{ request()->routeIs('admin.accounting.sales') ? 'active' : '' }}">Sales</a>
                        <a href="{{ route('admin.accounting.purchases') }}" class="admin-nav-menu-item {{ request()->routeIs('admin.accounting.purchases') ? 'active' : '' }}">Purchases</a>
                    </div>
                </div>
            @endif

            <div class="admin-nav-group {{ $isOwner ? '' : 'admin-nav-group--end' }}">
                <button type="button" class="admin-nav-link admin-nav-trigger {{ $systemActive ? 'active' : '' }}" data-nav-toggle aria-haspopup="true" aria-expanded="false">
                    System
                    <svg class="admin-nav-chevron" viewBox="0 0 24 24" width="11" height="11" aria-hidden="true">
                        <path d="m6 9 6 6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                <div class="admin-nav-menu admin-nav-menu--right" data-nav-menu hidden>
                    <a href="{{ route('admin.settings.index') }}" class="admin-nav-menu-item {{ request()->routeIs('admin.settings.*', 'admin.stripe.*') ? 'active' : '' }}">Settings</a>
                    <a href="{{ route('admin.activity') }}" class="admin-nav-menu-item {{ request()->routeIs('admin.activity') ? 'active' : '' }}">Activity</a>
                    <a href="{{ route('admin.analytics.index') }}" class="admin-nav-menu-item {{ request()->routeIs('admin.analytics.*') ? 'active' : '' }}">Analytics</a>
                    @if ($isOwner)
                        {{-- Owner only, like the accounts: an archive holds every
                             order and every customer's address. --}}
                        <a href="{{ route('admin.backups.index') }}" class="admin-nav-menu-item {{ request()->routeIs('admin.backups.*') ? 'active' : '' }}">Backups</a>
                    @endif
                    <a href="{{ route('admin.changelog') }}" class="admin-nav-menu-item {{ request()->routeIs('admin.changelog') ? 'active' : '' }}">Changelog</a>
                </div>
            </div>
        </nav>
    </header>

    <main class="admin-main">
        @if (session('status'))
            <div class="flash flash-success" role="status">{{ session('status') }}</div>
        @endif
        {{-- Catches validation failures from forms with no field to hang an
             @error on — a bulk action, say — which would otherwise redirect
             back looking as though nothing had happened. --}}
        {{-- isset(): $errors is shared by the web middleware group, so it is
             absent when this layout renders outside a normal request — an
             error page, for instance. --}}
        @if (isset($errors) && $errors->any())
            {{-- Deduplicated first: a bulk action can raise the same message
                 once per item, and the count decides the layout. --}}
            @php($flashErrors = collect($errors->all())->unique()->values())
            <div class="flash flash-error" role="alert">
                @if ($flashErrors->count() === 1)
                    {{ $flashErrors->first() }}
                @else
                    <p class="flash-error-title">Some changes could not be saved:</p>
                    <ul class="flash-error-list">
                        @foreach ($flashErrors as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
        @yield('content')
    </main>

    <script src="{{ asset('js/admin-nav-menu.js') }}" defer></script>
    <script src="{{ asset('js/admin-toast.js') }}" defer></script>
    <script src="{{ asset('js/pretty-select.js') }}" defer></script>
    <script src="{{ asset('js/theme-toggle.js') }}" defer></script>
    <script src="{{ asset('js/admin-modal.js') }}" defer></script>
    <script
        src="{{ asset('js/admin-active-now.js') }}"
        data-active-now-url="{{ route('admin.analytics.active-now') }}"
        defer
    ></script>
    @stack('scripts')
</body>
</html>
