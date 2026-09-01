<nav class="breadcrumbs" aria-label="breadcrumb">
    <a href="{{ route('home') }}">{{ __('store.breadcrumb_home') }}</a>
    <span class="breadcrumbs-sep" aria-hidden="true">/</span>
    {{-- Named, not linked: the section has no page of its own, and a
         crumb that goes to a sibling is worse than one that goes
         nowhere. The index beside the document reaches them all. --}}
    <span class="breadcrumbs-section">{{ __('store.legal_kicker') }}</span>
    <span class="breadcrumbs-sep" aria-hidden="true">/</span>
    <span>{{ $title }}</span>
</nav>

@include('partials.page-panel-header', [
    'kicker' => __('store.legal_kicker'),
    'title' => $title,
    'lede' => $lede ?? null,
    'meta' => __('store.legal_updated', [
        'date' => \Illuminate\Support\Carbon::parse(config('shop.legal_updated.'.$page))->translatedFormat('d F Y'),
    ]),
])
