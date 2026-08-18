@extends('layouts.app')

@section('title', 'Livraison & Retours — '.config('app.name'))
@section('meta_description', 'Transporteurs, délais, frais de port et procédure de retour ou de rétractation sur la boutique Armo Outdoor.')
@section('canonical', route('help.shipping-returns'))

@push('head')
    <link rel="stylesheet" href="{{ asset('css/help.css') }}">
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'WebPage',
            'name' => 'Livraison & Retours',
            'description' => 'Transporteurs, délais, frais de port et procédure de retour ou de rétractation sur la boutique Armo Outdoor.',
            'url' => route('help.shipping-returns'),
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
                    'name' => 'Livraison & Retours',
                    'item' => route('help.shipping-returns'),
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('content')
    <div class="container help-wrap">
        @include('help.partials.chrome', [
            'title' => 'Livraison & Retours',
            'lede' => 'Transporteurs, délais et procédure de retour, en un seul endroit.',
        ])

        <article>
            <section class="help-card" id="transporteurs" aria-labelledby="help-carriers-title">
                <h2 id="help-carriers-title">Transporteurs</h2>
                <p>
                    Les commandes sont expédiées en France métropolitaine avec Colissimo, Chronopost, Mondial Relay ou
                    Chronopost Shop2Shop, au choix, à domicile ou en point relais.
                </p>
                <ul class="help-logos">
                    <li class="help-logo">
                        <img src="{{ asset('images/carriers/colissimo.png') }}" alt="Colissimo" width="120" height="28">
                        <span>Colissimo</span>
                    </li>
                    <li class="help-logo">
                        <img src="{{ asset('images/carriers/chronopost.png') }}" alt="Chronopost" width="120" height="28">
                        <span>Chronopost</span>
                    </li>
                    <li class="help-logo">
                        <img src="{{ asset('images/carriers/mondialrelay.png') }}" alt="Mondial Relay" width="120" height="28">
                        <span>Mondial Relay</span>
                    </li>
                    <li class="help-logo">
                        <img src="{{ asset('images/carriers/chronopost.png') }}" alt="Chronopost Shop2Shop" width="120" height="28">
                        <span>Shop2Shop</span>
                    </li>
                </ul>
            </section>

            <section class="help-card" id="delais-frais" aria-labelledby="help-fees-title">
                <h2 id="help-fees-title">Délais &amp; frais de port</h2>
                <p>
                    Le délai et le prix exacts s'affichent au moment du paiement, selon le transporteur choisi.
                    @if ($freeShippingAmount)
                        La livraison est offerte dès {{ $freeShippingAmount }} d'achat.
                    @endif
                </p>
            </section>

            <section class="help-card" id="suivi" aria-labelledby="help-tracking-title">
                <h2 id="help-tracking-title">Suivi de commande</h2>
                <p>
                    Dès l'expédition, un numéro de suivi est ajouté à votre commande et reste consultable depuis
                    <a href="{{ route('orders.index') }}">Mes commandes</a> dans votre compte.
                </p>
            </section>

            <section class="help-card" id="retractation" aria-labelledby="help-withdrawal-title">
                <h2 id="help-withdrawal-title">Droit de rétractation</h2>
                <p>
                    Vous disposez de 14 jours francs à compter de la réception pour vous rétracter, hors exceptions
                    légales (produits scellés descellés, personnalisés ou périssables). La procédure complète et le
                    formulaire type figurent sur la page
                    <a href="{{ route('legal.withdrawal') }}">droit de rétractation</a>.
                </p>
            </section>

            <section class="help-card" id="remboursement" aria-labelledby="help-refund-title">
                <h2 id="help-refund-title">Remboursement</h2>
                <p>
                    Le remboursement intervient dans les 14 jours suivant la réception du produit retourné, ou la preuve
                    de son expédition.
                </p>
            </section>
        </article>

        <aside class="help-next">
            <div>
                <p class="help-next-title">Une question sur votre colis ?</p>
                <p class="help-next-text">Les réponses courtes sont aussi dans la FAQ.</p>
            </div>
            <a href="{{ route('faq') }}" class="btn btn-primary help-next-cta">Voir la FAQ</a>
        </aside>
    </div>
@endsection
