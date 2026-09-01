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
