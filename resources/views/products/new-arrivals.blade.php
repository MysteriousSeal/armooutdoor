@extends('layouts.app')

@section('title', 'Nouveautés — '.config('app.name'))
@section('meta_description', 'Les derniers produits ajoutés à la boutique Armo Outdoor : cibles, stand de tir, vêtements, terrain, quotidien et munitions.')
@section('canonical', route('products.new-arrivals'))

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
                    'name' => 'Nouveautés',
                    'item' => route('products.new-arrivals'),
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'CollectionPage',
            'name' => 'Nouveautés',
            'description' => 'Les derniers produits ajoutés à la boutique Armo Outdoor.',
            'url' => route('products.new-arrivals'),
            'inLanguage' => 'fr-FR',
            'mainEntity' => [
                '@@type' => 'ItemList',
                'name' => 'Nouveautés',
                'numberOfItems' => $products->count(),
                'itemListElement' => $products->values()->map(fn ($product, $index) => [
                    '@@type' => 'ListItem',
                    'position' => $index + 1,
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
            <span>Nouveautés</span>
        </nav>

        <header class="page-header">
            <p class="home-kicker">{{ __('store.hero_kicker') }}</p>
            <h1 class="page-title">Nouveautés</h1>
            <p class="page-lede">Les derniers produits ajoutés à la boutique.</p>
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
