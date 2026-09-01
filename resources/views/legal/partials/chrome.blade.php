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
