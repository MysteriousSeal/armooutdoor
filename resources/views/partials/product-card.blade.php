@php
    /** @var \App\Models\Product $product */
    $inWishlist = ($wishlistProductIds ?? collect())->contains($product->id);
@endphp
<article class="masonry-card product-card {{ $product->inStock() ? '' : 'is-out-of-stock' }}">
    <a href="{{ localized_route('products.show', ['product' => $product->slug]) }}" class="masonry-card-link">
        <div class="masonry-card-media">
            <img
                src="{{ $product->imageUrl() }}"
                alt="{{ $product->localizedName() }}"
                width="900"
                height="1200"
                loading="{{ $lazy ?? true ? 'lazy' : 'eager' }}"
            >
        </div>
        <div class="card-caption">
            <h2>{{ $product->localizedName() }}</h2>
            <div class="card-caption-meta">
                <p class="card-price">{{ $product->formattedPrice() }}</p>
                @unless ($product->inStock())
                    <span class="card-stock-chip">{{ __('store.variant_stock_out') }}</span>
                @endunless
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

    @if ($product->inStock())
        <form method="POST" action="{{ localized_route('cart.add') }}" class="card-cart">
            @csrf
            <input type="hidden" name="product_id" value="{{ $product->id }}">
            <button type="submit" class="btn btn-sm btn-primary">{{ __('store.add_to_cart') }}</button>
        </form>
    @endif
</article>
