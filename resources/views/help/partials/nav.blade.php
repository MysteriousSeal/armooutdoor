{{-- Beside the page rather than above it: somebody reading about delivery is
     often one question away from wanting the payment page, and should not
     have to reach the bottom of this one to find out it exists. --}}
<aside class="help-aside">
    <nav class="help-nav" aria-label="Aide">
        <p class="help-nav-title">Aide</p>
        @foreach ([
            'faq' => 'FAQ',
            'help.shipping-returns' => 'Livraison & Retours',
            'help.secure-payment' => 'Paiement sécurisé',
            'contact.show' => 'Nous contacter',
        ] as $route => $label)
            <a
                href="{{ route($route) }}"
                class="{{ request()->routeIs($route) ? 'is-active' : '' }}"
                @if (request()->routeIs($route)) aria-current="page" @endif
            >{{ $label }}</a>
        @endforeach
    </nav>
</aside>
