<nav class="breadcrumbs" aria-label="breadcrumb">
    <a href="{{ route('home') }}">{{ __('store.breadcrumb_home') }}</a>
    <span class="breadcrumbs-sep" aria-hidden="true">/</span>
    <span>{{ $title }}</span>
</nav>

<header class="page-header legal-header">
    <p class="home-kicker">{{ __('store.legal_kicker') }}</p>
    <h2 class="page-title">{{ $title }}</h2>
    <p class="page-lede">{{ __('store.legal_updated', ['date' => now()->translatedFormat('d F Y')]) }}</p>
</header>

<nav class="legal-nav" aria-label="{{ __('store.legal_kicker') }}">
    <a href="{{ route('legal.terms') }}" class="{{ request()->routeIs('legal.terms') ? 'is-active' : '' }}">
        {{ __('store.legal_terms_nav') }}
    </a>
    <a href="{{ route('legal.notice') }}" class="{{ request()->routeIs('legal.notice') ? 'is-active' : '' }}">
        {{ __('store.legal_notice_nav') }}
    </a>
    <a href="{{ route('legal.privacy') }}" class="{{ request()->routeIs('legal.privacy') ? 'is-active' : '' }}">
        {{ __('store.legal_privacy_nav') }}
    </a>
    <a href="{{ route('legal.withdrawal') }}" class="{{ request()->routeIs('legal.withdrawal') ? 'is-active' : '' }}">
        {{ __('store.legal_withdrawal_nav') }}
    </a>
</nav>
