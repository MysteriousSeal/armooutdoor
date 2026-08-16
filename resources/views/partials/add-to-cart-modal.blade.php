<dialog
    id="add-to-cart-modal"
    class="modal add-to-cart-modal"
    aria-labelledby="add-to-cart-modal-title"
    data-quantity-label="{{ __('store.added_to_cart_quantity') }}"
>
    <p class="modal-kicker" id="add-to-cart-modal-title">{{ __('store.added_to_cart_title') }}</p>

    <div class="add-to-cart-modal-body">
        <span class="add-to-cart-modal-media">
            <img id="add-to-cart-modal-image" src="" alt="" width="96" height="96">
        </span>
        <div class="add-to-cart-modal-info">
            <p class="add-to-cart-modal-name" id="add-to-cart-modal-name"></p>
            <p class="add-to-cart-modal-variant" id="add-to-cart-modal-variant" hidden>
                <span class="order-item-variant-value" id="add-to-cart-modal-variant-value"></span>
            </p>
            <p class="add-to-cart-modal-price">
                <span class="card-price-original" id="add-to-cart-modal-original" hidden></span>
                <span id="add-to-cart-modal-price"></span>
            </p>
            <p class="add-to-cart-modal-meta" id="add-to-cart-modal-meta"></p>
        </div>
    </div>

    <div class="modal-actions add-to-cart-modal-actions">
        <button type="button" class="btn btn-secondary" data-modal-close>{{ __('store.cart_continue') }}</button>
        <a href="{{ localized_route('cart.show') }}" class="btn btn-primary" id="add-to-cart-modal-cart-link">{{ __('store.go_to_cart') }}</a>
    </div>
</dialog>

<dialog
    id="stock-limit-modal"
    class="modal add-to-cart-modal stock-limit-modal"
    aria-labelledby="stock-limit-modal-title"
>
    <p class="modal-kicker" id="stock-limit-modal-title">{{ __('store.stock_limit_title') }}</p>
    <p class="stock-limit-modal-message">{{ __('store.stock_limit_message') }}</p>

    <div class="modal-actions stock-limit-modal-actions">
        <button type="button" class="btn btn-primary" data-modal-close>{{ __('store.close') }}</button>
    </div>
</dialog>
