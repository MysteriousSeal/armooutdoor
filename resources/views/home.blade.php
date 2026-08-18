@extends('layouts.app')

@section('title', config('app.name'))
@section('meta_description', __('store.meta_home'))
@section('canonical', localized_route('home'))

@section('content')
    @php
        $shopUrl = $firstCategory
            ? localized_route('categories.show', ['category' => $firstCategory->slug])
            : localized_route('search');
    @endphp

    <div class="container home">
        <section class="home-hero" aria-labelledby="home-hero-title" style="--hero-image: url('{{ asset('images/hero.jpg') }}')">
            <div class="home-hero-overlay" aria-hidden="true"></div>
            <div class="home-hero-copy">
                <p class="home-hero-kicker">{{ __('store.home_hero_kicker') }}</p>
                <h2 class="home-hero-title" id="home-hero-title">
                    <span class="home-hero-title-line">Équipez-vous</span>
                    <span class="home-hero-title-line home-hero-title-accent">pour le stand</span>
                    <span class="home-hero-title-line">et le terrain</span>
                </h2>
                <p class="home-hero-text">
                    Équipement sélectionné pour le tir sportif, la chasse, l’airgun et l’aventure en plein air.
                </p>
                <ul class="home-hero-tags" aria-label="{{ __('store.home_hero_tags_label') }}">
                    <li>{{ __('store.home_hero_tag_range') }}</li>
                    <li>{{ __('store.home_hero_tag_hunt') }}</li>
                    <li>{{ __('store.home_hero_tag_outdoor') }}</li>
                </ul>
                <div class="home-hero-actions">
                    <a href="{{ $shopUrl }}" class="btn btn-primary">{{ __('store.hero_cta') }}</a>
                    <a href="{{ $shopUrl }}" class="btn home-hero-ghost">{{ __('store.home_browse') }}</a>
                </div>
            </div>
        </section>

        <ul class="home-trust">
            <li class="home-trust-item">
                <span class="home-trust-icon" aria-hidden="true">
                    @include('partials.icon', ['name' => 'truck-fast', 'size' => 19])
                </span>
                <span class="home-trust-copy">
                    <strong>{{ __('store.home_hero_ship_title') }}</strong>
                    <span>
                        @if ($freeShippingAmount)
                            {{ __('store.home_hero_ship_from', ['amount' => $freeShippingAmount]) }}
                        @else
                            {{ __('store.home_hero_ship_plain') }}
                        @endif
                    </span>
                </span>
            </li>
            <li class="home-trust-item">
                <span class="home-trust-icon" aria-hidden="true">
                    @include('partials.icon', ['name' => 'shield-halved', 'size' => 19])
                </span>
                <span class="home-trust-copy">
                    <strong>{{ __('store.home_hero_pay_title') }}</strong>
                    <span>{{ __('store.home_hero_pay_text') }}</span>
                </span>
            </li>
            <li class="home-trust-item">
                <span class="home-trust-icon" aria-hidden="true">
                    @include('partials.icon', ['name' => 'box', 'size' => 19])
                </span>
                <span class="home-trust-copy">
                    <strong>{{ __('store.home_hero_track_title') }}</strong>
                    <span>{{ __('store.home_hero_track_text') }}</span>
                </span>
            </li>
            <li class="home-trust-item">
                <span class="home-trust-icon" aria-hidden="true">
                    @include('partials.icon', ['name' => 'headset', 'size' => 19])
                </span>
                <span class="home-trust-copy">
                    <strong>{{ __('store.home_hero_help_title') }}</strong>
                    <span>{{ __('store.home_hero_help_text') }}</span>
                </span>
            </li>
        </ul>

        @if ($categories->isNotEmpty())
            <section class="home-cats-section" id="categories" aria-labelledby="home-categories-title">
                <header class="home-cats-header">
                    <h2 class="home-cats-title" id="home-categories-title">{{ __('store.shop_by_category') }}</h2>
                    <a href="{{ route('categories.index') }}" class="home-cats-link">
                        {{ __('store.see_all_categories') }} <span aria-hidden="true">→</span>
                    </a>
                </header>
                <div class="home-cats">
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
                    @foreach ($categories as $category)
                        @php
                            $blurbKey = 'store.home_cat_blurb_'.str_replace('-', '_', $category->slug);
                            $blurb = trans()->has($blurbKey)
                                ? __($blurbKey)
                                : ($category->localizedDescription() !== ''
                                    ? $category->localizedDescription()
                                    : trans_choice('store.products_count', $category->listingCount(), ['count' => $category->listingCount()]));
                        @endphp
                        <a href="{{ localized_route('categories.show', ['category' => $category->slug]) }}" class="home-cat">
                            <span class="home-cat-icon">
                                @include('partials.icon', ['name' => $categoryIconNames[$category->slug] ?? 'default', 'size' => 30])
                            </span>
                            <span class="home-cat-copy">
                                <span class="home-cat-name">{{ $category->localizedName() }}</span>
                                <span class="home-cat-desc">{{ $blurb }}</span>
                            </span>
                            <span class="home-cat-arrow" aria-hidden="true">→</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($featured->isNotEmpty())
            <section class="home-featured" id="featured" aria-labelledby="home-featured-title">
                <header class="home-cats-header">
                    <h2 class="home-cats-title" id="home-featured-title">{{ __('store.featured') }}</h2>
                    <a href="{{ $shopUrl }}" class="home-cats-link">
                        {{ __('store.see_all_products') }} <span aria-hidden="true">→</span>
                    </a>
                </header>
                <div class="product-grid product-grid--five">
                    @foreach ($featured as $product)
                        @include('partials.product-card', ['product' => $product, 'lazy' => $loop->index > 1])
                    @endforeach
                </div>
            </section>
        @endif

        <aside class="home-ship-banner">
            <span class="home-ship-banner-icon" aria-hidden="true">
                @include('partials.icon', ['name' => 'truck-fast', 'size' => 28])
            </span>
            <div class="home-ship-banner-copy">
                <p class="home-ship-banner-title">
                    @if ($freeShippingAmount)
                        {{ __('store.home_ship_banner_title', ['amount' => $freeShippingAmount]) }}
                    @else
                        {{ __('store.home_ship_banner_title_plain') }}
                    @endif
                </p>
                <p class="home-ship-banner-text">{{ __('store.home_ship_banner_text') }}</p>
            </div>
            <a href="{{ route('legal.withdrawal') }}" class="btn btn-primary home-ship-banner-cta">
                {{ __('store.home_ship_banner_cta') }}
            </a>
        </aside>

        @if ($more->isNotEmpty())
            <section class="home-more" id="more" aria-labelledby="home-more-title">
                <header class="home-cats-header">
                    <h2 class="home-cats-title" id="home-more-title">{{ __('store.home_more') }}</h2>
                    <a href="{{ $shopUrl }}" class="home-cats-link">
                        {{ __('store.see_all_products') }} <span aria-hidden="true">→</span>
                    </a>
                </header>
                <div class="product-grid product-grid--five">
                    @foreach ($more as $product)
                        @include('partials.product-card', ['product' => $product, 'lazy' => true])
                    @endforeach
                </div>
            </section>
        @endif

        <section class="home-why" aria-labelledby="home-why-title">
            <header class="home-why-header">
                <p class="home-why-kicker">{{ __('store.home_why_kicker') }}</p>
                <h2 class="home-why-title" id="home-why-title">{{ __('store.home_why_title') }}</h2>
                <p class="home-why-text">{{ __('store.home_why_text') }}</p>
            </header>
            <ul class="home-why-list">
                <li class="home-why-item">
                    <span class="home-why-icon" aria-hidden="true">
                        @include('partials.icon', ['name' => 'award', 'size' => 22])
                    </span>
                    <span class="home-why-index" aria-hidden="true">01</span>
                    <span class="home-why-copy">
                        <strong>{{ __('store.home_why_useful_title') }}</strong>
                        <span>{{ __('store.home_why_useful_text') }}</span>
                    </span>
                </li>
                <li class="home-why-item">
                    <span class="home-why-icon" aria-hidden="true">
                        @include('partials.icon', ['name' => 'tag', 'size' => 22])
                    </span>
                    <span class="home-why-index" aria-hidden="true">02</span>
                    <span class="home-why-copy">
                        <strong>{{ __('store.home_why_price_title') }}</strong>
                        <span>{{ __('store.home_why_price_text') }}</span>
                    </span>
                </li>
                <li class="home-why-item">
                    <span class="home-why-icon" aria-hidden="true">
                        @include('partials.icon', ['name' => 'truck-fast', 'size' => 22])
                    </span>
                    <span class="home-why-index" aria-hidden="true">03</span>
                    <span class="home-why-copy">
                        <strong>{{ __('store.home_why_ship_title') }}</strong>
                        <span>{{ __('store.home_why_ship_text') }}</span>
                    </span>
                </li>
                <li class="home-why-item">
                    <span class="home-why-icon" aria-hidden="true">
                        @include('partials.icon', ['name' => 'headset', 'size' => 22])
                    </span>
                    <span class="home-why-index" aria-hidden="true">04</span>
                    <span class="home-why-copy">
                        <strong>{{ __('store.home_why_support_title') }}</strong>
                        <span>{{ __('store.home_why_support_text') }}</span>
                    </span>
                </li>
            </ul>
        </section>

        <section class="home-about" aria-labelledby="home-about-title">
            <div class="home-about-media">
                <img
                    src="{{ asset('images/about.jpg') }}"
                    alt=""
                    width="1280"
                    height="720"
                    loading="lazy"
                >
            </div>
            <div class="home-about-copy">
                <p class="home-about-kicker">{{ __('store.home_about_kicker') }}</p>
                <h2 class="home-about-title" id="home-about-title">{{ __('store.home_about_heading') }}</h2>
                <p class="home-about-lead">{{ __('store.home_about_lead') }}</p>
                <ul class="home-about-points">
                    <li>
                        <span class="home-about-point-mark" aria-hidden="true">
                            @include('partials.icon', ['name' => 'circle-check', 'size' => 16])
                        </span>
                        <span>{{ __('store.home_about_quality') }}</span>
                    </li>
                    <li>
                        <span class="home-about-point-mark" aria-hidden="true">
                            @include('partials.icon', ['name' => 'circle-check', 'size' => 16])
                        </span>
                        <span>{{ __('store.home_about_goal') }}</span>
                    </li>
                </ul>
                <a href="{{ $shopUrl }}" class="btn btn-primary home-about-cta">
                    {{ __('store.home_about_cta') }}
                </a>
            </div>
        </section>
    </div>
@endsection

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/home.css') }}">
    <script type="application/ld+json">
        {{-- The key is written @@context so Blade emits a literal "@context":
             left bare, Blade compiles it as its own @context directive and the
             key is replaced by PHP, leaving the JSON-LD without @context. --}}
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('app.name'),
            'url' => localized_route('home'),
            'inLanguage' => 'fr-FR',
            'description' => __('store.meta_home'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush
