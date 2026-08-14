@extends('layouts.app')

@section('title', __('store.legal_notice_title').' — '.config('app.name'))
@section('canonical', route('legal.notice'))

@section('content')
    <div class="container">
        <nav class="breadcrumbs" aria-label="breadcrumb">
            <a href="{{ route('home') }}">{{ __('store.breadcrumb_home') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <span>{{ __('store.legal_notice_title') }}</span>
        </nav>

        <header class="page-header">
            <h2 class="page-title">{{ __('store.legal_notice_title') }}</h2>
        </header>

        <div class="legal-page">
            @unless ($company->isComplete())
                <p class="legal-notice">
                    Certaines informations de cette page sont encore des espaces réservés. Complétez-les dans
                    <a href="{{ route('admin.settings.company.edit') }}">l'administration → Réglages → Company &amp; legal</a>.
                </p>
            @endunless
            <p class="legal-updated">Dernière mise à jour : {{ now()->translatedFormat('d F Y') }}</p>

            <h2>Éditeur du site</h2>
            <p>
                Le site Armo Outdoor est édité par {{ $company->value('company_name') }}, {{ $company->value('legal_form') }}
                @if ($company->value('share_capital') !== '')
                    au capital de {{ $company->value('share_capital') }},
                @endif
                immatriculé(e) sous le numéro SIRET {{ $company->value('siret') }}.<br>
                Siège social : {{ $company->value('address') }}.<br>
                {{ $company->vatMention() }}.<br>
                Adresse e-mail : {{ $company->value('contact_email') }}.<br>
                Téléphone : {{ $company->value('phone') }}.
            </p>

            <h2>Directeur de la publication</h2>
            <p>{{ $company->value('publication_director') }}.</p>

            <h2>Hébergement</h2>
            <p>
                Le site est hébergé par {{ $company->value('host_name') }}, {{ $company->value('host_address') }},
                {{ $company->value('host_phone') }}.
            </p>

            <h2>Propriété intellectuelle</h2>
            <p>
                L'ensemble des éléments du site (textes, images, logos, mise en page, éléments graphiques) est protégé
                par le droit d'auteur et le droit des marques. Toute reproduction, représentation ou exploitation, totale
                ou partielle, sans autorisation préalable est interdite.
            </p>

            <h2>Données personnelles</h2>
            <p>
                Le traitement des données personnelles collectées sur le site est décrit dans notre
                <a href="{{ route('legal.privacy') }}">{{ __('store.legal_privacy_title') }}</a>.
            </p>

            <h2>Cookies</h2>
            <p>
                Le site utilise des cookies strictement nécessaires à son fonctionnement (panier, session, préférence
                d'affichage). Aucun cookie de mesure d'audience ou publicitaire n'est déposé sans consentement préalable.
            </p>

            <h2>Droit applicable et litiges</h2>
            <p>
                Le présent site et les présentes mentions légales sont soumis au droit français. En cas de litige, les
                tribunaux français seront seuls compétents.
            </p>
        </div>
    </div>
@endsection
