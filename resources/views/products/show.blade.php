@extends('layouts.app')

@section('title', $product->localizedName().' — '.config('app.name'))
@section('meta_description', \Illuminate\Support\Str::limit($product->localizedDescriptionText(), 160))
@section('canonical', localized_route('products.show', ['product' => $product->slug]))

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
                        width="900"
                        height="900"
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
                                <img src="{{ $src['thumb'] }}" alt="" loading="lazy">
                            </button>
                        @endforeach
                    </div>
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
                @endphp

                @if ($product->category)
                    <p class="product-detail-category">
                        <a href="{{ localized_route('categories.show', ['category' => $product->category->slug]) }}">
                            {{ $product->category->localizedName() }}
                        </a>
                    </p>
                @endif

                <h2 class="product-detail-title">{{ $product->localizedName() }}</h2>
                <div class="product-detail-rating">
                    <span class="star-rating" aria-hidden="true">{{ str_repeat('★', (int) round($product->averageRating() ?? 0)) }}{{ str_repeat('☆', 5 - (int) round($product->averageRating() ?? 0)) }}</span>
                    <span class="card-rating-count">({{ $product->reviewsCount() }})</span>
                </div>
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
                    <span class="stock-badge {{ $product->lowStock() ? 'is-low-stock' : ($product->inStock() ? 'is-in-stock' : 'is-out-of-stock') }}" id="product-stock-badge">
                        {{ $product->lowStock() ? __('store.low_stock') : ($product->inStock() ? __('store.in_stock') : __('store.out_of_stock')) }}
                    </span>
                </div>

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
                                        <label class="product-variant-chip {{ ! $variant->inStock() ? 'is-unavailable' : '' }}">
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
                                                    <span class="product-variant-chip-stock {{ $variant->lowStock() ? 'is-low-stock' : ($variant->inStock() ? 'is-in-stock' : 'is-out-of-stock') }}">
                                                        {{ $variant->lowStock() ? __('store.variant_stock_low') : ($variant->inStock() ? __('store.variant_stock_ok') : __('store.variant_stock_out')) }}
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

                <ul class="product-detail-perks">
                    @if ($allowedHomeCarriers->isNotEmpty())
                        <li>{{ __('store.home_trust_ship_title') }} — À domicile : {{ $allowedHomeCarriers->join(', ', ' et ') }}</li>
                    @endif
                    @if ($allowedRelayCarriers->isNotEmpty())
                        <li>{{ __('store.home_trust_ship_title') }} — Point relais : {{ $allowedRelayCarriers->join(', ', ' et ') }}</li>
                    @endif
                    <li>{{ __('store.home_trust_pay_title') }} — {{ __('store.footer_payment_card') }}, {{ __('store.footer_payment_paypal') }}</li>
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
                    <span class="star-rating" aria-hidden="true">{{ str_repeat('★', (int) round($product->averageRating())) }}{{ str_repeat('☆', 5 - (int) round($product->averageRating())) }}</span>
                    <span class="reviews-summary-value">{{ number_format($product->averageRating(), 1) }} / 5</span>
                @endif
                <span class="reviews-summary-count">{{ trans_choice('store.reviews_count', $product->reviewsCount(), ['count' => $product->reviewsCount()]) }}</span>
            </div>

            @auth
                @if ($product->canBeReviewedBy(auth()->user()))
                    <form method="POST" action="{{ localized_route('reviews.store', ['product' => $product->slug]) }}" class="review-form">
                        @csrf
                        <h4 class="review-form-title">{{ __('store.review_form_title') }}</h4>

                        <div class="star-input" role="radiogroup" aria-label="{{ __('store.review_rating_label') }}">
                            @for ($value = 5; $value >= 1; $value--)
                                <input type="radio" name="rating" id="rating-{{ $value }}" value="{{ $value }}" @checked((int) old('rating') === $value) required>
                                <label for="rating-{{ $value }}">★</label>
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
                <p class="reviews-empty">{{ __('store.reviews_empty') }}</p>
            @else
                <ul class="reviews-list">
                    @foreach ($product->reviews as $review)
                        <li class="review-item">
                            <div class="review-item-head">
                                <span class="star-rating" aria-hidden="true">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span>
                                <span class="review-item-author">{{ $review->reviewerName() }}</span>
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
    <script src="{{ asset('js/product-gallery.js') }}" defer></script>
    <script src="{{ asset('js/product-qty.js') }}" defer></script>
    <script src="{{ asset('js/product-variant.js') }}" defer></script>
    <script src="{{ asset('js/product-discount-countdown.js') }}" defer></script>
@endpush
