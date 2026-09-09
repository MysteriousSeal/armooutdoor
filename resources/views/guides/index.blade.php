@extends('layouts.app')

@section('title', 'Guides du tir et de l\'airsoft : loi, énergie, matériel — Armo Outdoor')
@section('meta_description', 'Neuf guides écrits par la boutique : les catégories D, C, B et A, les joules et les FPS, où l\'on peut tirer, la première séance au stand, le réglage d\'une lunette, les cibles, l\'entretien, le camouflage et le glossaire.')
@section('canonical', route('guides.index'))

@php
    // One card per guide, read off the shop's own shelf: the next guide is
    // one entry in App\Support\Guides, and the JSON-LD below reads the same
    // list rather than a second copy of it.
    $guides = \App\Support\Guides::all();
@endphp

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/guides.css') }}">
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'CollectionPage',
            'name' => 'Guides',
            'url' => route('guides.index'),
            'inLanguage' => 'fr-FR',
            'isPartOf' => ['@@id' => \App\Support\OrganizationSchema::websiteId()],
            'mainEntity' => [
                '@@type' => 'ItemList',
                'numberOfItems' => count($guides),
                'itemListElement' => collect($guides)->values()->map(fn (array $guide, int $index): array => [
                    '@@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $guide['title'],
                    'url' => $guide['url'],
                ])->all(),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@@type' => 'ListItem', 'position' => 1, 'name' => __('store.breadcrumb_home'), 'item' => localized_route('home')],
                ['@@type' => 'ListItem', 'position' => 2, 'name' => 'Guides', 'item' => route('guides.index')],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('content')
    <div class="container glab glab-index">
        <nav class="breadcrumbs" aria-label="breadcrumb">
            <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <span>Guides</span>
        </nav>

        {{-- The shelf is the page best placed to answer a broad question, and
             it was titled with one word and carried three hundred of them.
             What it says now is what the seven guides actually cover, in the
             order they are read: the law decides what you may buy, energy
             decides most of the rest, and the rayons come after. --}}
        <header class="glab-head">
            <p class="glab-head-kicker">La boutique conseille</p>
            <h1 class="glab-head-title">Les guides du tir <span class="glab-title-accent">et de l'airsoft</span></h1>
            <p class="glab-head-lede">
                Neuf pages écrites par la boutique, d'après ce qu'elle vend et ce qu'on lui
                demande au comptoir. La loi d'abord, parce qu'elle décide de ce que vous avez
                le droit d'acheter et d'emporter. L'énergie ensuite, parce qu'elle décide de
                presque tout le reste. Puis le matériel, rayon par rayon.
            </p>
            <p class="glab-head-lede">
                Aucune n'est un billet d'humeur : chacune répond à une question qui revient,
                cite ses textes quand il y en a, et dit ce qu'elle ne sait pas plutôt que de
                le combler. Elles sont reprises quand le rayon change, ou quand le droit
                change.
            </p>
        </header>

        <div class="glab-index-grid">
            @foreach ($guides as $guide)
                <a href="{{ $guide['url'] }}" class="glab-index-card">
                    <span class="glab-index-card-top">
                        <span class="glab-index-num">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="glab-index-kicker">{{ $guide['topic'] }}</span>
                    </span>
                    <h2>{{ $guide['title'] }}</h2>
                    <p>{{ $guide['summary'] }}</p>
                    <span class="glab-index-more">Lire le guide</span>
                </a>
            @endforeach
        </div>
    </div>
@endsection
