{{-- What is being bought, in one row whatever the cart's size:
     a few thumbnails, the item count and the subtotal. --}}
<div class="auth-checkout-cart">
    <div class="auth-checkout-cart-row">
        <div class="auth-checkout-cart-text">
            <p class="auth-checkout-count">{{ trans_choice('store.cart_count', $checkoutQuantity, ['count' => $checkoutQuantity]) }}</p>
            <p class="auth-checkout-subtotal">{{ $checkoutSubtotal }}</p>
        </div>
        <div class="auth-checkout-thumbs">
            @foreach ($checkoutLines->take(3) as $line)
                <img src="{{ $line->variant?->thumbnailUrl() ?? $line->product->thumbnailUrl() }}" alt="{{ $line->product->localizedName() }}" width="44" height="44">
            @endforeach
            @if ($checkoutLines->count() > 3)
                <span class="auth-checkout-thumbs-more">+{{ $checkoutLines->count() - 3 }}</span>
            @endif
        </div>
    </div>
    <p class="auth-checkout-note">
        {{ __('store.login_checkout_shipping') }}
        <a href="{{ localized_route('cart.show') }}">{{ __('store.login_checkout_edit_cart') }}</a>
    </p>
</div>
