@if ($discountCode)
    <div class="discount-code-applied">
        <div class="discount-code-applied-copy">
            <span class="order-discount-badge">{{ $discountCode->code }}</span>
            @if ($discountCode->isFreeRelayShipping())
                {{-- No money amount: it is worth whatever the relay carrier
                     costs, and only once one is chosen. --}}
                <span class="discount-code-applied-amount">{{ __('store.discount_code_free_relay_label') }}</span>
                <span class="discount-code-applied-hint">{{ __('store.discount_code_free_relay_hint') }}</span>
            @else
                <span class="discount-code-applied-amount">-{{ format_euros($discountCents) }}</span>
            @endif
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

    {{-- The homepage's welcome code, one click away. Only passed in when
         applying it would succeed, and posted through the same form class so
         it goes through the same AJAX path as a typed code. --}}
    @if ($suggestedCode ?? null)
        <form method="POST" action="{{ localized_route('checkout.discount-code.store') }}" class="discount-code-form discount-code-suggested">
            @csrf
            <input type="hidden" name="code" value="{{ $suggestedCode->code }}">
            {{-- The banner's price tag and rust, so the customer recognises
                 the offer they saw on the way in. --}}
            <svg class="discount-code-suggested-icon" viewBox="0 0 24 24" width="26" height="26" aria-hidden="true" focusable="false">
                <path d="M3.5 12.6V4.5a1 1 0 0 1 1-1h8.1l8 8a1.4 1.4 0 0 1 0 2l-7.1 7.1a1.4 1.4 0 0 1-2 0l-8-8Z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                <circle cx="8.2" cy="8.2" r="1.6" fill="none" stroke="currentColor" stroke-width="1.5"/>
            </svg>
            <div class="discount-code-suggested-copy">
                <p class="discount-code-suggested-title">{!! __('store.discount_code_suggested', ['amount' => '<b class="discount-code-suggested-amount">'.e($suggestedCode->customerLabel()).'</b>']) !!}</p>
                <p class="discount-code-suggested-note">{!! __('store.discount_code_suggested_note', ['code' => '<code class="discount-code-suggested-code">'.e($suggestedCode->code).'</code>']) !!}</p>
            </div>
            <button type="submit" class="btn btn-primary discount-code-suggested-btn">{{ __('store.discount_code_suggested_apply') }}</button>
        </form>
    @endif
@endif
