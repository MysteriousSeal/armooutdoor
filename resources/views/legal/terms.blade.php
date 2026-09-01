@extends('layouts.app')

@section('title', __('store.legal_terms_title').' — '.config('app.name'))
@section('canonical', route('legal.terms'))

@push('head')
    {{-- The section itself has no page, so it is not a step a trail can
         name: Google wants an address for every element but the last. --}}
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@@type' => 'ListItem', 'position' => 1, 'name' => __('store.breadcrumb_home'), 'item' => route('home')],
                ['@@type' => 'ListItem', 'position' => 2, 'name' => __('store.legal_terms_title'), 'item' => route('legal.terms')],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('content')
    <div class="container legal-wrap">
        @include('legal.partials.chrome', [
            'title' => __('store.legal_terms_title'),
            'lede' => "Les règles de vente de la boutique : commande, paiement, livraison, garanties et litiges.",
            'page' => 'terms',
        ])

        <div class="legal-layout">
            @include('legal.partials.nav')

            <article class="legal-doc">
            @unless ($company->isComplete())
                <p class="legal-notice">
                    Certaines informations de cette page sont encore des espaces réservés. Complétez-les dans
                    <a href="{{ route('admin.settings.company.edit') }}">l'administration → Réglages → Company &amp; legal</a>.
                </p>
            @endunless

            <h2>Article 1 : Objet</h2>
            <p>
                Les présentes conditions générales de vente (CGV) régissent les ventes de produits réalisées sur le
                site Armo Outdoor entre {{ $company->value('company_name') }}, ci-après « le Vendeur », et toute personne physique
                effectuant un achat, ci-après « le Client ». Toute commande implique l'acceptation sans réserve des
                présentes CGV.
            </p>

            <h2>Article 2 : Capacité et produits réglementés</h2>
            <p>
                Certains produits du catalogue (notamment les répliques d'airsoft, leurs munitions et les couteaux)
                sont réservés aux personnes majeures. En commandant un produit signalé comme tel, le Client certifie
                être âgé d'au moins 18 ans ; une preuve de majorité pourra être exigée avant l'expédition, et la
                commande annulée et remboursée à défaut.
            </p>

            <h2>Article 3 : Produits</h2>
            <p>
                Les produits proposés à la vente sont ceux figurant sur le site au jour de la consultation, dans la
                limite des stocks disponibles. Le Vendeur se réserve le droit de modifier le catalogue à tout moment.
            </p>

            <h2>Article 4 : Prix</h2>
            <p>
                Les prix sont indiqués en euros, toutes taxes comprises (TTC). Les frais de livraison sont calculés
                lors de la commande, en fonction de l'adresse et du mode de livraison choisis, et affichés avant
                validation du paiement.
            </p>

            <h2>Article 5 : Commande</h2>
            <p>
                La commande est validée après sélection des produits, choix de l'adresse de livraison, du transporteur
                et du mode de paiement, puis confirmation par le Client. Un e-mail de confirmation est envoyé après
                validation.
            </p>

            <h2>Article 6 : Paiement</h2>
            <p>
                Le paiement s'effectue en ligne au moment de la commande, selon les moyens de paiement proposés
                au checkout. La commande n'est traitée qu'après confirmation du paiement.
            </p>

            <h2>Article 7 : Livraison</h2>
            <p>
                Les produits sont livrés en France métropolitaine uniquement, à l'adresse indiquée par le Client
                lors de la commande, ou retirés dans un point relais choisi par le Client. Les délais indiqués sont
                donnés à titre indicatif.
            </p>

            <h2>Article 8 : Droit de rétractation</h2>
            <p>
                Conformément à la loi, le Client dispose d'un délai de 14 jours pour exercer son droit de rétractation.
                Les modalités sont détaillées dans notre page dédiée au
                <a href="{{ route('legal.withdrawal') }}">{{ __('store.legal_withdrawal_title') }}</a>.
            </p>

            <h2>Article 9 : Garanties légales</h2>
            <p>
                Tous les produits fournis par le Vendeur bénéficient de la garantie légale de conformité et de la
                garantie contre les vices cachés, dans les conditions prévues par le Code civil et le Code de la
                consommation.
            </p>

            <h2>Article 10 : Responsabilité</h2>
            <p>
                Le Vendeur ne saurait être tenu responsable des dommages résultant d'une mauvaise utilisation des
                produits vendus, ou d'un cas de force majeure.
            </p>

            <h2>Article 11 : Données personnelles</h2>
            <p>
                Les données personnelles collectées lors de la commande sont traitées conformément à notre
                <a href="{{ route('legal.privacy') }}">{{ __('store.legal_privacy_title') }}</a>.
            </p>

            <h2>Article 12 : Litiges et médiation</h2>
            <p>
                Conformément aux articles L612-1 et suivants du Code de la consommation, le Client peut recourir
                gratuitement à un médiateur de la consommation en cas de litige non résolu avec le Vendeur.
                @if ($company->value('mediator_name') !== '')
                    Le médiateur compétent est {{ $company->value('mediator_name') }}@if ($company->value('mediator_url') !== '') :
                    <a href="{{ $company->value('mediator_url') }}" rel="noopener noreferrer">{{ $company->value('mediator_url') }}</a>@endif.
                @endif
            </p>
            <p>
                La plateforme européenne de règlement en ligne des litiges est accessible à l'adresse
                <a href="https://ec.europa.eu/consumers/odr" rel="noopener noreferrer">ec.europa.eu/consumers/odr</a>.
                À défaut de résolution amiable, les tribunaux français seront seuls compétents.
            </p>

            <h2>Article 13 : Droit applicable</h2>
            <p>Les présentes CGV sont soumises au droit français.</p>
        </article>
        </div>
    </div>
@endsection
