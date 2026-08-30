{{-- Rendu seulement tant qu'aucun choix n'est posé ; le lien « Cookies »
     du pied de page efface le choix et recharge pour le faire réapparaître. --}}
@if (request()->cookie('cookie_consent') === null)
    <aside class="cookie-banner" data-cookie-banner role="dialog" aria-live="polite" aria-label="{{ __('store.cookie_banner_title') }}">
        <h2 class="cookie-banner-title">{{ __('store.cookie_banner_title') }}</h2>
        <p class="cookie-banner-text">
            {{ __('store.cookie_banner_text') }}
            <a href="{{ route('legal.privacy') }}">{{ __('store.cookie_banner_more') }}</a>
        </p>
        {{-- Refuser reste un vrai bouton, même taille et même rangée :
             l'accent sur Accepter ne doit pas le rendre moins accessible. --}}
        <div class="cookie-banner-actions">
            <button type="button" class="btn btn-secondary" data-cookie-choice="essential">
                {{ __('store.cookie_banner_decline') }}
            </button>
            <button type="button" class="btn btn-primary" data-cookie-choice="all">
                {{ __('store.cookie_banner_accept') }}
            </button>
        </div>
    </aside>
@endif
