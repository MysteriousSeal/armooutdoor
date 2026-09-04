@extends('layouts.app')

@section('title', 'Guides d\'achat — Armo Outdoor')
@section('meta_description', 'Les guides d\'achat de la boutique : bien choisir sa cible, régler son matériel, comparer les familles de produits avant de commander.')
@section('canonical', route('guides.index'))

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/guide-cibles.css') }}">
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
    <div class="container glab">
        <nav class="breadcrumbs" aria-label="breadcrumb">
            <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <span>Guides d'achat</span>
        </nav>

        <header class="glab-head">
            <p class="glab-head-kicker">La boutique conseille</p>
            <h1 class="glab-head-title">Guides <span class="glab-title-accent">d'achat</span></h1>
            <p class="glab-head-lede">
                Avant d'ouvrir le panier : ce qu'il faut savoir pour choisir le bon matériel,
                rayon par rayon, écrit par la boutique d'après ce qu'elle vend vraiment.
            </p>
        </header>

        @php
            // One card per guide; the next guide is one entry here.
            $guides = [
                [
                    'route' => route('guides.cibles'),
                    'kicker' => 'Cibles',
                    'title' => 'Bien choisir sa cible',
                    'text' => 'Réactives autocollantes, planches, carton ou métal basculant : quel format pour quelle distance, ce qu\'on lit après le tir, et combien de feuilles prévoir.',
                ],
            ];
        @endphp

        <div class="glab-index-grid">
            @foreach ($guides as $guide)
                <a href="{{ $guide['route'] }}" class="glab-index-card">
                    <span class="glab-index-kicker">{{ $guide['kicker'] }}</span>
                    <h2>{{ $guide['title'] }}</h2>
                    <p>{{ $guide['text'] }}</p>
                    <span class="glab-index-more">Lire le guide</span>
                </a>
            @endforeach
        </div>
    </div>
@endsection
