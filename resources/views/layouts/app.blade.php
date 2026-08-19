<!DOCTYPE html>
@php
    $theme = \App\Support\ThemePreference::resolve(request());
    $locale = app()->getLocale();
@endphp
<html lang="{{ str_replace('_', '-', $locale) }}" data-theme="{{ $theme }}">
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
    <title>@yield('title', config('app.name'))</title>
    <meta name="description" content="@yield('meta_description', __('store.meta_home'))">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <meta name="theme-color" content="#8b7e74">
    @hasSection('canonical')
        <link rel="canonical" href="@yield('canonical')">
    @endif
    <link rel="preload" href="{{ asset('fonts/inter-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ versioned_asset('css/base.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('css/app.css') }}">
    @stack('head')
</head>
<body>
    <header class="site-header">
        <div class="site-header-inner">
            <div class="site-header-main">
                <h1 class="site-logo">
                    <a href="{{ localized_route('home') }}" class="site-logo-link">
                        <span class="site-logo-wordmark">
                            <span class="logo-primary">Armo</span><span class="logo-secondary">Outdoor</span>
                        </span>
                    </a>
                </h1>
                <p class="site-tagline">{{ __('store.tagline') }}</p>
            </div>
            <div class="site-header-actions">
                <form action="{{ localized_route('search') }}" method="GET" class="site-search" role="search">
                    <label for="site-search-input" class="sr-only">{{ __('store.search_label') }}</label>
                    <button type="submit" class="site-search-btn" aria-label="{{ __('store.search_label') }}">
                        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                            <circle cx="11" cy="11" r="6.5" fill="none" stroke="currentColor" stroke-width="1.75"/>
                            <path d="M20 20l-4.3-4.3" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                        </svg>
                    </button>
                    <input
                        type="search"
                        id="site-search-input"
                        name="q"
                        class="site-search-input"
                        placeholder="{{ __('store.search_placeholder') }}"
                        value="{{ request('q') }}"
                    >
                </form>
                @include('partials.cart-button', ['class' => 'cart-btn--header'])
            </div>
        </div>
    </header>

    <div class="site-subheader" id="site-subheader">
        <div class="site-subheader-inner">
            <div class="site-menu-panel">
                <div class="site-subheader-nav">
                    <button
                        type="button"
                        class="site-menu-toggle {{ request()->routeIs('categories.*') ? 'is-active' : '' }}"
                        id="site-menu-toggle"
                        aria-expanded="false"
                        aria-controls="site-cat-menu"
                        aria-label="{{ __('store.menu_toggle') }}"
                    >
                        <svg class="site-menu-toggle-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                            <path d="M4 6h16M4 12h16M4 18h16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                        </svg>
                        <span>{{ __('store.nav_categories') }}</span>
                    </button>
                    <nav class="sort-tabs" aria-label="{{ __('store.footer_shop') }}">
                        <a href="{{ localized_route('home') }}" class="sort-tab {{ request()->routeIs('home') ? 'active' : '' }}">
                            {{ __('store.nav_home') }}
                        </a>
                        <a href="{{ route('products.new-arrivals') }}" class="sort-tab {{ request()->routeIs('products.new-arrivals') ? 'active' : '' }}">
                            {{ __('store.footer_shop_new') }}
                        </a>
                        <a href="{{ route('products.promotions') }}" class="sort-tab {{ request()->routeIs('products.promotions') ? 'active' : '' }}">
                            {{ __('store.footer_shop_promotions') }}
                        </a>
                        <a href="{{ route('products.best-sellers') }}" class="sort-tab {{ request()->routeIs('products.best-sellers') ? 'active' : '' }}">
                            {{ __('store.footer_shop_best_sellers') }}
                        </a>
                        <a href="#" class="sort-tab">
                            {{ __('store.footer_help_contact') }}
                        </a>
                    </nav>
                </div>

                <span class="site-header-divider" aria-hidden="true"></span>

                <div class="site-auth">
                    <button
                        type="button"
                        class="theme-toggle-btn"
                        id="theme-toggle"
                        data-theme="{{ $theme }}"
                        title="{{ __('store.theme_toggle') }}"
                        aria-label="{{ __('store.theme_toggle') }}"
                    >
                        <span class="theme-toggle-icon theme-toggle-icon-sun" aria-hidden="true">☀</span>
                        <span class="theme-toggle-icon theme-toggle-icon-moon" aria-hidden="true">☾</span>
                    </button>

                    @include('partials.cart-button', ['class' => 'cart-btn--nav'])

                    <span class="site-header-divider" aria-hidden="true"></span>

                    @auth
                        <a href="{{ localized_route('account.index') }}" class="site-auth-name {{ request()->routeIs('account.*') ? 'is-active' : '' }}">
                            {{ auth()->user()->name }}
                        </a>
                        <form action="{{ localized_route('logout') }}" method="POST" class="site-auth-logout">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-secondary">{{ __('store.logout') }}</button>
                        </form>
                    @else
                        <a href="{{ localized_route('login') }}" class="btn btn-sm btn-secondary {{ request()->routeIs('login') ? 'is-active' : '' }}">{{ __('store.login') }}</a>
                        <a href="{{ localized_route('register') }}" class="btn btn-sm btn-primary {{ request()->routeIs('register') ? 'is-active' : '' }}">{{ __('store.register') }}</a>
                    @endauth
                </div>
            </div>
        </div>

        @php
            $routeCategory = request()->route('category');
            $routeProduct = request()->route('product');
            $currentCategory = $routeCategory instanceof \App\Models\Category
                ? $routeCategory
                : ($routeProduct instanceof \App\Models\Product ? $routeProduct->category : null);
        @endphp
        <div class="site-cat-menu" id="site-cat-menu" hidden>
            <div class="site-cat-menu-inner">
                @foreach ($navCategories as $navCategory)
                    @php
                        $visibleChildren = $navCategory->children->where('products_count', '>', 0);
                        $navActive = $currentCategory
                            && ($currentCategory->is($navCategory) || $currentCategory->parent_id === $navCategory->id);
                    @endphp
                    <section class="site-cat-group {{ $navActive ? 'is-active' : '' }}">
                        <a
                            href="{{ localized_route('categories.show', ['category' => $navCategory->slug]) }}"
                            class="site-cat-group-head"
                        >
                            <span class="site-cat-group-icon" aria-hidden="true">
                                @include('partials.icon', ['name' => $navCategory->iconName(), 'size' => 18])
                            </span>
                            <span class="site-cat-group-copy">
                                <span class="site-cat-group-kicker">{{ __('store.shop_subcategories') }}</span>
                                <span class="site-cat-group-name">{{ $navCategory->localizedName() }}</span>
                            </span>
                        </a>
                        @if ($visibleChildren->isNotEmpty())
                            <ul class="site-cat-group-links">
                                @foreach ($visibleChildren->take(4) as $child)
                                    <li>
                                        <a
                                            href="{{ localized_route('categories.show', ['category' => $child->slug]) }}"
                                            class="{{ $currentCategory?->is($child) ? 'is-active' : '' }}"
                                        >
                                            <span class="site-cat-group-link-name">{{ $child->localizedName() }}</span>
                                            <span class="site-cat-group-link-count">{{ $child->products_count }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        <a
                            href="{{ localized_route('categories.show', ['category' => $navCategory->slug]) }}"
                            class="site-cat-group-more"
                        >
                            {{ $visibleChildren->count() > 4 ? __('store.nav_see_more') : __('store.view_category') }}
                        </a>
                    </section>
                @endforeach
            </div>
            <div class="site-cat-menu-footer">
                <a href="{{ route('categories.index') }}">{{ __('store.see_all_categories') }}</a>
            </div>
        </div>
    </div>

    <main class="site-main">
        @if (session('status'))
            <div class="container">
                <div class="flash flash-success" role="status">{{ session('status') }}</div>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="site-footer-inner">
            <div class="site-footer-top">
                <div class="site-footer-brand">
                    <a href="{{ localized_route('home') }}" class="site-footer-brand-name">
                        <span class="logo-primary">Armo</span><span class="logo-secondary">Outdoor</span>
                    </a>
                    <p class="site-footer-about">{{ __('store.footer_about') }}</p>
                    <p class="site-footer-about">{{ __('store.footer_about_more') }}</p>
                </div>

                <nav class="site-footer-col" aria-labelledby="footer-shop-heading">
                    <h2 id="footer-shop-heading" class="site-footer-heading">{{ __('store.footer_shop') }}</h2>
                    <ul class="site-footer-links">
                        <li><a href="{{ route('categories.index') }}">{{ __('store.footer_shop_all_categories') }}</a></li>
                        <li><a href="{{ route('products.new-arrivals') }}">{{ __('store.footer_shop_new') }}</a></li>
                        <li><a href="{{ route('products.promotions') }}">{{ __('store.footer_shop_promotions') }}</a></li>
                        <li><a href="{{ route('products.best-sellers') }}">{{ __('store.footer_shop_best_sellers') }}</a></li>
                    </ul>
                </nav>

                <nav class="site-footer-col" aria-labelledby="footer-site-heading">
                    <h2 id="footer-site-heading" class="site-footer-heading">{{ __('store.footer_site') }}</h2>
                    <ul class="site-footer-links">
                        <li><a href="{{ localized_route('orders.index') }}">{{ __('store.footer_account_orders') }}</a></li>
                        <li><a href="{{ localized_route('account.addresses.index') }}">{{ __('store.footer_account_addresses') }}</a></li>
                        <li><a href="{{ localized_route('account.wishlist.index') }}">{{ __('store.footer_account_wishlist') }}</a></li>
                        <li><a href="{{ localized_route('account.profile.edit') }}">{{ __('store.footer_account_profile') }}</a></li>
                    </ul>
                </nav>

                <nav class="site-footer-col" aria-labelledby="footer-help-heading">
                    <h2 id="footer-help-heading" class="site-footer-heading">{{ __('store.footer_help') }}</h2>
                    <ul class="site-footer-links">
                        <li><a href="{{ route('faq') }}">{{ __('store.footer_help_faq') }}</a></li>
                        <li><a href="{{ route('help.shipping-returns') }}">{{ __('store.footer_help_shipping_returns') }}</a></li>
                        <li><a href="{{ route('help.secure-payment') }}">{{ __('store.footer_help_secure_payment') }}</a></li>
                        <li><a href="#">{{ __('store.footer_help_contact') }}</a></li>
                    </ul>
                </nav>
            </div>

            <div class="site-footer-bottom">
                <p class="site-footer-copy">
                    &copy; {{ date('Y') }}
                    <a href="{{ localized_route('home') }}">Armo Outdoor</a>
                    <span class="site-footer-copy-sep" aria-hidden="true">·</span>
                    <span class="site-footer-copy-note">{{ __('store.footer_note') }}</span>
                    <span class="site-footer-copy-sep" aria-hidden="true">·</span>
                    <span class="site-footer-version">v{{ config('shop.version') }}</span>
                </p>
                <nav class="site-footer-legal" aria-label="{{ __('store.legal_notice_title') }}">
                    <a href="{{ route('legal.terms') }}">{{ __('store.legal_terms_title') }}</a>
                    <a href="{{ route('legal.notice') }}">{{ __('store.legal_notice_title') }}</a>
                    <a href="{{ route('legal.privacy') }}">{{ __('store.legal_privacy_title') }}</a>
                    <a href="{{ route('legal.withdrawal') }}">{{ __('store.legal_withdrawal_title') }}</a>
                    <a href="{{ route('sitemap.html') }}">{{ __('store.footer_sitemap') }}</a>
                </nav>
            </div>
        </div>
    </footer>

    @include('partials.add-to-cart-modal')

    <script src="{{ asset('js/pretty-select.js') }}" defer></script>
    <script src="{{ asset('js/site-menu-toggle.js') }}" defer></script>
    <script src="{{ asset('js/theme-toggle.js') }}" defer></script>
    <script src="{{ asset('js/cart-modal.js') }}" defer></script>
    <script src="{{ asset('js/cart-quantity-toast.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
