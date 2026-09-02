@extends('layouts.app')

@php
    $faqs = [
        [
            'group' => 'Commandes & livraison',
            'slug' => 'commandes-livraison',
            'items' => [
                [
                    'q' => 'Quels sont les délais et frais de livraison ?',
                    'a' => 'Les commandes sont préparées puis expédiées en France métropolitaine avec Colissimo, Chronopost, Mondial Relay, Chronopost Shop2Shop ou, pour les articles petits et légers, en Lettre suivie, à domicile ou en point relais. Le délai et le prix exacts s\'affichent au moment du paiement, selon le transporteur choisi.'
                        .($freeShippingAmount ? ' La livraison est offerte dès '.$freeShippingAmount.' d\'achat.' : '')
                        .' Le détail vit sur la page Livraison & Retours.',
                    'a_html' => 'Les commandes sont préparées puis expédiées en France métropolitaine avec Colissimo, Chronopost, Mondial Relay, Chronopost Shop2Shop ou, pour les articles petits et légers, en Lettre suivie, à domicile ou en point relais. Le délai et le prix exacts s\'affichent au moment du paiement, selon le transporteur choisi.'
                        .($freeShippingAmount ? ' La livraison est offerte dès '.$freeShippingAmount.' d\'achat.' : '')
                        .' Le détail vit sur la page <a href="'.e(route('help.shipping-returns')).'">Livraison &amp; Retours</a>.',
                ],
                [
                    'q' => 'Livrez-vous en dehors de la France métropolitaine ?',
                    'a' => 'Non, la boutique ne livre actuellement qu\'en France métropolitaine.',
                ],
                [
                    'q' => 'Comment suivre ma commande ?',
                    'a' => 'Dès l\'expédition, un numéro de suivi est ajouté à votre commande et reste consultable depuis "Mes commandes" dans votre compte.',
                    'a_html' => 'Dès l\'expédition, un numéro de suivi est ajouté à votre commande et reste consultable depuis « <a href="'.e(route('orders.index')).'">Mes commandes</a> » dans votre compte.',
                ],
                [
                    'q' => 'Vos produits marqués « en stock » le sont-ils vraiment ?',
                    'a' => 'Oui. Tous les produits marqués en stock le sont réellement, et sont expédiés dans la journée.',
                ],
                [
                    'q' => 'Un produit « disponible chez le fournisseur » : puis-je le commander ?',
                    'a' => 'Oui. Nous ne l\'avons pas en stock, mais notre fournisseur en dispose : nous le commandons pour vous. Le délai d\'expédition estimé s\'affiche sur la fiche produit, puis sur votre commande.',
                ],
            ],
        ],
        [
            'group' => 'Paiement',
            'slug' => 'paiement',
            'items' => [
                [
                    'q' => 'Quels moyens de paiement acceptez-vous ?',
                    'a' => 'Le paiement se fait par carte bancaire au moment de la commande, traité par Stripe avec 3-D Secure via une connexion sécurisée. Le paiement par PayPal arrive bientôt. Plus de détails sur la page Paiement sécurisé.',
                    'a_html' => 'Le paiement se fait par carte bancaire au moment de la commande, traité par Stripe avec 3-D Secure via une connexion sécurisée. Le paiement par PayPal arrive bientôt. Plus de détails sur la page <a href="'.e(route('help.secure-payment')).'">Paiement sécurisé</a>.',
                ],
                [
                    'q' => 'Le paiement en plusieurs fois est-il possible ?',
                    'a' => 'La boutique ne le propose pas elle-même : le paiement se règle en une fois. Selon votre éligibilité, Stripe peut toutefois proposer un paiement échelonné au moment du règlement.',
                ],
                [
                    'q' => 'Comment utiliser un code de réduction ?',
                    'a' => 'Le code se saisit à l\'étape de paiement, avant de valider la commande. Les codes qui vous sont réservés apparaissent dans « Mes réductions » dans votre compte.',
                ],
                [
                    'q' => 'Où trouver ma facture ?',
                    'a' => 'Chaque commande a sa facture PDF, téléchargeable depuis "Mes commandes" dans votre compte dès l\'expédition.',
                    'a_html' => 'Chaque commande a sa facture PDF, téléchargeable depuis « <a href="'.e(route('orders.index')).'">Mes commandes</a> » dans votre compte dès l\'expédition.',
                ],
            ],
        ],
        [
            'group' => 'Retours & rétractation',
            'slug' => 'retours-retractation',
            'items' => [
                [
                    'q' => 'Puis-je retourner un produit ?',
                    'a' => 'Oui, vous disposez de 14 jours francs à compter de la réception pour vous rétracter, hors exceptions légales (produits scellés descellés, personnalisés ou périssables). Le détail de la procédure figure sur la page droit de rétractation.',
                    'a_html' => 'Oui, vous disposez de 14 jours francs à compter de la réception pour vous rétracter, hors exceptions légales (produits scellés descellés, personnalisés ou périssables). Le détail de la procédure figure sur la page <a href="'.e(route('legal.withdrawal')).'">droit de rétractation</a>.',
                ],
                [
                    'q' => 'Sous quel délai suis-je remboursé ?',
                    'a' => 'Le remboursement intervient dans les 14 jours suivant la réception du produit retourné, ou la preuve de son expédition.',
                ],
            ],
        ],
        [
            'group' => 'Compte & produits',
            'slug' => 'compte-produits',
            'items' => [
                [
                    'q' => 'Dois-je créer un compte pour commander ?',
                    'a' => 'Oui, un compte est nécessaire pour passer commande : il permet de garder vos adresses, de suivre vos commandes et de gérer votre liste de souhaits.',
                ],
                [
                    'q' => 'Certains produits sont-ils réservés aux adultes ?',
                    'a' => 'Oui, certains articles sont réservés aux plus de 18 ans. La commande se passe normalement ; avant l\'expédition, une preuve de majorité (carte d\'identité, passeport ou permis de conduire) peut être demandée, à envoyer depuis "Mes documents" dans votre compte. Le document est vérifié puis immédiatement supprimé : seule la vérification est conservée.',
                    'a_html' => 'Oui, certains articles sont réservés aux plus de 18 ans. La commande se passe normalement ; avant l\'expédition, une preuve de majorité (carte d\'identité, passeport ou permis de conduire) peut être demandée, à envoyer depuis « <a href="'.e(route('account.documents.index')).'">Mes documents</a> » dans votre compte. Le document est vérifié puis immédiatement supprimé : seule la vérification est conservée.',
                ],
                [
                    'q' => 'Qui peut laisser un avis sur un produit ?',
                    'a' => 'Seuls les clients ayant reçu le produit peuvent laisser un avis : chaque avis publié correspond à un achat réel.',
                ],
                [
                    'q' => 'Comment vous contacter pour une autre question ?',
                    'a' => 'Vous pouvez nous écrire directement depuis la page de contact ; nos coordonnées figurent aussi sur la page mentions légales.',
                    'a_html' => 'Vous pouvez nous écrire directement depuis la <a href="'.e(localized_route('contact.show')).'">page de contact</a> ; nos coordonnées figurent aussi sur la page <a href="'.e(route('legal.notice')).'">mentions légales</a>.',
                ],
            ],
        ],
    ];
@endphp

@section('title', 'Questions fréquentes — '.config('app.name'))
@section('meta_description', 'Livraison, paiement, retours, compte : retrouvez les réponses aux questions les plus fréquentes sur la boutique Armo Outdoor.')
@section('canonical', route('faq'))

@push('head')
    {{-- The FAQ is the landing page of the help section and wears its
         layout, so it needs the section's stylesheet as well as its own. --}}
    <link rel="stylesheet" href="{{ versioned_asset('css/help.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('css/faq.css') }}">
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'FAQPage',
            'inLanguage' => 'fr-FR',
            'name' => 'Questions fréquentes',
            'url' => route('faq'),
            'mainEntity' => collect($faqs)->flatMap(fn ($group) => $group['items'])->map(fn ($item) => [
                '@@type' => 'Question',
                'name' => $item['q'],
                'acceptedAnswer' => [
                    '@@type' => 'Answer',
                    'text' => $item['a'],
                ],
            ])->values()->all(),
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
                    // The same step the sibling pages point at, under the same
                    // name: this page is the section, not a page inside it.
                    'name' => 'Aide',
                    'item' => route('faq'),
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('content')
    <div class="container faq-wrap help-wrap">
        <nav class="breadcrumbs" aria-label="breadcrumb">
            <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            {{-- This page is Aide. The siblings link their section crumb here;
                 on the section's own page it is where you already are. --}}
            <span class="breadcrumbs-section">Aide</span>
        </nav>

        @include('partials.page-panel-header', [
            'kicker' => 'Aide',
            'title' => 'Foire aux questions',
            'lede' => 'Les réponses aux questions les plus fréquentes sur la livraison, le paiement, les retours et votre compte.',
            'meta' => null,
        ])

        <div class="help-layout">
            @include('help.partials.nav')

            <div class="help-main">

        <nav class="faq-nav" aria-label="Rubriques de la FAQ">
            @foreach ($faqs as $group)
                <a href="#{{ $group['slug'] }}">{{ $group['group'] }}</a>
            @endforeach
        </nav>

        @foreach ($faqs as $group)
            <section class="faq-group" id="{{ $group['slug'] }}" aria-labelledby="faq-{{ $group['slug'] }}">
                <h2 class="faq-heading" id="faq-{{ $group['slug'] }}">{{ $group['group'] }}</h2>
                <div class="faq-list">
                    @foreach ($group['items'] as $item)
                        <details class="faq-item">
                            <summary class="faq-question">
                                <span>{{ $item['q'] }}</span>
                            </summary>
                            <div class="faq-answer">
                                <p>{!! $item['a_html'] ?? e($item['a']) !!}</p>
                            </div>
                        </details>
                    @endforeach
                </div>
            </section>
        @endforeach

        <aside class="faq-contact">
            <div class="faq-contact-copy">
                <p class="faq-contact-title">Une autre question ?</p>
                <p class="faq-contact-text">Écrivez-nous, nos coordonnées sont sur la page mentions légales.</p>
            </div>
            <a href="{{ route('legal.notice') }}" class="btn btn-primary faq-contact-cta">Mentions légales</a>
        </aside>
            </div>
        </div>
    </div>
@endsection
