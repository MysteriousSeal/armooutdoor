@extends('layouts.app')

@section('title', 'Guides — Armo Outdoor')
@section('meta_description', 'Les guides de la boutique : classer son arme (D, C, B, A), bien choisir sa cible, entretenir son matériel, d\'après ce que le rayon vend vraiment.')
@section('canonical', route('guides.index'))

@php
    // One card per guide, read off the shop's own shelf: the next guide is
    // one entry in App\Support\Guides, and the JSON-LD below reads the same
    // list rather than a second copy of it.
    $guides = \App\Support\Guides::all();
@endphp

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/guides/guides.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('css/guides/index.css') }}">
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

        <header class="glab-head">
            <p class="glab-head-kicker">La boutique conseille</p>
            <h1 class="glab-head-title"><span class="glab-title-accent">Guides</span></h1>
            <p class="glab-head-lede">
                Avant d'ouvrir le panier : ce qu'il faut savoir pour choisir le bon matériel,
                rayon par rayon, écrit par la boutique d'après ce qu'elle vend vraiment.
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
