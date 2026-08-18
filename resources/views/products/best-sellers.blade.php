@extends('layouts.app')

@section('title', 'Meilleures ventes — '.config('app.name'))
@section('meta_description', 'Les produits les plus vendus de la boutique Armo Outdoor : cibles, stand de tir, vêtements, terrain, quotidien et munitions.')
@section('canonical', route('products.best-sellers'))

@push('head')
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@@type' => 'ListItem',
                    'position' => 1,
                    'name' => __('store.breadcrumb_home'),
                    'item' => localized_route('home'),
                ],
                [
                    '@@type' => 'ListItem',
                    'position' => 2,
                    'name' => 'Meilleures ventes',
                    'item' => route('products.best-sellers'),
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'CollectionPage',
            'name' => 'Meilleures ventes',
            'description' => 'Les produits les plus vendus de la boutique Armo Outdoor.',
            'url' => route('products.best-sellers'),
            'inLanguage' => 'fr-FR',
            'mainEntity' => [
                '@@type' => 'ItemList',
                'name' => 'Meilleures ventes',
                'numberOfItems' => $products->count(),
                'itemListElement' => $products->values()->map(fn ($product, $index) => [
                    '@@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $product->localizedName(),
                    'url' => localized_route('products.show', ['product' => $product->slug]),
                ])->all(),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('content')
    <div class="container">
        <nav class="breadcrumbs" aria-label="breadcrumb">
            <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <span>Meilleures ventes</span>
        </nav>

        <header class="page-header">
            <p class="home-kicker">{{ __('store.hero_kicker') }}</p>
            <h1 class="page-title">Meilleures ventes</h1>
            <p class="page-lede">Les produits les plus vendus de la boutique.</p>
        </header>

        @if ($products->isEmpty())
            <p class="empty-state">{{ __('store.search_empty') }}</p>
        @else
            <div class="product-grid">
                @foreach ($products as $index => $product)
                    @include('partials.product-card', ['product' => $product, 'lazy' => $index > 1])
                @endforeach
            </div>
        @endif
    </div>
@endsection
