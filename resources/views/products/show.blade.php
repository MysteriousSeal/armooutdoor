@extends('layouts.app')

@section('title', $product->metaTitle().' — '.config('app.name'))
@section('meta_description', $product->metaDescription())
@section('canonical', localized_route('products.show', ['product' => $product->slug]))
@section('og_type', 'product')
@section('og_image', $product->imageUrl())
@section('og_image_alt', $product->localizedName())

@push('head')
    @if ($product->blogPosts->isNotEmpty())
        <link rel="stylesheet" href="{{ versioned_asset('css/blog.css') }}">
    @endif
    <script type="application/ld+json">
        {!! json_encode(\App\Support\ProductSchema::for($product), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'BreadcrumbList',
            'itemListElement' => collect([
                ['name' => __('store.breadcrumb_home'), 'item' => localized_route('home')],
                $product->category?->parent ? [
                    'name' => $product->category->parent->localizedName(),
                    'item' => localized_route('categories.show', ['category' => $product->category->parent->slug]),
                ] : null,
                $product->category ? [
                    'name' => $product->category->localizedName(),
                    'item' => localized_route('categories.show', ['category' => $product->category->slug]),
                ] : null,
                ['name' => $product->localizedName(), 'item' => localized_route('products.show', ['product' => $product->slug])],
            ])->filter()->values()->map(fn (array $crumb, int $index): array => [
                '@@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => $crumb['item'],
            ])->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('content')
    @php
        $inWishlist = ($wishlistProductIds ?? collect())->contains($product->id);
        $gallery = collect([['full' => $product->imageUrl(), 'thumb' => $product->thumbnailUrl()]])
            ->concat($product->images->map(fn ($image) => ['full' => $image->imageUrl(), 'thumb' => $image->thumbnailUrl()]))
            ->filter(fn (array $entry) => $entry['full'] !== '')
            ->unique('full')
            ->values();
        $allowedCarriers = \App\Models\Carrier::active()->get()->filter(fn ($carrier) => $product->isCarrierAllowed($carrier));
        $allowedHomeCarriers = $allowedCarriers->where('method', \App\Enums\DeliveryMethod::Home)->map->localizedName();
        $allowedRelayCarriers = $allowedCarriers->where('method', \App\Enums\DeliveryMethod::Relay)->map->localizedName();
    @endphp
    <div class="container">
        <nav class="breadcrumbs" aria-label="breadcrumb">
            <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
            @if ($product->category?->parent)
                <span class="breadcrumbs-sep" aria-hidden="true">/</span>
                <a href="{{ localized_route('categories.show', ['category' => $product->category->parent->slug]) }}">
                    {{ $product->category->parent->localizedName() }}
                </a>
            @endif
            @if ($product->category)
                <span class="breadcrumbs-sep" aria-hidden="true">/</span>
                <a href="{{ localized_route('categories.show', ['category' => $product->category->slug]) }}">
                    {{ $product->category->localizedName() }}
                </a>
            @endif
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <span>{{ $product->localizedName() }}</span>
        </nav>

        <article class="product-detail">
            <div class="product-detail-gallery">
                <div class="product-detail-stage">
                    <img
                        id="product-detail-main-image"
                        src="{{ $product->imageUrl() }}"
                        alt="{{ $product->localizedName() }}"
                        width="{{ \App\Support\ImageThumbnailer::MAIN_SIZE }}"
                        height="{{ \App\Support\ImageThumbnailer::MAIN_SIZE }}"
                        fetchpriority="high"
                    >
                </div>
                @if ($gallery->count() > 1)
                    <div class="product-detail-thumbs" role="group" aria-label="{{ __('store.product_photos') }}">
                        @foreach ($gallery as $src)
                            <button
                                type="button"
                                class="product-detail-thumb {{ $loop->first ? 'is-active' : '' }}"
                                data-full-src="{{ $src['full'] }}"
                            >
                                <img
                                    src="{{ $src['thumb'] }}"
                                    alt=""
                                    width="{{ \App\Support\ImageThumbnailer::SIZE }}"
                                    height="{{ \App\Support\ImageThumbnailer::SIZE }}"
                                    loading="lazy"
                                >
                            </button>
                        @endforeach
                    </div>
                @endif
                @if ($product->image_may_vary)
                    <p class="image-may-vary-notice">{{ __('store.image_may_vary_notice') }}</p>
                @endif
            </div>

            <div class="product-detail-buy">
                @php
                    $activeVariants = $product->variants->where('is_active', true)
                        ->sortBy(fn ($variant) => $variant->sizeSortRank() ?? $variant->sort_order)
                        ->values();
                    $selectedVariantId = old('variant_id', optional($activeVariants->first(fn ($variant) => $variant->inStock()) ?? $activeVariants->first())->id);
                    $displayVariant = $product->hasVariants() ? $activeVariants->firstWhere('id', (int) $selectedVariantId) : null;
                    $variantHasOwnPrice = $displayVariant?->price_cents !== null;
                    // La référence affichée est celle de ce qui partira au
                    // panier : celle de la variante si elle en a une.
                    $displaySku = $displayVariant?->sku ?: $product->sku;
                    $keySpecs = $product->keyCharacteristics();
                @endphp

                @if ($product->category)
                    <p class="product-detail-category">
                        <a href="{{ localized_route('categories.show', ['category' => $product->category->slug]) }}">
                            {{ $product->category->localizedName() }}
                        </a>
                    </p>
                @endif

                <h1 class="product-detail-title">{{ $product->localizedName() }}</h1>
                <p class="product-detail-sku" id="product-detail-sku" @if (! $displaySku) hidden @endif>
                    <span class="product-detail-sku-label">{{ __('store.product_sku') }}</span>
                    <span class="product-detail-sku-value" id="product-detail-sku-value">{{ $displaySku }}</span>
                </p>

                {{-- On regarde les étoiles en haut, puis on cherche les avis en
                     bas : autant que les premières y mènent. Le lien porte la
                     note en toutes lettres, les étoiles n'étant qu'un dessin. --}}
                <a class="product-detail-rating" href="#product-reviews-title">
                    <span class="star-rating" aria-hidden="true">{{ str_repeat('★', (int) round($product->averageRating() ?? 0)) }}{{ str_repeat('☆', 5 - (int) round($product->averageRating() ?? 0)) }}</span>
                    <span class="card-rating-count" aria-hidden="true">({{ $product->reviewsCount() }})</span>
                    <span class="sr-only">
                        @if ($product->reviewsCount() > 0)
                            {{ __('store.reviews_rating_summary', ['rating' => number_format($product->averageRating(), 1, ',', ''), 'count' => $product->reviewsCount()]) }}
                        @else
                            {{ __('store.reviews_empty') }}
                        @endif
                    </span>
                    <span class="product-detail-rating-link" aria-hidden="true">{{ __('store.reviews_see_all') }}</span>
                </a>
                <div class="product-detail-meta">
                    <span
                        class="badge badge-active cart-line-discount-badge"
                        id="product-detail-discount-badge"
                        @if ($variantHasOwnPrice || ! $product->hasDiscount()) hidden @endif
                    >{{ $product->hasDiscount() ? $product->discount->label() : '' }}</span>
                    <p class="product-detail-price" id="product-detail-price">
                        <span
                            class="product-detail-price-original"
                            id="product-detail-price-original"
                            @if ($variantHasOwnPrice || ! $product->hasDiscount()) hidden @endif
                        >{{ $product->formattedOriginalPrice() }}</span>
                        <span id="product-detail-price-current">{{ ($displayVariant ?? $product)->formattedPrice() }}</span>
                    </p>
                    @php
                        $backorderableVariant = $product->hasVariants()
                            ? $activeVariants->first(fn ($variant) => ! $variant->inStock() && $variant->isBackorderable())
                            : null;
                        $availableAtSupplier = $product->hasVariants()
                            ? ($activeVariants->isNotEmpty() && $activeVariants->every(fn ($variant) => ! $variant->inStock()) && $backorderableVariant !== null)
                            : (! $product->inStock() && $product->supplier_id !== null && $product->available_at_supplier);
                        $supplierForLeadTime = $backorderableVariant?->supplier ?? $product->supplier;
                        $stockState = $product->availabilityState();
                    @endphp
                    <span class="stock-badge is-{{ str_replace('_', '-', $stockState) }}" id="product-stock-badge">
                        {{ __('store.'.($stockState === 'at_supplier' ? 'available_at_supplier' : $stockState)) }}
                    </span>
                </div>

                {{-- Ce qu'on vérifie avant de cliquer, à côté du prix. Le
                     tableau complet reste en bas : ici on répond aux trois ou
                     quatre questions qui décident, sans faire défiler. --}}
                @if ($keySpecs !== [])
                    <dl class="product-key-specs">
                        @foreach ($keySpecs as $spec)
                            <div class="product-key-spec">
                                <dt>{{ $spec['label'] }}</dt>
                                <dd title="{{ $spec['value'] }}">{{ $spec['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
                @php
                    $displayLeadTimeSource = $product->hasVariants() ? $displayVariant : $product;
                    $displayLeadTimeVisible = $product->hasVariants()
                        ? ($displayLeadTimeSource !== null && ! $displayLeadTimeSource->inStock() && $displayLeadTimeSource->isBackorderable())
                        : $availableAtSupplier;
                    $displayLeadTimeSupplier = $product->hasVariants() ? $displayLeadTimeSource?->supplier : $supplierForLeadTime;
                @endphp
                {{-- Placée avant le délai : on dit d'abord pourquoi il y en a un. --}}
                <p class="product-supplier-notice" id="product-supplier-notice" @if (! $displayLeadTimeVisible) hidden @endif>
                    <span class="product-supplier-notice-icon" aria-hidden="true">
                        @include('partials.icon', ['name' => 'circle-info', 'size' => 16])
                    </span>
                    <span>{{ __('store.supplier_notice') }}</span>
                </p>
                <p class="product-lead-time" id="product-lead-time" @if (! $displayLeadTimeVisible) hidden @endif>
                    <span class="product-lead-time-icon" aria-hidden="true">
                        @include('partials.icon', ['name' => 'hourglass-half', 'size' => 16])
                    </span>
                    <span class="product-lead-time-copy">
                        <strong>{{ __('store.supplier_lead_time_label') }}</strong>
                        <span id="product-lead-time-value">
                            {{ $displayLeadTimeSupplier?->lead_time_days !== null
                                ? trans_choice('store.supplier_lead_time_value', $displayLeadTimeSupplier->lead_time_days, ['days' => $displayLeadTimeSupplier->lead_time_days])
                                : __('store.supplier_lead_time_unknown') }}
                        </span>
                    </span>
                </p>
                @if ($product->hasVariants())
                    <template id="variant-lead-times">
                        @foreach ($activeVariants as $variant)
                            @php
                                $variantBackorderable = ! $variant->inStock() && $variant->isBackorderable();
                                $variantLeadTimeText = $variantBackorderable
                                    ? ($variant->supplier?->lead_time_days !== null
                                        ? trans_choice('store.supplier_lead_time_value', $variant->supplier->lead_time_days, ['days' => $variant->supplier->lead_time_days])
                                        : __('store.supplier_lead_time_unknown'))
                                    : '';
                            @endphp
                            <span
                                data-variant-id="{{ $variant->id }}"
                                data-lead-time-visible="{{ $variantBackorderable ? '1' : '' }}"
                                data-lead-time-text="{{ $variantLeadTimeText }}"
                            ></span>
                        @endforeach
                    </template>
                @endif

                <div
                    class="discount-countdown"
                    id="discount-countdown"
                    data-ends-at="{{ (! $variantHasOwnPrice && $product->hasDiscount() && $product->discount->ends_at) ? $product->discount->ends_at->toIso8601String() : '' }}"
                    data-label-days="{{ __('store.discount_countdown_days') }}"
                    data-label-hours="{{ __('store.discount_countdown_hours') }}"
                    data-label-minutes="{{ __('store.discount_countdown_minutes') }}"
                    data-label-seconds="{{ __('store.discount_countdown_seconds') }}"
                    @if ($variantHasOwnPrice || ! $product->hasDiscount() || ! $product->discount->ends_at) hidden @endif
                >
                    <p class="discount-countdown-label">{{ __('store.discount_ends_in') }}</p>
                    <div class="discount-countdown-timer" id="discount-countdown-timer" aria-live="off"></div>
                </div>

                @if ($product->age_restricted)
                    <p class="age-restricted-notice">{{ __('store.age_restricted_notice') }}</p>
                    <p class="age-restricted-notice">{{ __('store.age_restricted_proof_notice') }}</p>
                @endif

                @if ($product->isPurchasable() || $product->hasVariants())
                    <form method="POST" action="{{ localized_route('cart.add') }}" class="add-to-cart-form">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">

                        @if ($product->hasVariants())
                            @php
                                $variantPricesDiffer = $activeVariants->map->effectivePriceCents()->unique()->count() > 1;
                                $selectedVariantLabel = $displayVariant
                                    ? ($displayVariant->label() !== '' ? $displayVariant->label() : $product->localizedName())
                                    : '';
                            @endphp
                            <fieldset class="product-variants">
                                <legend class="product-variants-legend">
                                    <span>{{ __('store.product_variants') }}</span>
                                    <span class="product-variants-current" id="product-variant-current">{{ $selectedVariantLabel }}</span>
                                </legend>
                                <div class="product-variant-grid" data-variant-options>
                                    @foreach ($activeVariants as $variant)
                                        @php($variantLabel = $variant->label() !== '' ? $variant->label() : $product->localizedName())
                                        <label class="product-variant-chip {{ (! $variant->inStock() && ! $variant->isBackorderable()) ? 'is-unavailable' : '' }}">
                                            <input
                                                type="radio"
                                                name="variant_id"
                                                value="{{ $variant->id }}"
                                                data-variant-label="{{ $variantLabel }}"
                                                data-variant-price="{{ $variant->formattedPrice() }}"
                                                data-variant-original-price="{{ ($variant->price_cents === null && $product->hasDiscount()) ? $product->formattedOriginalPrice() : '' }}"
                                                data-variant-discount-label="{{ ($variant->price_cents === null && $product->hasDiscount()) ? $product->discount->label() : '' }}"
                                                data-variant-discount-ends-at="{{ ($variant->price_cents === null && $product->hasDiscount() && $product->discount->ends_at) ? $product->discount->ends_at->toIso8601String() : '' }}"
                                                data-variant-max="{{ $variant->maxPurchasable() }}"
                                                data-variant-sku="{{ $variant->sku ?: $product->sku }}"
                                                @if ($variant->image)
                                                    data-variant-image="{{ $variant->imageUrl() }}"
                                                @endif
                                                @checked((string) $selectedVariantId === (string) $variant->id)
                                            >
                                            <span class="product-variant-chip-face">
                                                @if ($variant->image)
                                                    <span class="product-variant-chip-media">
                                                        <img src="{{ $variant->imageUrl() }}" alt="">
                                                    </span>
                                                @endif
                                                <span class="product-variant-chip-copy">
                                                    <span class="product-variant-chip-label">{{ $variantLabel }}</span>
                                                    @if ($variantPricesDiffer)
                                                        <span class="product-variant-chip-price">{{ $variant->formattedPrice() }}</span>
                                                    @endif
                                                    @php($variantRestocking = ! $variant->inStock() && $variant->isRestocking())
                                                    @php($variantBackorderable = ! $variant->inStock() && $variant->isBackorderable())
                                                    <span class="product-variant-chip-stock {{ $variant->lowStock() ? 'is-low-stock' : ($variant->inStock() ? 'is-in-stock' : ($variantRestocking ? 'is-restocking' : ($variantBackorderable ? 'is-at-supplier' : 'is-out-of-stock'))) }}">
                                                        {{ $variant->lowStock() ? __('store.variant_stock_low') : ($variant->inStock() ? __('store.variant_stock_ok') : ($variantRestocking ? __('store.variant_stock_restocking') : ($variantBackorderable ? __('store.variant_stock_backorder') : __('store.variant_stock_out')))) }}
                                                    </span>
                                                </span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('variant_id')
                                    <p class="form-error">{{ $message }}</p>
                                @enderror
                            </fieldset>
                        @endif

                        @if ($product->isPurchasable())
                            <div class="product-buy-row" @if (($displayVariant ?? $product)->maxPurchasable() < 1) hidden @endif>
                                @if ($availableAtSupplier)
                                    <input type="hidden" name="quantity" value="1">
                                @else
                                    <div class="qty-stepper">
                                        <button type="button" class="qty-stepper-btn" data-qty-step="-1" aria-label="−">−</button>
                                        <label class="sr-only" for="quantity">{{ __('store.quantity') }}</label>
                                        <input
                                            type="number"
                                            id="quantity"
                                            name="quantity"
                                            class="qty-stepper-input"
                                            value="{{ old('quantity', 1) }}"
                                            min="1"
                                            max="{{ ($displayVariant ?? $product)->maxPurchasable() }}"
                                            required
                                        >
                                        <button type="button" class="qty-stepper-btn" data-qty-step="1" aria-label="+">+</button>
                                    </div>
                                @endif
                                <button type="submit" class="btn btn-primary product-buy-submit">{{ __('store.add_to_cart') }}</button>
                            </div>
                            @error('quantity')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        @endif
                    </form>
                @endif

                <form
                    method="POST"
                    action="{{ $inWishlist ? localized_route('wishlist.destroy', ['product' => $product->slug]) : localized_route('wishlist.store') }}"
                    class="product-detail-wishlist-form"
                >
                    @csrf
                    @if ($inWishlist)
                        @method('DELETE')
                    @else
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                    @endif
                    <button type="submit" class="btn btn-secondary product-wishlist-btn {{ $inWishlist ? 'is-active' : '' }}">
                        <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
                            <path d="M12 20s-7.5-4.6-7.5-10A4.4 4.4 0 0 1 12 6.8 4.4 4.4 0 0 1 19.5 10c0 5.4-7.5 10-7.5 10z" fill="{{ $inWishlist ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                        </svg>
                        <span>{{ $inWishlist ? __('store.remove_from_wishlist') : __('store.add_to_wishlist') }}</span>
                    </button>
                </form>

                {{-- Un titre, puis ses lignes. Répété une fois par mode de
                     livraison, « Livraison France » se lisait deux fois de
                     suite pour dire une seule chose. --}}
                <ul class="product-detail-perks">
                    @if ($allowedHomeCarriers->isNotEmpty() || $allowedRelayCarriers->isNotEmpty())
                        <li>
                            <span class="product-perk-title">{{ __('store.home_trust_ship_title') }}</span>
                            @if ($allowedHomeCarriers->isNotEmpty())
                                <span class="product-perk-line">{{ __('store.shipping_home') }} : {{ $allowedHomeCarriers->join(', ', ' et ') }}</span>
                            @endif
                            @if ($allowedRelayCarriers->isNotEmpty())
                                <span class="product-perk-line">{{ __('store.shipping_relay') }} : {{ $allowedRelayCarriers->join(', ', ' et ') }}</span>
                            @endif
                        </li>
                    @endif
                    <li>
                        <span class="product-perk-title">{{ __('store.home_trust_pay_title') }}</span>
                        <span class="product-perk-line">{{ __('store.footer_payment_card') }}, {{ __('store.footer_payment_paypal') }}</span>
                    </li>
                </ul>
            </div>
        </article>

        @if ($product->localizedDescription() !== '')
            <section class="product-desc" aria-labelledby="product-desc-title">
                <h3 class="product-desc-title" id="product-desc-title">{{ __('store.product_description') }}</h3>
                <div class="product-detail-text">{!! $product->localizedDescription() !!}</div>
            </section>
        @endif

        @if (! empty($product->characteristics))
            <section class="product-specs" aria-labelledby="product-specs-title">
                <h3 class="product-desc-title" id="product-specs-title">{{ __('store.product_characteristics') }}</h3>
                <dl class="product-specs-list">
                    @foreach ($product->characteristics as $characteristic)
                        <div class="product-specs-row">
                            <dt>{{ $characteristic['label'] }}</dt>
                            <dd>{{ $characteristic['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif

        <section class="product-reviews" aria-labelledby="product-reviews-title">
            <h3 class="product-desc-title" id="product-reviews-title">{{ __('store.reviews_title') }}</h3>

            <div class="reviews-summary">
                @if ($product->averageRating() !== null)
                    {{-- Les étoiles sont un dessin : la note se lit à côté, en
                         toutes lettres, pour l'œil comme pour la synthèse vocale. --}}
                    <span class="star-rating" aria-hidden="true">{{ str_repeat('★', (int) round($product->averageRating())) }}{{ str_repeat('☆', 5 - (int) round($product->averageRating())) }}</span>
                    <span class="reviews-summary-value">{{ number_format($product->averageRating(), 1) }} / 5</span>
                @endif
                <span class="reviews-summary-count">{{ trans_choice('store.reviews_count', $product->reviewsCount(), ['count' => $product->reviewsCount()]) }}</span>
            </div>

            @if ($product->reviewsCount() > 0)
                {{-- Une moyenne cache son échantillon : 3,5 sur deux avis n'est
                     pas un verdict, c'est deux opinions. La répartition le dit
                     mieux que la moyenne seule. --}}
                @php($ratingCounts = $product->ratingDistribution())
                <table class="reviews-distribution">
                    <caption class="sr-only">{{ __('store.reviews_distribution_title') }}</caption>
                    <tbody>
                        @foreach ($ratingCounts as $stars => $count)
                            <tr>
                                <th scope="row">
                                    <span aria-hidden="true">{{ $stars }} ★</span>
                                    <span class="sr-only">{{ trans_choice('store.review_rating_value', $stars, ['count' => $stars]) }}</span>
                                </th>
                                <td class="reviews-distribution-track">
                                    <span
                                        class="reviews-distribution-bar {{ $count === 0 ? 'is-empty' : '' }}"
                                        style="--share: {{ $product->reviewsCount() > 0 ? round($count / $product->reviewsCount() * 100) : 0 }}%"
                                    ></span>
                                </td>
                                <td class="reviews-distribution-count">
                                    <span aria-hidden="true">{{ $count }}</span>
                                    <span class="sr-only">{{ __('store.reviews_distribution_row', ['count' => $count, 'stars' => $stars]) }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            {{-- La boutique n'accepte un avis que d'un client dont la commande
                 est partie. C'est une garantie que peu de boutiques peuvent
                 donner, et la page ne la disait nulle part. --}}
            <p class="reviews-gate">
                <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true">
                    <path d="M12 3l7 3v5c0 4.4-3 8.2-7 10-4-1.8-7-5.6-7-10V6l7-3z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                    <path d="m9 12 2 2 4-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                {{ __('store.reviews_gate') }}
            </p>

            @auth
                @if ($product->canBeReviewedBy(auth()->user()))
                    {{-- Personne n'a encore écrit : c'est le seul moment où la
                         page a une raison de demander, et elle ne le faisait
                         pas. Ailleurs, le titre habituel suffit. --}}
                    @php($isFirstReview = $product->reviews->isEmpty())
                    <form method="POST" action="{{ localized_route('reviews.store', ['product' => $product->slug]) }}" class="review-form {{ $isFirstReview ? 'is-first' : '' }}">
                        @csrf
                        <h4 class="review-form-title">
                            {{ $isFirstReview ? __('store.review_form_title_first') : __('store.review_form_title') }}
                        </h4>
                        @if ($isFirstReview)
                            <p class="review-form-intro">{{ __('store.review_form_intro_first') }}</p>
                        @endif

                        {{-- De 1 à 5, dans l'ordre du document. Écrites à
                             l'envers et retournées en CSS, les flèches du
                             clavier parcouraient les notes de droite à gauche. --}}
                        <div class="star-input" role="radiogroup" aria-label="{{ __('store.review_rating_label') }}">
                            @for ($value = 1; $value <= 5; $value++)
                                <input type="radio" name="rating" id="rating-{{ $value }}" value="{{ $value }}" @checked((int) old('rating') === $value) required>
                                <label for="rating-{{ $value }}">
                                    <span aria-hidden="true">★</span>
                                    <span class="sr-only">{{ trans_choice('store.review_rating_value', $value, ['count' => $value]) }}</span>
                                </label>
                            @endfor
                        </div>
                        @error('rating') <p class="form-error">{{ $message }}</p> @enderror

                        <div class="form-group">
                            <label for="review-comment" class="sr-only">{{ __('store.review_comment_label') }}</label>
                            <textarea id="review-comment" name="comment" class="form-control" rows="3" placeholder="{{ __('store.review_comment_label') }}">{{ old('comment') }}</textarea>
                            @error('comment') <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        <button type="submit" class="btn btn-primary">{{ __('store.review_submit') }}</button>
                    </form>
                @elseif ($product->hasBeenReviewedBy(auth()->user()))
                    <p class="reviews-already">{{ __('store.review_already_submitted') }}</p>
                @endif
            @endauth

            @if ($product->reviews->isEmpty())
                {{-- Le formulaire dit déjà qu'il n'y a rien : le répéter en
                     dessous ferait deux fois la même phrase. --}}
                @unless (auth()->check() && $product->canBeReviewedBy(auth()->user()))
                    <p class="reviews-empty">{{ __('store.reviews_empty') }}</p>
                @endunless
            @else
                <ul class="reviews-list">
                    @foreach ($product->reviews as $review)
                        <li class="review-item">
                            <div class="review-item-head">
                                <span class="star-rating" aria-hidden="true">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span>
                                <span class="sr-only">{{ trans_choice('store.review_rating_value', $review->rating, ['count' => $review->rating]) }}</span>
                                <span class="review-item-author">{{ $review->reviewerName() }}</span>
                                @if ($review->order_id)
                                    <span class="review-verified" title="{{ __('store.review_verified_hint') }}">
                                        <svg viewBox="0 0 24 24" width="12" height="12" aria-hidden="true">
                                            <path d="m5 13 4 4L19 7" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        {{ __('store.review_verified') }}
                                    </span>
                                @endif
                                <span class="review-item-date">{{ $review->created_at->translatedFormat('d F Y') }}</span>
                            </div>
                            @if (filled($review->comment))
                                <p class="review-item-comment">{{ $review->comment }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        @if ($product->blogPosts->isNotEmpty())
            <section class="shop-section product-blog-posts" aria-labelledby="product-blog-title">
                <div class="section-head">
                    <h2 class="section-title" id="product-blog-title">{{ __('store.product_blog_posts') }}</h2>
                </div>
                <div class="blog-grid">
                    @foreach ($product->blogPosts as $post)
                        @include('blog.partials.card', ['post' => $post, 'lazy' => true])
                    @endforeach
                </div>
            </section>
        @endif

        @if ($related->isNotEmpty())
            <section class="shop-section">
                <header class="section-header">
                    <h2 class="section-title">{{ __('store.related') }}</h2>
                </header>
                <div class="product-grid">
                    @foreach ($related as $relatedProduct)
                        @include('partials.product-card', ['product' => $relatedProduct])
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/product-gallery.js') }}" defer></script>
    <script src="{{ versioned_asset('js/product-qty.js') }}" defer></script>
    <script src="{{ versioned_asset('js/product-variant.js') }}" defer></script>
    <script src="{{ versioned_asset('js/product-discount-countdown.js') }}" defer></script>
@endpush
