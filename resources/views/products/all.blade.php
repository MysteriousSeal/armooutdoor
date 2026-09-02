@extends('layouts.app')

@section('title', paginated_title('Tous les produits', $products).' — '.config('app.name'))
@section('meta_description', 'Tout le catalogue Armo Outdoor sur une page : cibles, matériel de stand, vêtements, terrain, quotidien, munitions et optiques.')
@section('canonical', paginated_canonical(localized_route('products.all'), $products))

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
                    'name' => 'Tous les produits',
                    'item' => localized_route('products.all'),
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
    <script type="application/ld+json">
        {{-- The products of the page being read, not the whole catalogue:
             positions carry on across pages. --}}
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'CollectionPage',
            'name' => 'Tous les produits',
            'description' => 'Tout le catalogue Armo Outdoor sur une page.',
            'url' => paginated_canonical(localized_route('products.all'), $products),
            'inLanguage' => 'fr-FR',
            'mainEntity' => [
                '@@type' => 'ItemList',
                'name' => 'Tous les produits',
                'numberOfItems' => $products->total(),
                'itemListElement' => $products->values()->map(fn ($product, $index) => [
                    '@@type' => 'ListItem',
                    'position' => $products->firstItem() + $index,
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
            <span>{{ __('store.all_products') }}</span>
        </nav>

        @include('partials.page-hero', [
            'kicker' => __('store.hero_kicker'),
            'title' => __('store.all_products'),
            'description' => 'Tout le catalogue, du plus pertinent au plus discret.',
            'tags' => [trans_choice('store.products_count', $products->total(), ['count' => $products->total()])],
        ])

        @if ($products->isEmpty())
            <p class="empty-state">{{ __('store.search_empty') }}</p>
        @else
            <div class="product-grid">
                @foreach ($products as $index => $product)
                    @include('partials.product-card', ['product' => $product, 'lazy' => $index > 1])
                @endforeach
            </div>

            @include('partials.pager', ['paginator' => $products])
        @endif
    </div>
@endsection
