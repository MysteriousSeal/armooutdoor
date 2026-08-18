@extends('layouts.app')

@section('title', __('store.footer_sitemap').' — '.config('app.name'))
@section('meta_description', __('store.sitemap_lede'))
@section('canonical', route('sitemap.html'))

@section('content')
    <div class="container sitemap-wrap">
        <nav class="breadcrumbs" aria-label="breadcrumb">
            <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <span>{{ __('store.footer_sitemap') }}</span>
        </nav>

        <header class="page-header">
            <p class="home-kicker">{{ __('store.sitemap_kicker') }}</p>
            <h1 class="page-title">{{ __('store.footer_sitemap') }}</h1>
            <p class="page-lede">{{ __('store.sitemap_lede') }}</p>
        </header>

        <nav class="sitemap-panel" aria-labelledby="sitemap-pages-heading">
            <h2 class="sitemap-heading" id="sitemap-pages-heading">{{ __('store.sitemap_pages') }}</h2>
            <ul class="sitemap-pages">
                <li><a href="{{ localized_route('home') }}">{{ __('store.nav_home') }}</a></li>
                <li><a href="{{ route('categories.index') }}">Toutes les catégories</a></li>
                <li><a href="{{ route('products.new-arrivals') }}">Nouveautés</a></li>
                <li><a href="{{ route('faq') }}">FAQ</a></li>
                <li><a href="{{ route('help.shipping-returns') }}">Livraison & Retours</a></li>
                <li><a href="{{ route('help.secure-payment') }}">Paiement sécurisé</a></li>
                <li><a href="{{ route('legal.terms') }}">{{ __('store.legal_terms_title') }}</a></li>
                <li><a href="{{ route('legal.notice') }}">{{ __('store.legal_notice_title') }}</a></li>
                <li><a href="{{ route('legal.privacy') }}">{{ __('store.legal_privacy_title') }}</a></li>
                <li><a href="{{ route('legal.withdrawal') }}">{{ __('store.legal_withdrawal_title') }}</a></li>
            </ul>
        </nav>

        <section class="sitemap-section" aria-labelledby="sitemap-categories-heading">
            <h2 class="sitemap-heading" id="sitemap-categories-heading">{{ __('store.footer_shop') }}</h2>
            <div class="sitemap-cats">
                @foreach ($categories as $category)
                    <nav class="sitemap-card" aria-labelledby="sitemap-cat-{{ $category->id }}">
                        <h3 class="sitemap-cat-name" id="sitemap-cat-{{ $category->id }}">
                            <a href="{{ localized_route('categories.show', ['category' => $category->slug]) }}">
                                {{ $category->localizedName() }}
                            </a>
                        </h3>
                        @if ($category->children->isNotEmpty())
                            <ul class="sitemap-links">
                                @foreach ($category->children as $child)
                                    <li>
                                        <a href="{{ localized_route('categories.show', ['category' => $child->slug]) }}">
                                            {{ $child->localizedName() }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </nav>
                @endforeach
            </div>
        </section>

        <section class="sitemap-section" aria-labelledby="sitemap-products-heading">
            <h2 class="sitemap-heading" id="sitemap-products-heading">{{ __('store.sitemap_products') }}</h2>
            <div class="sitemap-groups">
                @foreach ($categories as $category)
                    @php
                        $categoryProducts = collect([$category->id])
                            ->concat($category->children->pluck('id'))
                            ->flatMap(fn ($id) => $products->get($id, collect()));
                    @endphp
                    @if ($categoryProducts->isNotEmpty())
                        <nav class="sitemap-card" aria-labelledby="sitemap-group-{{ $category->id }}">
                            <h3 class="sitemap-cat-name" id="sitemap-group-{{ $category->id }}">
                                <a href="{{ localized_route('categories.show', ['category' => $category->slug]) }}">
                                    {{ $category->localizedName() }}
                                </a>
                            </h3>
                            <ul class="sitemap-links">
                                @foreach ($categoryProducts as $product)
                                    <li>
                                        <a href="{{ localized_route('products.show', ['product' => $product->slug]) }}">
                                            {{ $product->localizedName() }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </nav>
                    @endif
                @endforeach
            </div>
        </section>

        <aside class="sitemap-xml">
            <div class="sitemap-xml-copy">
                <p class="sitemap-xml-title">{{ __('store.sitemap_xml') }}</p>
                <p class="sitemap-xml-text">{{ __('store.sitemap_xml_text') }}</p>
            </div>
            <a href="{{ route('sitemap.index') }}" class="sitemap-xml-link">sitemap.xml</a>
        </aside>
    </div>
@endsection

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/sitemap.css') }}">
@endpush
