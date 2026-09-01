{{-- Aside rather than inside the document: the four pages are siblings, and
     a reader who lands on the CGV looking for the return policy should see
     where it is without scrolling the CGV to find out. --}}
<aside class="legal-aside">
    <nav class="legal-nav" aria-label="{{ __('store.legal_kicker') }}">
        <p class="legal-nav-title">{{ __('store.legal_kicker') }}</p>
        @foreach ([
            'legal.terms' => 'store.legal_terms_nav',
            'legal.notice' => 'store.legal_notice_nav',
            'legal.privacy' => 'store.legal_privacy_nav',
            'legal.withdrawal' => 'store.legal_withdrawal_nav',
        ] as $route => $label)
            <a
                href="{{ route($route) }}"
                class="{{ request()->routeIs($route) ? 'is-active' : '' }}"
                @if (request()->routeIs($route)) aria-current="page" @endif
            >{{ __($label) }}</a>
        @endforeach
    </nav>
</aside>
