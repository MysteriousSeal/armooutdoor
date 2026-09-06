{{-- Shared chrome of a single guide: the trail, then the shop's page hero. --}}
<nav class="breadcrumbs" aria-label="breadcrumb">
    <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
    <span class="breadcrumbs-sep" aria-hidden="true">/</span>
    <a href="{{ route('guides.index') }}">Guides</a>
    <span class="breadcrumbs-sep" aria-hidden="true">/</span>
    <span>{{ $crumb }}</span>
</nav>

@include('partials.page-hero', [
    'kicker' => $kicker,
    'title' => $title,
    'description' => $lede,
    'tags' => $tags ?? [],
])
