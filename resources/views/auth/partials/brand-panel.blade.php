{{-- Beside the sign-in and sign-up forms on wide screens: the three facts a
     visitor checks before handing over an e-mail, the same ones as the
     footer strip, with the same threshold setting. No title: the wordmark is
     in the header. Hidden on mobile, where the visitor came for the form. --}}
@php($freeShipping = \App\Models\ShippingSetting::current()->freeShippingLabel())
<aside class="auth-brand">
    <ul class="auth-brand-facts">
        @if (filled($freeShipping))
            <li>
                <span class="auth-brand-fact-mark" aria-hidden="true">@include('partials.icon', ['name' => 'truck-fast', 'size' => 22])</span>
                <span>
                    <strong>{{ __('store.footer_ship_title', ['amount' => $freeShipping]) }}</strong>
                    <span>{{ __('store.footer_ship_note') }}</span>
                </span>
            </li>
        @endif
        <li>
            <span class="auth-brand-fact-mark" aria-hidden="true">@include('partials.icon', ['name' => 'shield-halved', 'size' => 22])</span>
            <span>
                <strong>{{ __('store.home_hero_pay_title') }}</strong>
                <span>{{ __('store.home_hero_pay_text') }}</span>
            </span>
        </li>
        <li>
            <span class="auth-brand-fact-mark" aria-hidden="true">@include('partials.icon', ['name' => 'box-open', 'size' => 22])</span>
            <span>
                <strong>{{ __('store.footer_returns_title') }}</strong>
                <span>{{ __('store.footer_returns_note') }}</span>
            </span>
        </li>
    </ul>
    <a href="{{ route('about') }}" class="auth-brand-more">En savoir plus sur la boutique</a>
</aside>
