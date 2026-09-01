<nav class="breadcrumbs" aria-label="breadcrumb">
    <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
    <span class="breadcrumbs-sep" aria-hidden="true">/</span>
    {{-- The FAQ is the section's own page, so this goes somewhere real. --}}
    <a href="{{ route('faq') }}">Aide</a>
    <span class="breadcrumbs-sep" aria-hidden="true">/</span>
    <span>{{ $title }}</span>
</nav>

@include('partials.page-panel-header', [
    'kicker' => 'Aide',
    'title' => $title,
    'lede' => $lede,
    'meta' => null,
])
