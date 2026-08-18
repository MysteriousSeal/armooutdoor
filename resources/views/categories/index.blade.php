@extends('layouts.app')

@section('title', 'Toutes les catégories — '.config('app.name'))
@section('meta_description', 'Toutes les catégories de la boutique Armo Outdoor : cibles, stand de tir, vêtements, terrain, quotidien et munitions.')
@section('canonical', route('categories.index'))

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
                    'name' => 'Toutes les catégories',
                    'item' => route('categories.index'),
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'CollectionPage',
            'name' => 'Toutes les catégories',
            'description' => 'Toutes les catégories de la boutique Armo Outdoor : cibles, stand de tir, vêtements, terrain, quotidien et munitions.',
            'url' => route('categories.index'),
            'inLanguage' => 'fr-FR',
            'mainEntity' => [
                '@@type' => 'ItemList',
                'name' => 'Toutes les catégories',
                'numberOfItems' => $categories->count(),
                'itemListElement' => $categories->values()->map(fn ($category, $index) => [
                    '@@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $category->localizedName(),
                    'url' => localized_route('categories.show', ['category' => $category->slug]),
                ])->all(),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('content')
    <div class="container cat-index">
        <nav class="breadcrumbs" aria-label="breadcrumb">
            <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <span>Toutes les catégories</span>
        </nav>

        <header class="page-header">
            <p class="home-kicker">{{ __('store.hero_kicker') }}</p>
            <h1 class="page-title">Toutes les catégories</h1>
            <p class="page-lede">Parcourez l'ensemble du catalogue par catégorie.</p>
        </header>

        @php
            $categoryIconNames = [
                'targets' => 'bullseye',
                'range' => 'toolbox',
                'apparel' => 'shirt',
                'field-gear' => 'campground',
                'everyday' => 'screwdriver-wrench',
                'munitions' => 'box-open',
            ];
        @endphp

        <div class="cat-index-grid">
            @foreach ($categories as $category)
                @php
                    $blurbKey = 'store.home_cat_blurb_'.str_replace('-', '_', $category->slug);
                    $blurb = trans()->has($blurbKey)
                        ? __($blurbKey)
                        : ($category->localizedDescription() !== ''
                            ? $category->localizedDescription()
                            : trans_choice('store.products_count', $category->listingCount(), ['count' => $category->listingCount()]));
                    $visibleChildren = $category->children->filter(fn ($child) => $child->products->isNotEmpty());
                    $count = $category->listingCount();
                @endphp
                <article class="cat-index-card">
                    <a href="{{ localized_route('categories.show', ['category' => $category->slug]) }}" class="cat-index-head">
                        <span class="cat-index-icon" aria-hidden="true">
                            @include('partials.icon', ['name' => $categoryIconNames[$category->slug] ?? 'default', 'size' => 28])
                        </span>
                        <span class="cat-index-copy">
                            <h2 class="cat-index-name">{{ $category->localizedName() }}</h2>
                            <p class="cat-index-desc">{{ $blurb }}</p>
                            <span class="cat-index-count">
                                {{ trans_choice('store.products_count', $count, ['count' => $count]) }}
                            </span>
                        </span>
                    </a>
                    @if ($visibleChildren->isNotEmpty())
                        <ul class="cat-index-subs">
                            @foreach ($visibleChildren as $child)
                                <li>
                                    <a href="{{ localized_route('categories.show', ['category' => $child->slug]) }}">
                                        <span class="cat-index-sub-name">{{ $child->localizedName() }}</span>
                                        <span class="cat-index-sub-count">
                                            {{ trans_choice('store.products_count', $child->products->count(), ['count' => $child->products->count()]) }}
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </article>
            @endforeach
        </div>
    </div>
@endsection
