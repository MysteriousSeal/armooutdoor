<nav class="breadcrumbs" aria-label="breadcrumb">
    <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
    <span class="breadcrumbs-sep" aria-hidden="true">/</span>
    {{-- Named, not linked: the section has no page of its own, and a
         crumb that goes to a sibling is worse than one that goes
         nowhere. The index beside the document reaches them all. --}}
    <span class="breadcrumbs-section">Aide</span>
    <span class="breadcrumbs-sep" aria-hidden="true">/</span>
    <span>{{ $title }}</span>
</nav>

@include('partials.page-panel-header', [
    'kicker' => 'Aide',
    'title' => $title,
    'lede' => $lede,
    'meta' => null,
])
