<nav class="breadcrumbs" aria-label="breadcrumb">
    <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
    <span class="breadcrumbs-sep" aria-hidden="true">/</span>
    <span>{{ $title }}</span>
</nav>

<header class="page-header">
    <p class="home-kicker">Aide</p>
    <h1 class="page-title">{{ $title }}</h1>
    <p class="page-lede">{{ $lede }}</p>
</header>

<nav class="help-nav" aria-label="Aide">
    <a href="{{ route('faq') }}">FAQ</a>
    <a href="{{ route('help.shipping-returns') }}" class="{{ request()->routeIs('help.shipping-returns') ? 'is-active' : '' }}">
        Livraison &amp; Retours
    </a>
    <a href="{{ route('help.secure-payment') }}" class="{{ request()->routeIs('help.secure-payment') ? 'is-active' : '' }}">
        Paiement sécurisé
    </a>
</nav>
