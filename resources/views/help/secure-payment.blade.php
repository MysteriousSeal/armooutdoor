@extends('layouts.app')

@section('title', 'Paiement sécurisé — '.config('app.name'))
@section('meta_description', 'Moyens de paiement acceptés et sécurité de vos données bancaires sur la boutique Armo Outdoor.')
@section('canonical', route('help.secure-payment'))

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/help.css') }}">
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'WebPage',
            'name' => 'Paiement sécurisé',
            'description' => 'Moyens de paiement acceptés et sécurité de vos données bancaires sur la boutique Armo Outdoor.',
            'url' => route('help.secure-payment'),
            'inLanguage' => 'fr-FR',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@@type' => 'ListItem',
                    'position' => 1,
                    'name' => __('store.breadcrumb_home'),
                    'item' => localized_route('home'),
                ],
                [
                    '@@type' => 'ListItem',
                    'position' => 2,
                    'name' => 'Paiement sécurisé',
                    'item' => route('help.secure-payment'),
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('content')
    <div class="container help-wrap">
        @include('help.partials.chrome', [
            'title' => 'Paiement sécurisé',
            'lede' => 'Moyens de paiement acceptés et sécurité de vos données.',
        ])

        <div class="help-layout">
            @include('help.partials.nav')

            <div class="help-main">
            <article>
            <section class="help-card" id="moyens-de-paiement" aria-labelledby="help-methods-title">
                <h2 id="help-methods-title">Moyens de paiement</h2>
                <p>
                    Le paiement se fait par carte bancaire, en une seule fois, au moment de la commande.
                    Le paiement par PayPal arrive bientôt.
                </p>
                <ul class="help-logos help-logos--two">
                    <li class="help-logo">
                        <img src="{{ asset('images/payments/cb.png') }}" alt="Carte bancaire" width="120" height="28">
                        <span>Carte bancaire</span>
                    </li>
                    <li class="help-logo help-logo--soon">
                        <img src="{{ asset('images/payments/paypal.png') }}" alt="PayPal" width="120" height="28">
                        <span>PayPal <span class="help-logo-soon-chip">{{ __('store.payment_paypal_soon') }}</span></span>
                    </li>
                </ul>
            </section>

            <section class="help-card" id="securite" aria-labelledby="help-security-title">
                <h2 id="help-security-title">Sécurité de vos données</h2>
                <p>
                    Le paiement par carte est traité par Stripe, prestataire de paiement certifié, sur une connexion
                    chiffrée, avec authentification 3-D Secure lorsque votre banque la demande. Vos coordonnées
                    bancaires ne transitent jamais par nos serveurs et ne sont ni stockées ni consultables par la
                    boutique.
                </p>
            </section>

            <section class="help-card" id="debit" aria-labelledby="help-debit-title">
                <h2 id="help-debit-title">Quand suis-je débité ?</h2>
                <p>
                    Le montant est débité au moment de la validation de la commande.
                </p>
            </section>

            <section class="help-card" id="facture" aria-labelledby="help-invoice-title">
                <h2 id="help-invoice-title">Facture</h2>
                <p>
                    Une facture est disponible pour chaque commande, téléchargeable depuis
                    <a href="{{ route('orders.index') }}">Mes commandes</a> dans votre compte, au moment de
                    l'expédition de la commande ou lors du remboursement.
                </p>
            </section>
        </article>

        <aside class="help-next">
            <div>
                <p class="help-next-title">Une autre question ?</p>
                <p class="help-next-text">Les réponses courtes sont aussi dans la FAQ.</p>
            </div>
            <a href="{{ route('faq') }}" class="btn btn-primary help-next-cta">Voir la FAQ</a>
        </aside>
            </div>
        </div>
    </div>
@endsection
