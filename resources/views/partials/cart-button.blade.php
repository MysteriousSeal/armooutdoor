@php
    $cartCount = $cartCount ?? 0;
@endphp
<a
    href="{{ localized_route('cart.show') }}"
    class="cart-btn {{ $class ?? '' }}"
    title="{{ __('store.cart') }}"
    aria-label="{{ __('store.cart') }}{{ $cartCount > 0 ? ' ('.$cartCount.')' : '' }}"
>
    <svg class="cart-btn-icon" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
        <path d="M6 6h15l-1.5 9h-12z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
        <path d="M6 6L5 3H2" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        <circle cx="9" cy="20" r="1.25" fill="currentColor"/>
        <circle cx="18" cy="20" r="1.25" fill="currentColor"/>
    </svg>
    <span class="cart-btn-label">{{ __('store.cart') }}</span>
    <span class="cart-badge" @if ($cartCount <= 0) hidden @endif>{{ $cartCount > 99 ? '99+' : $cartCount }}</span>
</a>
