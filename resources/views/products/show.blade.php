@extends('layouts.app')

@section('title', $product->localizedName().' — '.config('app.name'))
@section('meta_description', \Illuminate\Support\Str::limit($product->localizedDescriptionText(), 160))
@section('canonical', localized_route('products.show', ['product' => $product->slug]))

@section('content')
    @php
        $inWishlist = ($wishlistProductIds ?? collect())->contains($product->id);
        $gallery = collect([$product->imageUrl()])
            ->concat($product->images->map->imageUrl())
            ->filter()
            ->unique()
            ->values();
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
                                data-full-src="{{ $src }}"
                            >
                                <img src="{{ $src }}" alt="" loading="lazy">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="product-detail-buy">
                @php
                    $activeVariants = $product->variants->where('is_active', true);
                    $selectedVariantId = old('variant_id', optional($activeVariants->first(fn ($variant) => $variant->inStock()))->id);
                    $displayVariant = $product->hasVariants() ? $activeVariants->firstWhere('id', (int) $selectedVariantId) : null;
                @endphp

                @if ($product->category)
                    <p class="product-detail-category">
                        <a href="{{ localized_route('categories.show', ['category' => $product->category->slug]) }}">
                            {{ $product->category->localizedName() }}
                        </a>
                    </p>
                @endif

                <h2 class="product-detail-title">{{ $product->localizedName() }}</h2>
                <div class="product-detail-meta">
                    <p class="product-detail-price" id="product-detail-price">{{ ($displayVariant ?? $product)->formattedPrice() }}</p>
                    <span class="stock-badge {{ ($displayVariant ?? $product)->lowStock() ? 'is-low-stock' : (($displayVariant ?? $product)->inStock() ? 'is-in-stock' : 'is-out-of-stock') }}" id="product-stock-badge">
                        {{ ($displayVariant ?? $product)->lowStock() ? __('store.low_stock') : (($displayVariant ?? $product)->inStock() ? __('store.in_stock') : __('store.out_of_stock')) }}
                    </span>
                </div>

                @if ($product->isPurchasable())
                    <form method="POST" action="{{ localized_route('cart.add') }}" class="add-to-cart-form">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">

                        @if ($product->hasVariants())
                            <fieldset class="product-variants-fieldset">
                                <legend class="product-variants-legend">{{ __('store.product_variants') }}</legend>
                                <div class="choice-grid choice-grid--variants" data-variant-options>
                                    @foreach ($activeVariants as $variant)
                                        <label class="choice-card">
                                            <input
                                                type="radio"
                                                name="variant_id"
                                                value="{{ $variant->id }}"
                                                data-variant-price="{{ $variant->formattedPrice() }}"
                                                data-variant-stock-class="{{ $variant->lowStock() ? 'is-low-stock' : ($variant->inStock() ? 'is-in-stock' : 'is-out-of-stock') }}"
                                                data-variant-stock-label="{{ $variant->lowStock() ? __('store.low_stock') : ($variant->inStock() ? __('store.in_stock') : __('store.out_of_stock')) }}"
                                                data-variant-max="{{ $variant->maxPurchasable() }}"
                                                @checked((string) $selectedVariantId === (string) $variant->id)
                                                {{ ! $variant->inStock() ? 'disabled' : '' }}
                                            >
                                            <span class="choice-card-body">
                                                <span class="choice-card-title">
                                                    {{ $variant->label() !== '' ? $variant->label() : $product->localizedName() }}
                                                    <span class="choice-card-price">{{ $variant->formattedPrice() }}</span>
                                                </span>
                                                @if ($variant->sku)
                                                    <span class="choice-card-meta">{{ $variant->sku }}</span>
                                                @endif
                                                <span class="choice-card-meta">
                                                    {{ $variant->inStock() ? __('store.in_stock') : __('store.out_of_stock') }}
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

                        <div class="product-buy-row">
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
                    <li>{{ __('store.home_trust_ship_title') }} — {{ __('store.footer_delivery_home') }}</li>
                    <li>{{ __('store.home_trust_ship_title') }} — {{ __('store.footer_delivery_relay') }}</li>
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
@endpush
