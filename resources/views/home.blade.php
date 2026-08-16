@extends('layouts.app')

@section('title', config('app.name'))
@section('meta_description', __('store.meta_home'))
@section('canonical', localized_route('home'))

@section('content')
    <div class="container home">
        <section class="home-hero">
            <div class="home-hero-copy">
                <p class="home-kicker">{{ __('store.hero_kicker') }}</p>
                <h2 class="home-hero-title">{{ __('store.hero_title') }}</h2>
                <p class="home-hero-text">{{ __('store.hero_text') }}</p>
                <div class="home-hero-actions">
                    <a href="#{{ $featured->isNotEmpty() ? 'featured' : 'categories' }}" class="btn btn-primary">{{ __('store.hero_cta') }}</a>
                    <a href="#categories" class="btn btn-secondary">{{ __('store.home_browse') }}</a>
                </div>
            </div>
            <div class="home-hero-media">
                <img
                    src="{{ asset('images/hero.jpg') }}"
                    alt="{{ __('store.tagline') }}"
                    width="1600"
                    height="900"
                    fetchpriority="high"
                >
            </div>
        </section>

        <ul class="home-trust">
            <li class="home-trust-item">
                <span class="home-trust-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 7h11v10H3z"/>
                        <path d="M14 10h4l3 3v4h-7"/>
                        <circle cx="7" cy="18" r="1.6"/>
                        <circle cx="18" cy="18" r="1.6"/>
                    </svg>
                </span>
                <span class="home-trust-copy">
                    <strong>{{ __('store.home_trust_ship_title') }}</strong>
                    <span>{{ __('store.home_trust_ship_text') }}</span>
                </span>
            </li>
            <li class="home-trust-item">
                <span class="home-trust-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="6" width="18" height="12" rx="1.5"/>
                        <path d="M3 10h18"/>
                        <path d="M7 15h3"/>
                    </svg>
                </span>
                <span class="home-trust-copy">
                    <strong>{{ __('store.home_trust_pay_title') }}</strong>
                    <span>{{ __('store.home_trust_pay_text') }}</span>
                </span>
            </li>
            <li class="home-trust-item">
                <span class="home-trust-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 8.5 12 4l8 4.5v7L12 20l-8-4.5z"/>
                        <path d="M12 12v8"/>
                        <path d="M12 12 4.4 8.2"/>
                        <path d="m12 12 7.6-3.8"/>
                    </svg>
                </span>
                <span class="home-trust-copy">
                    <strong>{{ __('store.home_trust_pack_title') }}</strong>
                    <span>{{ __('store.home_trust_pack_text') }}</span>
                </span>
            </li>
            <li class="home-trust-item">
                <span class="home-trust-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 17v-2a4 4 0 0 1 4-4h1"/>
                        <path d="M20 17v-2a4 4 0 0 0-4-4h-1"/>
                        <circle cx="8.5" cy="8" r="2.2"/>
                        <circle cx="15.5" cy="8" r="2.2"/>
                    </svg>
                </span>
                <span class="home-trust-copy">
                    <strong>{{ __('store.home_trust_help_title') }}</strong>
                    <span>{{ __('store.home_trust_help_text') }}</span>
                </span>
            </li>
        </ul>

        <section class="home-section" id="categories" aria-labelledby="home-categories-title">
            <header class="home-section-header">
                <h2 class="section-title" id="home-categories-title">{{ __('store.shop_by_category') }}</h2>
            </header>
            <div class="home-cats">
                @foreach ($categories as $category)
                    @php $cover = $category->coverProduct; @endphp
                    <a href="{{ localized_route('categories.show', ['category' => $category->slug]) }}" class="home-cat">
                        @if ($cover)
                            <span class="home-cat-media">
                                <img
                                    src="{{ $cover->thumbnailUrl() }}"
                                    alt=""
                                    width="600"
                                    height="600"
                                    loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                                >
                            </span>
                        @endif
                        <span class="home-cat-body">
                            <span class="home-cat-name">{{ $category->localizedName() }}</span>
                            <span class="home-cat-meta">
                                <span class="home-cat-count">
                                    {{ trans_choice('store.products_count', $category->listingCount(), ['count' => $category->listingCount()]) }}
                                </span>
                                <span class="home-cat-go">{{ __('store.home_cat_go') }}</span>
                            </span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>

        @if ($featured->isNotEmpty())
            <section class="home-section" id="featured" aria-labelledby="home-featured-title">
                <header class="home-section-header">
                    <div>
                        <h2 class="section-title" id="home-featured-title">{{ __('store.featured') }}</h2>
                        <p class="section-lede">{{ __('store.featured_lede') }}</p>
                    </div>
                </header>
                <div class="product-grid">
                    @foreach ($featured as $product)
                        @include('partials.product-card', ['product' => $product, 'lazy' => true])
                    @endforeach
                </div>
            </section>
        @endif

        @if ($more->isNotEmpty())
            <section class="home-section" id="more" aria-labelledby="home-more-title">
                <header class="home-section-header">
                    <div>
                        <h2 class="section-title" id="home-more-title">{{ __('store.home_more') }}</h2>
                        <p class="section-lede">{{ __('store.home_more_lede') }}</p>
                    </div>
                </header>
                <div class="product-grid">
                    @foreach ($more as $product)
                        @include('partials.product-card', ['product' => $product])
                    @endforeach
                </div>
            </section>
        @endif

        <section class="home-seo" id="a-propos" aria-labelledby="home-seo-title">
            <h2 class="home-seo-title" id="home-seo-title">{{ __('store.home_seo_title') }}</h2>
            <div class="home-seo-copy">
                <p>{{ __('store.home_seo_p1') }}</p>
                <p>{{ __('store.home_seo_p2') }}</p>
            </div>

            @if ($categories->isNotEmpty())
                <h3 class="home-seo-cats-title">{{ __('store.home_seo_cats_title') }}</h3>
                <div class="home-seo-cats">
                    @foreach ($categories as $category)
                        <article class="home-seo-cat">
                            <h4>
                                <a href="{{ localized_route('categories.show', ['category' => $category->slug]) }}">
                                    {{ $category->localizedName() }}
                                </a>
                            </h4>
                            @if ($category->localizedDescription() !== '')
                                <p>{{ $category->localizedDescription() }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection

@push('head')
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('app.name'),
            'url' => localized_route('home'),
            'inLanguage' => 'fr-FR',
            'description' => __('store.meta_home'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush
