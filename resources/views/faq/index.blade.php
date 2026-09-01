@extends('layouts.app')

@php
    $faqs = [
        [
            'group' => 'Commandes & livraison',
            'slug' => 'commandes-livraison',
            'items' => [
                [
                    'q' => 'Quels sont les délais et frais de livraison ?',
                    'a' => 'Les commandes sont préparées puis expédiées en France métropolitaine avec Colissimo, Chronopost, Mondial Relay ou Chronopost Shop2Shop, à domicile ou en point relais. Le délai et le prix exacts s\'affichent au moment du paiement, selon le transporteur choisi.'
                        .($freeShippingAmount ? ' La livraison est offerte dès '.$freeShippingAmount.' d\'achat.' : ''),
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
            ],
        ],
        [
            'group' => 'Paiement',
            'slug' => 'paiement',
            'items' => [
                [
                    'q' => 'Quels moyens de paiement acceptez-vous ?',
                    'a' => 'Le paiement se fait par carte bancaire, au moment de la commande, via une connexion sécurisée. Le paiement par PayPal arrive bientôt.',
                ],
                [
                    'q' => 'Le paiement en plusieurs fois est-il possible ?',
                    'a' => 'Non, seul le paiement en une fois est proposé actuellement.',
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
                    'a' => 'Oui, certains produits sont en vente libre réservée aux plus de 18 ans ; une preuve de majorité peut être demandée au passage de la commande.',
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

@section('title', 'FAQ — Questions fréquentes — '.config('app.name'))
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
            'name' => 'FAQ — Questions fréquentes',
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
