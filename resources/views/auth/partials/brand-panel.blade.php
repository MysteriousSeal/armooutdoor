{{-- The shop's side of the auth pages: who you are signing in with.
     Hidden on mobile — the visitor came for the form. --}}
<aside class="auth-brand">
    <p class="auth-brand-kicker">{{ __('store.hero_kicker') }}</p>
    <p class="auth-brand-title">
        <span class="auth-brand-title-accent">Armo Outdoor</span>
    </p>
    <p class="auth-brand-pitch">
        Une boutique française à taille humaine, tenue par des passionnés de tir sportif.
        Un catalogue court, choisi pour servir au stand comme sur le terrain.
    </p>
    <ul class="auth-brand-points">
        @foreach ([
            'Des produits utiles, testés par des pratiquants',
            'Des prix justes toute l\'année, affichés TTC',
            'Une expédition rapide et suivie',
            'Une équipe en France, à votre écoute',
        ] as $point)
            <li>
                <span class="auth-brand-point-mark" aria-hidden="true"></span>
                <span>{{ $point }}</span>
            </li>
        @endforeach
    </ul>
    <a href="{{ route('about') }}" class="auth-brand-more">En savoir plus sur la boutique</a>
</aside>
