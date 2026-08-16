@if ($discountCode)
    <div class="discount-code-applied">
        <div class="discount-code-applied-copy">
            <span class="order-discount-badge">{{ $discountCode->code }}</span>
            <span class="discount-code-applied-amount">-{{ format_euros($discountCents) }}</span>
        </div>
        <form method="POST" action="{{ localized_route('checkout.discount-code.destroy') }}" class="discount-code-remove-form">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-secondary">{{ __('store.discount_code_remove') }}</button>
        </form>
    </div>
@else
    <form method="POST" action="{{ localized_route('checkout.discount-code.store') }}" class="discount-code-form">
        @csrf
        <input
            type="text"
            name="code"
            class="form-control"
            placeholder="{{ __('store.discount_code_placeholder') }}"
            maxlength="40"
            autocomplete="off"
            spellcheck="false"
        >
        <button type="submit" class="btn btn-secondary">{{ __('store.discount_code_apply') }}</button>
    </form>
    @error('discount_code')
        <p class="form-error">{{ $message }}</p>
    @enderror
@endif
