@extends('layouts.app')

@section('title', __('store.meta_title_home'))
@section('meta_description', __('store.meta_home'))
@section('canonical', localized_route('home'))

@if ($freeShippingAmount)
    @push('topbar')
        {{-- Only rendered when there is a real figure to name: a promise of
             free shipping with no threshold behind it is worse than silence. --}}
        <aside class="ship-strip" aria-label="{{ __('store.home_ship_banner_title', ['amount' => $freeShippingAmount]) }}">
            <p class="ship-strip-inner">
                {{-- A parcel drawn in line rather than the catalogue's
                     filled silhouette: at this size a solid shape becomes a
                     blot, and the lines of travel say "on its way" where the
                     truck only said "truck". --}}
                <svg class="ship-strip-icon" viewBox="0 0 24 24" width="28" height="28" aria-hidden="true" focusable="false">
                    <path d="M1.6 9.4h3.6M0.8 12.6h3.2M2.4 15.8h2.8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity=".65"/>
                    <path d="M8.4 8.2 14.6 5l6.2 3.2v7.6L14.6 19l-6.2-3.2V8.2Z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                    <path d="m8.4 8.2 6.2 3.2 6.2-3.2M14.6 11.4V19" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                </svg>
                <span class="ship-strip-copy">{!! __('store.home_ship_strip', ['amount' => '<b class="ship-strip-amount">'.e($freeShippingAmount).'</b>']) !!}</span>
                <span class="ship-strip-note">{{ __('store.home_ship_strip_note') }}</span>
            </p>
        </aside>
    @endpush
@endif

@section('content')
    @php
        // « Voir tous les produits » goes to the page that actually holds
        // them all — not, as before, to whichever category happens first.
        $shopUrl = localized_route('products.all');
    @endphp

    <div class="container home">
        @php
            // Trois panneaux figés : le premier reprend le hero d'origine, les
            // deux autres pointent vers les rayons qui bougent le plus.
            $slides = [
                [
                    'image' => versioned_asset('images/hero.webp'),
                    'focus' => '78%',
                    'kicker' => __('store.home_hero_kicker'),
                    // The h1 of the whole site: it names the aisles rather
                    // than shouting an imperative, because it is the strongest
                    // on-page signal there is.
                    'lines' => ['Cibles, entretien', 'et équipement', 'pour le stand et le terrain'],
                    'accent' => 1,
                    'text' => 'Équipement sélectionné pour le tir sportif, la chasse, l’airgun et l’aventure en plein air.',
                    'tags' => [__('store.home_hero_tag_range'), __('store.home_hero_tag_hunt'), __('store.home_hero_tag_outdoor')],
                    'cta' => ['label' => __('store.hero_cta'), 'url' => $shopUrl],
                    'ghost' => ['label' => __('store.home_browse'), 'url' => localized_route('products.new-arrivals')],
                ],
                [
                    'image' => versioned_asset('images/hero-2.webp'),
                    'focus' => '28%',
                    'kicker' => __('store.home_slide_new_kicker'),
                    'lines' => ['Les dernières', 'nouveautés', 'en rayon'],
                    'accent' => 1,
                    'text' => __('store.home_slide_new_text'),
                    'tags' => [],
                    'cta' => ['label' => __('store.home_slide_new_cta'), 'url' => localized_route('products.new-arrivals')],
                    'ghost' => null,
                ],
                [
                    'image' => versioned_asset('images/hero-3.webp'),
                    'focus' => '75%',
                    'kicker' => __('store.home_slide_sale_kicker'),
                    'lines' => ['Des prix', 'en baisse', 'cette semaine'],
                    'accent' => 1,
                    'text' => __('store.home_slide_sale_text'),
                    'tags' => [],
                    'cta' => ['label' => __('store.home_slide_sale_cta'), 'url' => localized_route('products.promotions')],
                    'ghost' => null,
                ],
                [
                    'image' => versioned_asset('images/hero-4.webp'),
                    'focus' => '28%',
                    'kicker' => __('store.home_slide_best_kicker'),
                    'lines' => ['Ce que les', 'tireurs', 'achètent le plus'],
                    'accent' => 1,
                    'text' => __('store.home_slide_best_text'),
                    'tags' => [],
                    'cta' => ['label' => __('store.home_slide_best_cta'), 'url' => localized_route('products.best-sellers')],
                    'ghost' => null,
                ],
            ];
        @endphp

        {{-- data-carousel est le point d'accroche du script. Sans JavaScript
             la piste reste une liste de panneaux que l'on fait défiler au
             doigt : rien ne disparaît. --}}
        <section
            class="home-carousel"
            aria-roledescription="carousel"
            aria-label="{{ __('store.home_carousel_label') }}"
            data-carousel
            data-carousel-interval="6000"
        >
            <div class="home-carousel-viewport">
                <div class="home-carousel-track" data-carousel-track>
                    @foreach ($slides as $index => $slide)
                        <article
                            class="home-hero home-carousel-panel {{ $index % 2 === 1 ? 'home-hero--mirrored' : '' }}"
                            style="--hero-image: url('{{ $slide['image'] }}'); --hero-focus: {{ $slide['focus'] }}"
                            role="group"
                            aria-roledescription="{{ __('store.home_carousel_slide') }}"
                            aria-label="{{ $index + 1 }} / {{ count($slides) }}"
                            @if ($index > 0) aria-hidden="true" @endif
                            data-carousel-panel
                        >
                            <div class="home-hero-overlay" aria-hidden="true"></div>
                            <div class="home-hero-copy">
                                <p class="home-hero-kicker">{{ $slide['kicker'] }}</p>
                                @php
                                    // Only the leading panel: four h1 elements
                                    // would be no better than none, and the
                                    // panels behind it are aria-hidden anyway.
                                    $headingTag = $index === 0 ? 'h1' : 'h2';
                                @endphp
                                <{{ $headingTag }} class="home-hero-title" @if ($index === 0) id="home-hero-title" @endif>
                                    @foreach ($slide['lines'] as $line => $text)
                                        <span class="home-hero-title-line {{ $line === $slide['accent'] ? 'home-hero-title-accent' : '' }}">{{ $text }}</span>
                                    @endforeach
                                </{{ $headingTag }}>
                                <p class="home-hero-text">{{ $slide['text'] }}</p>
                                @if ($slide['tags'])
                                    <ul class="home-hero-tags" aria-label="{{ __('store.home_hero_tags_label') }}">
                                        @foreach ($slide['tags'] as $tag)
                                            <li>{{ $tag }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                                <div class="home-hero-actions">
                                    <a href="{{ $slide['cta']['url'] }}" class="btn btn-primary">{{ $slide['cta']['label'] }}</a>
                                    @if ($slide['ghost'])
                                        <a href="{{ $slide['ghost']['url'] }}" class="btn home-hero-ghost">{{ $slide['ghost']['label'] }}</a>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>

            <button type="button" class="home-carousel-arrow home-carousel-arrow--prev" data-carousel-prev aria-label="{{ __('store.home_carousel_prev') }}" hidden>
                <span aria-hidden="true">‹</span>
            </button>
            <button type="button" class="home-carousel-arrow home-carousel-arrow--next" data-carousel-next aria-label="{{ __('store.home_carousel_next') }}" hidden>
                <span aria-hidden="true">›</span>
            </button>

            <div class="home-carousel-dots" role="tablist" aria-label="{{ __('store.home_carousel_label') }}" data-carousel-dots hidden>
                @foreach ($slides as $index => $slide)
                    <button
                        type="button"
                        class="home-carousel-dot {{ $index === 0 ? 'is-current' : '' }}"
                        role="tab"
                        aria-selected="{{ $index === 0 ? 'true' : 'false' }}"
                        aria-label="{{ __('store.home_carousel_goto', ['number' => $index + 1]) }}"
                        data-carousel-dot="{{ $index }}"
                    ></button>
                @endforeach
            </div>
        </section>

        <ul class="home-trust">
            <li class="home-trust-item">
                <span class="home-trust-icon" aria-hidden="true">
                    @include('partials.icon', ['name' => 'truck-fast', 'size' => 22])
                </span>
                <span class="home-trust-copy">
                    <strong>{{ __('store.home_hero_ship_title') }}</strong>
                    <span>
                        @if ($freeShippingAmount)
                            {{-- The threshold carries the emphasis, the way it
                                 does in the strip at the top of the page: it is
                                 the one figure of the four a shopper is
                                 counting on. --}}
                            {!! __('store.home_hero_ship_from', ['amount' => '<b class="home-trust-amount">'.e($freeShippingAmount).'</b>']) !!}
                        @else
                            {{ __('store.home_hero_ship_plain') }}
                        @endif
                    </span>
                </span>
            </li>
            <li class="home-trust-item">
                <span class="home-trust-icon" aria-hidden="true">
                    @include('partials.icon', ['name' => 'shield-halved', 'size' => 22])
                </span>
                <span class="home-trust-copy">
                    <strong>{{ __('store.home_hero_pay_title') }}</strong>
                    <span>{{ __('store.home_hero_pay_text') }}</span>
                </span>
            </li>
            <li class="home-trust-item">
                <span class="home-trust-icon" aria-hidden="true">
                    @include('partials.icon', ['name' => 'box', 'size' => 22])
                </span>
                <span class="home-trust-copy">
                    <strong>{{ __('store.home_hero_track_title') }}</strong>
                    <span>{{ __('store.home_hero_track_text') }}</span>
                </span>
            </li>
            <li class="home-trust-item">
                <span class="home-trust-icon" aria-hidden="true">
                    @include('partials.icon', ['name' => 'envelope', 'size' => 22])
                </span>
                <span class="home-trust-copy">
                    <strong>{{ __('store.home_hero_help_title') }}</strong>
                    <span>{{ __('store.home_hero_help_text') }}</span>
                </span>
            </li>
        </ul>

        @if ($onSale->isNotEmpty())
            {{-- Between the promises and the aisles: a reason to stop, before
                 being asked where to go. A row rather than a grid, so the
                 categories stay within reach — and nothing at all when nothing
                 is reduced, an offers heading over an empty row being worse
                 than no heading. --}}
            <section class="home-deals" id="deals" aria-labelledby="home-deals-title">
                <header class="home-cats-header">
                    <div class="home-cats-heading">
                        <p class="home-cats-kicker">{{ __('store.home_kicker_deals') }}</p>
                        <h2 class="home-cats-title" id="home-deals-title">{{ __('store.home_deals') }}</h2>
                    </div>
                    <a href="{{ route('products.promotions') }}" class="home-cats-link">
                        {{ __('store.home_deals_link') }} <span aria-hidden="true">→</span>
                    </a>
                </header>
                {{-- aria-live off: the row moves on its own, and announcing each
                     turn would talk over whatever else is being read. --}}
                <ul
                    class="home-deals-row"
                    tabindex="0"
                    aria-label="{{ __('store.home_deals') }}"
                    aria-live="off"
                    data-deals-row
                >
                    @foreach ($onSale as $product)
                        <li class="home-deals-item">
                            {{-- Five across, like the two grids below, so the stock
                                 chip takes the short labels they take: the long
                                 ones crowd a card this narrow. --}}
                            @include('partials.product-card', ['product' => $product, 'lazy' => $loop->index > 2, 'fiveColumn' => true])
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($catalogue)
            {{-- How much there is. The figure is the whole design: three
                 lines locked together so it reads as one sentence, with the
                 way into the catalogue sitting beside it. --}}
            <section class="home-stock" aria-labelledby="home-stock-figure">
                <div class="home-stock-stats">
                    <p class="home-stock-kicker">{{ __('store.home_stock_kicker') }}</p>
                    <p class="home-stock-figure" id="home-stock-figure">
                        <span class="home-stock-more">{{ __('store.home_stock_more') }}</span>
                        <span class="home-stock-count">{{ $catalogue['rounded'] }}</span>
                        <span class="home-stock-unit">{{ __('store.home_stock_unit') }}</span>
                    </p>
                    {{-- What the figure is spread across. Two smaller counts, so
                         the shelves are read as depth rather than as a second
                         menu: the aisles themselves are the next section. --}}
                    <dl class="home-stock-spread">
                        <div class="home-stock-split">
                            <dt class="home-stock-split-count">{{ $catalogue['rayons'] }}</dt>
                            <dd class="home-stock-split-label">{{ trans_choice('store.home_stock_rayons', $catalogue['rayons']) }}</dd>
                        </div>
                        <div class="home-stock-split">
                            <dt class="home-stock-split-count">{{ $catalogue['categories'] }}</dt>
                            <dd class="home-stock-split-label">{{ trans_choice('store.home_stock_categories', $catalogue['categories']) }}</dd>
                        </div>
                    </dl>
                </div>
                <div class="home-stock-aside">
                    {{-- Raw so the sentence can weight its own second half:
                         the range is the fact, where it ships from is the
                         promise, and they are not read with the same eye. --}}
                    <p class="home-stock-text">{!! __('store.home_stock_text') !!}</p>
                    <a class="home-stock-link" href="{{ localized_route('products.all') }}">
                        {{ __('store.home_stock_link') }}
                        <span class="home-stock-arrow" aria-hidden="true">&rarr;</span>
                    </a>
                </div>
            </section>
        @endif

        @if ($categories->isNotEmpty())
            <section class="home-cats-section" id="categories" aria-labelledby="home-categories-title">
                <header class="home-cats-header">
                    <div class="home-cats-heading">
                        <p class="home-cats-kicker">{{ __('store.home_kicker_categories') }}</p>
                        <h2 class="home-cats-title" id="home-categories-title">{{ __('store.shop_by_category') }}</h2>
                    </div>
                    <a href="{{ route('categories.index') }}" class="home-cats-link">
                        {{ __('store.see_all_categories') }} <span aria-hidden="true">→</span>
                    </a>
                </header>
                <div class="home-cats">
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
                                @include('partials.icon', ['name' => $category->iconName(), 'size' => 30])
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
                    <div class="home-cats-heading">
                        <p class="home-cats-kicker">{{ __('store.home_kicker_featured') }}</p>
                        <h2 class="home-cats-title" id="home-featured-title">{{ __('store.featured') }}</h2>
                    </div>
                    <a href="{{ $shopUrl }}" class="home-cats-link">
                        {{ __('store.see_all_products') }} <span aria-hidden="true">→</span>
                    </a>
                </header>
                <div class="product-grid product-grid--five">
                    @foreach ($featured as $product)
                        @include('partials.product-card', ['product' => $product, 'lazy' => $loop->index > 1, 'fiveColumn' => true])
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
                    <div class="home-cats-heading">
                        <p class="home-cats-kicker">{{ __('store.home_kicker_more') }}</p>
                        <h2 class="home-cats-title" id="home-more-title">{{ __('store.home_more') }}</h2>
                    </div>
                    <a href="{{ $shopUrl }}" class="home-cats-link">
                        {{ __('store.see_all_products') }} <span aria-hidden="true">→</span>
                    </a>
                </header>
                <div class="product-grid product-grid--five">
                    @foreach ($more as $product)
                        @include('partials.product-card', ['product' => $product, 'lazy' => true, 'fiveColumn' => true])
                    @endforeach
                </div>
            </section>
        @endif

        @if ($testimonials->isNotEmpty())
            {{-- What customers said, each still pointing at the product it
                 judged: a testimonial nobody can trace reads as invented. --}}
            <section class="home-voices" aria-labelledby="home-voices-title">
                <header class="home-cats-header">
                    <div class="home-cats-heading">
                        <p class="home-cats-kicker">{{ __('store.home_voices_kicker') }}</p>
                        <h2 class="home-cats-title" id="home-voices-title">{{ __('store.home_voices_title') }}</h2>
                    </div>
                    @if ($reviewSummary)
                        {{-- The shop's own score, at the far end of the rule.
                             Outside the heading on purpose: the outline should
                             read as the section's name and nothing else. The
                             stars are painted to the same rounded figure the
                             number shows, so the two cannot disagree. --}}
                        <p class="home-rating" style="--home-rating-fill: {{ $reviewSummary['fill'] }}%">
                            <span class="home-rating-stars" aria-hidden="true"></span>
                            <span class="home-rating-copy">
                                <span class="home-rating-score" aria-hidden="true">{{ number_format($reviewSummary['average'], 1, ',', '') }}</span>
                                <span class="home-rating-out" aria-hidden="true">/ 5</span>
                                <span class="home-rating-count" aria-hidden="true">{{ trans_choice('store.reviews_count', $reviewSummary['count'], ['count' => $reviewSummary['count']]) }}</span>
                            </span>
                            <span class="sr-only">{{ __('store.reviews_rating_summary', ['rating' => number_format($reviewSummary['average'], 1, ',', ''), 'count' => $reviewSummary['count']]) }}</span>
                        </p>
                    @endif
                </header>
                <ul class="home-voices-list">
                    @foreach ($testimonials as $review)
                        <li class="home-voice">
                            <span class="home-voice-stars" aria-hidden="true">
                                <span></span><span></span><span></span><span></span><span></span>
                            </span>
                            <span class="sr-only">{{ trans_choice('store.review_rating_value', 5, ['count' => 5]) }}</span>
                            <div class="home-voice-quote">
                                <p class="home-voice-body">{{ $review->comment }}</p>
                            </div>
                            <div class="home-voice-foot">
                                {{-- The reviewer's monogram, as the blog comments sign theirs. --}}
                                <span class="home-voice-avatar" aria-hidden="true">{{ $review->reviewerInitials() }}</span>
                                <span class="home-voice-byline">
                                    <span class="home-voice-author">{{ $review->reviewerName() }}</span>
                                    <a href="{{ localized_route('products.show', ['product' => $review->product->slug]) }}" class="home-voice-product">{{ $review->product->localizedName() }}</a>
                                </span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- The shop's own writing, linked from the page that gets the most
             visits rather than the footer alone. --}}
        <section class="home-readings" aria-labelledby="home-readings-title">
            <header class="home-cats-header">
                <p class="home-cats-kicker">{{ __('store.home_readings_kicker') }}</p>
                <h2 class="home-cats-title" id="home-readings-title">{{ __('store.home_readings_title') }}</h2>
                <a href="{{ route('guides.index') }}" class="home-cats-link">{{ __('store.home_readings_link') }}</a>
            </header>
            <div class="home-readings-grid">
                @foreach ($readings as $reading)
                    <a href="{{ $reading['url'] }}" class="home-reading">
                        <span class="home-reading-kicker">
                            <span class="home-reading-kind">{{ $reading['kind'] }}</span>
                            @if ($reading['topic'] !== null)
                                <span class="home-reading-topic">{{ $reading['topic'] }}</span>
                            @endif
                        </span>
                        <h3 class="home-reading-title">{{ $reading['title'] }}</h3>
                        <p class="home-reading-text">{{ \Illuminate\Support\Str::limit($reading['text'], 130) }}</p>
                        <span class="home-reading-more">{{ $reading['cta'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>

        @if ($marketplace->showsNaturabuyOnHome())
            {{-- The shop sells on NaturaBuy too, and the standing it has
                 there is worth saying here. The figures are typed in the back
                 office: nothing is read back from the marketplace. --}}
            <section class="home-market" aria-labelledby="home-market-title">
                <div class="home-market-copy">
                    <p class="home-market-kicker">{{ __('store.home_market_kicker') }}</p>
                    {{-- Not a heading: the home page's outline is the shop's own
                         sections, and a marketplace it competes with has no place
                         in it. The region keeps its accessible name all the same. --}}
                    <p class="home-market-title" id="home-market-title">
                        {{ __('store.home_market_title') }}@include('partials.naturabuy-logo')
                    </p>
                    <p class="home-market-text">{{ __('store.home_market_text') }}</p>
                </div>

                <div class="home-market-aside">
                    <p class="home-market-score">
                        <strong>{{ number_format($marketplace->naturabuyRating(), 1, ',', ' ') }}</strong>
                        <span class="home-market-out">/ 5</span>
                        <span class="home-market-count">{{ trans_choice('store.home_market_reviews', $marketplace->naturabuy_reviews, ['count' => number_format($marketplace->naturabuy_reviews, 0, ',', ' ')]) }}</span>
                    </p>

                    {{-- Above zero, not merely present: « plus de 0 articles
                         vendus » is a boast nobody wants to make. --}}
                    @if ($marketplace->naturabuy_sales > 0)
                        {{-- Two lines by construction rather than by luck: the
                             sentence is long enough to wrap, and where it wraps
                             should not be left to the column's width. --}}
                        <p class="home-market-sales">
                            <span>
                                {{ __('store.home_market_sales_before') }}
                                <strong>{{ number_format($marketplace->naturabuy_sales, 0, ',', ' ') }}</strong>
                                {{ __('store.home_market_sales_after') }}
                            </span>
                            <span>{{ __('store.home_market_sales_trust') }}</span>
                        </p>
                    @endif

                    {{-- A marketplace the shop competes with for its own product
                         names: the visitor may follow it, a crawler may not. --}}
                    <a
                        href="{{ $marketplace->naturabuy_url }}"
                        class="home-market-link"
                        target="_blank"
                        rel="nofollow noopener"
                    >{{ __('store.home_market_cta') }}</a>
                </div>
            </section>
        @endif

        <section class="home-why" aria-labelledby="home-why-title">
            <header class="home-why-header">
                <p class="home-why-kicker">{{ __('store.home_why_kicker') }}</p>
                <h2 class="home-why-title" id="home-why-title">{{ __('store.home_why_title') }}</h2>
                {{-- Raw so the sentence can weight its own second half: the
                     first says how the catalogue is built, the second is the
                     line worth remembering. --}}
                <p class="home-why-text">{!! __('store.home_why_text') !!}</p>
            </header>

            {{-- Four undertakings, written out rather than boxed, and
                 numbered the way the articles of an undertaking are: this is
                 the page saying what it holds itself to, and a statement is
                 set in clauses, not tiled. --}}
            <ul class="home-why-list">
                <li class="home-why-item">
                    <span class="home-why-index" aria-hidden="true">01</span>
                    <strong>{{ __('store.home_why_useful_title') }}</strong>
                    <span>{{ __('store.home_why_useful_text') }}</span>
                </li>
                <li class="home-why-item">
                    <span class="home-why-index" aria-hidden="true">02</span>
                    <strong>{{ __('store.home_why_price_title') }}</strong>
                    <span>{{ __('store.home_why_price_text') }}</span>
                </li>
                <li class="home-why-item">
                    <span class="home-why-index" aria-hidden="true">03</span>
                    <strong>{{ __('store.home_why_ship_title') }}</strong>
                    <span>{{ __('store.home_why_ship_text') }}</span>
                </li>
                <li class="home-why-item">
                    <span class="home-why-index" aria-hidden="true">04</span>
                    <strong>{{ __('store.home_why_support_title') }}</strong>
                    <span>{{ __('store.home_why_support_text') }}</span>
                </li>
            </ul>

            {{-- The seal. It carries the shop's name, which the heading has
                 already said, so it is read by nobody who cannot see it. Last
                 in reading order either way; the grid decides whether it is
                 stamped in the corner of the sheet or centred at its foot. --}}
            <p class="home-why-stamp" aria-hidden="true">
                <span class="home-why-stamp-name">{{ config('app.name') }}</span>
                <span class="home-why-stamp-place">{{ __('store.home_why_stamp_place') }}</span>
            </p>
        </section>

        <section class="home-about" aria-labelledby="home-about-title">
            <div class="home-about-media">
                <img
                    src="{{ versioned_asset('images/about.webp') }}"
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
                {{-- A target rather than a tick: the tick said "valid", the
                     way a form that has accepted you does, where these lines
                     say what the shop chooses to stock. And it is the glyph
                     of the trade. --}}
                <ul class="home-about-points">
                    <li>
                        <span class="home-about-point-mark" aria-hidden="true">
                            @include('partials.icon', ['name' => 'bullseye', 'size' => 13])
                        </span>
                        <span>{{ __('store.home_about_quality') }}</span>
                    </li>
                    <li>
                        <span class="home-about-point-mark" aria-hidden="true">
                            @include('partials.icon', ['name' => 'bullseye', 'size' => 13])
                        </span>
                        <span>{{ __('store.home_about_goal') }}</span>
                    </li>
                    {{-- The first two lines say what the shop chooses; this
                         one says what it does with it. It is the sentence one
                         looks for before ordering, and the one carrying the
                         words people type to find it. --}}
                    <li>
                        <span class="home-about-point-mark" aria-hidden="true">
                            @include('partials.icon', ['name' => 'bullseye', 'size' => 13])
                        </span>
                        <span>{{ __('store.home_about_shipping') }}</span>
                    </li>
                </ul>
                {{-- « En savoir plus » about the shop goes to the page about
                     the shop, not to a product listing. --}}
                <a href="{{ route('about') }}" class="btn btn-primary home-about-cta">
                    {{ __('store.home_about_cta') }}
                    <svg class="home-about-cta-arrow" viewBox="0 0 16 16" width="13" height="13" aria-hidden="true" focusable="false">
                        <path d="M2.5 8h10m-4-4 4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
            </div>
        </section>
    </div>
@endsection

@push('head')
    {{-- The first panel is the largest thing painted, and its photograph
         arrives as a CSS background: the preload scanner cannot see it until
         home.css has been fetched and parsed, so the browser learns about it
         late and at no particular priority. Naming it here makes it
         discoverable in the initial document instead.

         Only the first panel: the other three are off screen, and preloading
         them would spend the visitor's bandwidth on pictures they may never
         reach. The URL is read from the slide rather than written out, since
         a preload that does not match the URL the panel paints downloads the
         photograph a second time. --}}
    <link
        rel="preload"
        as="image"
        href="{{ $slides[0]['image'] }}"
        type="image/webp"
        fetchpriority="high"
    >
    <link rel="stylesheet" href="{{ versioned_asset('css/home.css') }}">
    @if ($onSale->isNotEmpty())
        {{-- Only with the block: a script for a row that is not on the page
             is bytes spent on nothing. --}}
        <script src="{{ versioned_asset('js/home-deals.js') }}" defer></script>
    @endif
    <script src="{{ versioned_asset('js/home-carousel.js') }}" defer></script>
    <script type="application/ld+json">
        {{-- The key is written @@context so Blade emits a literal "@context":
             left bare, Blade compiles it as its own @context directive and the
             key is replaced by PHP, leaving the JSON-LD without @context. --}}
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => \App\Support\OrganizationSchema::websiteId(),
            'name' => config('app.name'),
            // The wordmark's one-word spelling and the bare domain, declared
            // as the same site so Google folds them onto the brand. The
            // display name stays `name`; these are recognition, not label.
            'alternateName' => ['ArmoOutdoor', 'armooutdoor'],
            'url' => localized_route('home'),
            'inLanguage' => 'fr-FR',
            'description' => __('store.meta_home'),
            // Named rather than restated: the site and the business that runs
            // it are two things, and this is what joins them.
            'publisher' => ['@id' => \App\Support\OrganizationSchema::id()],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
    <script type="application/ld+json">
        {{-- Google reads the business off the home page, so it is declared
             here rather than on every page of the shop. --}}
        {!! json_encode(
            \App\Support\OrganizationSchema::for(\App\Models\CompanySetting::current()),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) !!}
    </script>
@endpush
