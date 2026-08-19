@extends('layouts.app')

@section('title', 'Promotions — '.config('app.name'))
@section('meta_description', 'Les produits actuellement en promotion sur la boutique Armo Outdoor : cibles, stand de tir, vêtements, terrain, quotidien et munitions.')
@section('canonical', route('products.promotions'))

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/categories.css') }}">
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
                    'name' => 'Promotions',
                    'item' => route('products.promotions'),
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'CollectionPage',
            'name' => 'Promotions',
            'description' => 'Les produits actuellement en promotion sur la boutique Armo Outdoor.',
            'url' => route('products.promotions'),
            'inLanguage' => 'fr-FR',
            'mainEntity' => [
                '@@type' => 'ItemList',
                'name' => 'Promotions',
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
            <span>Promotions</span>
        </nav>

        @include('partials.page-hero', [
            'kicker' => __('store.hero_kicker'),
            'title' => 'Promotions',
            'description' => 'Les produits actuellement en promotion.',
            'tags' => [trans_choice('store.products_count', $products->count(), ['count' => $products->count()])],
            'imageUrl' => asset('images/pages/promotions-hero.webp'),
        ])

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
