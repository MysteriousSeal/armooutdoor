@php
    /** @var \App\Models\Product $product */
    $inWishlist = ($wishlistProductIds ?? collect())->contains($product->id);
    $cardVariants = $product->relationLoaded('variants') ? $product->variants : $product->variants()->with('supplier')->get();
    $variantCount = $cardVariants->count();
    $activeCardVariants = $cardVariants->where('is_active', true);
    $backorderableCardVariant = $activeCardVariants->first(fn ($variant) => ! $variant->inStock() && $variant->isBackorderable());
    $availableAtSupplier = $product->hasVariants()
        ? ($activeCardVariants->isNotEmpty() && $activeCardVariants->every(fn ($variant) => ! $variant->inStock()) && $backorderableCardVariant !== null)
        : (! $product->inStock() && $product->isBackorderable());
    $fiveColumn = $fiveColumn ?? false;
@endphp
<article class="masonry-card product-card {{ $product->inStock() || $availableAtSupplier ? '' : 'is-out-of-stock' }}">
    <a href="{{ localized_route('products.show', ['product' => $product->slug]) }}" class="masonry-card-link">
        <div class="masonry-card-media">
            <img
                src="{{ $product->thumbnailUrl() }}"
                alt="{{ $product->localizedName() }}"
                width="900"
                height="1200"
                loading="{{ $lazy ?? true ? 'lazy' : 'eager' }}"
            >
            @if ($product->hasDiscount())
                <span class="card-discount-chip">{{ $product->discount->label() }}</span>
            @endif
            @if ($variantCount > 0)
                <span class="card-variant-chip">{{ trans_choice('store.variants_count', $variantCount, ['count' => $variantCount]) }}</span>
            @endif
        </div>
        <div class="card-caption">
            <h2>{{ $product->localizedName() }}</h2>
            <div class="card-caption-meta">
                <p class="card-price">
                    @if ($product->hasDiscount())
                        <span class="card-price-original">{{ $product->formattedOriginalPrice() }}</span>
                    @endif
                    {{ $product->formattedPrice() }}
                </p>
                <span class="card-stock-chip {{ $product->lowStock() ? 'is-low-stock' : ($product->inStock() ? 'is-in-stock' : ($availableAtSupplier ? 'is-at-supplier' : 'is-out-of-stock')) }}">
                    {{ $product->lowStock() ? __($fiveColumn ? 'store.low_stock_short' : 'store.low_stock') : ($product->inStock() ? __('store.in_stock') : ($availableAtSupplier ? __($fiveColumn ? 'store.card_available_at_supplier_short' : 'store.card_available_at_supplier') : __('store.out_of_stock'))) }}
                </span>
            </div>
            <div class="card-rating">
                <span class="star-rating" aria-hidden="true">{{ str_repeat('★', (int) round($product->averageRating() ?? 0)) }}{{ str_repeat('☆', 5 - (int) round($product->averageRating() ?? 0)) }}</span>
                <span class="card-rating-count">({{ $product->reviewsCount() }})</span>
            </div>
        </div>
    </a>

    <form
        method="POST"
        action="{{ $inWishlist ? localized_route('wishlist.destroy', ['product' => $product->slug]) : localized_route('wishlist.store') }}"
        class="wishlist-btn-form"
    >
        @csrf
        @if ($inWishlist)
            @method('DELETE')
        @else
            <input type="hidden" name="product_id" value="{{ $product->id }}">
        @endif
        <button
            type="submit"
            class="wishlist-btn {{ $inWishlist ? 'is-active' : '' }}"
            aria-label="{{ $inWishlist ? __('store.remove_from_wishlist') : __('store.add_to_wishlist') }}"
            title="{{ $inWishlist ? __('store.remove_from_wishlist') : __('store.add_to_wishlist') }}"
        >
            <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
                <path d="M12 20s-7.5-4.6-7.5-10A4.4 4.4 0 0 1 12 6.8 4.4 4.4 0 0 1 19.5 10c0 5.4-7.5 10-7.5 10z" fill="{{ $inWishlist ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
            </svg>
        </button>
    </form>

    @if ($product->isPurchasable())
        @if ($variantCount > 0)
            <div class="card-cart">
                <a href="{{ localized_route('products.show', ['product' => $product->slug]) }}" class="btn btn-sm btn-primary">
                    {{ __('store.view_options') }}
                </a>
            </div>
        @else
            <form method="POST" action="{{ localized_route('cart.add') }}" class="card-cart">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <button type="submit" class="btn btn-sm btn-primary">{{ __('store.add_to_cart') }}</button>
            </form>
        @endif
    @endif
</article>
