@extends('layouts.app')

@section('title', 'Guides d\'achat — Armo Outdoor')
@section('meta_description', 'Les guides d\'achat de la boutique : bien choisir sa cible, régler son matériel, comparer les familles de produits avant de commander.')
@section('canonical', route('guides.index'))

@php
    // One card per guide; the next guide is one entry here, and the
    // JSON-LD below reads the same list.
    $guides = [
        [
            'route' => route('guides.cibles'),
            'kicker' => 'Cibles',
            'title' => 'Bien choisir sa cible',
            'text' => 'Réactives autocollantes, planches, carton ou métal basculant : quel format pour quelle distance, ce qu\'on lit après le tir, et combien de feuilles prévoir.',
        ],
        [
            'route' => route('guides.entretien'),
            'kicker' => 'Entretien',
            'title' => 'Entretenir son arme',
            'text' => 'Corde de nettoyage ou kit à tiges : quel matériel pour quel calibre, du 4,5 mm au calibre 12, dans quel sens nettoyer et à quelle fréquence.',
        ],
    ];
@endphp

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/guides/guides.css') }}">
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'CollectionPage',
            'name' => 'Guides d\'achat',
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
                    'url' => $guide['route'],
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
                ['@@type' => 'ListItem', 'position' => 2, 'name' => 'Guides d\'achat', 'item' => route('guides.index')],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('content')
    <div class="container glab glab-index">
        <nav class="breadcrumbs" aria-label="breadcrumb">
            <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <span>Guides d'achat</span>
        </nav>

        <header class="glab-head">
            <p class="glab-head-kicker">La boutique conseille</p>
            <h1 class="glab-head-title"><span class="glab-title-accent">Guides d'achat</span></h1>
            <p class="glab-head-lede">
                Avant d'ouvrir le panier : ce qu'il faut savoir pour choisir le bon matériel,
                rayon par rayon, écrit par la boutique d'après ce qu'elle vend vraiment.
            </p>
        </header>

        <div class="glab-index-grid">
            @foreach ($guides as $guide)
                <a href="{{ $guide['route'] }}" class="glab-index-card">
                    <span class="glab-index-card-top">
                        <span class="glab-index-num">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="glab-index-kicker">{{ $guide['kicker'] }}</span>
                    </span>
                    <h2>{{ $guide['title'] }}</h2>
                    <p>{{ $guide['text'] }}</p>
                    <span class="glab-index-more">Lire le guide</span>
                </a>
            @endforeach
        </div>
    </div>
@endsection
