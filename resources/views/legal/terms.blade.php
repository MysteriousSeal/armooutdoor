@extends('layouts.app')

@section('title', __('store.legal_terms_title').' — '.config('app.name'))
@section('canonical', route('legal.terms'))

@section('content')
    <div class="container legal-wrap">
        @include('legal.partials.chrome', ['title' => __('store.legal_terms_title')])

        <article class="legal-doc">
            <p class="legal-notice">
                Ce document est un modèle à faire relire avant publication : il ne remplace pas l'avis d'un
                professionnel du droit.
                @unless ($company->isComplete())
                    Certaines informations sont encore des espaces réservés, à compléter dans
                    <a href="{{ route('admin.settings.company.edit') }}">l'administration → Réglages → Company &amp; legal</a>.
                @endunless
            </p>

            <h2>Article 1 — Objet</h2>
            <p>
                Les présentes conditions générales de vente (CGV) régissent les ventes de produits réalisées sur le
                site Armo Outdoor entre {{ $company->value('company_name') }}, ci-après « le Vendeur », et toute personne physique
                effectuant un achat, ci-après « le Client ». Toute commande implique l'acceptation sans réserve des
                présentes CGV.
            </p>

            <h2>Article 2 — Produits</h2>
            <p>
                Les produits proposés à la vente sont ceux figurant sur le site au jour de la consultation, dans la
                limite des stocks disponibles. Le Vendeur se réserve le droit de modifier le catalogue à tout moment.
            </p>

            <h2>Article 3 — Prix</h2>
            <p>
                Les prix sont indiqués en euros, toutes taxes comprises (TTC). Les frais de livraison sont calculés
                lors de la commande, en fonction de l'adresse et du mode de livraison choisis, et affichés avant
                validation du paiement.
            </p>

            <h2>Article 4 — Commande</h2>
            <p>
                La commande est validée après sélection des produits, choix de l'adresse de livraison, du transporteur
                et du mode de paiement, puis confirmation par le Client. Un e-mail de confirmation est envoyé après
                validation.
            </p>

            <h2>Article 5 — Paiement</h2>
            <p>
                Le paiement s'effectue en ligne, par carte bancaire ou PayPal, au moment de la commande. La commande
                n'est traitée qu'après confirmation du paiement.
            </p>

            <h2>Article 6 — Livraison</h2>
            <p>
                Les produits sont livrés à l'adresse indiquée par le Client lors de la commande, ou retirés dans un
                point relais choisi par le Client. Les délais indiqués sont donnés à titre indicatif.
            </p>

            <h2>Article 7 — Droit de rétractation</h2>
            <p>
                Conformément à la loi, le Client dispose d'un délai de 14 jours pour exercer son droit de rétractation.
                Les modalités sont détaillées dans notre page dédiée au
                <a href="{{ route('legal.withdrawal') }}">{{ __('store.legal_withdrawal_title') }}</a>.
            </p>

            <h2>Article 8 — Garanties légales</h2>
            <p>
                Tous les produits fournis par le Vendeur bénéficient de la garantie légale de conformité et de la
                garantie contre les vices cachés, dans les conditions prévues par le Code civil et le Code de la
                consommation.
            </p>

            <h2>Article 9 — Responsabilité</h2>
            <p>
                Le Vendeur ne saurait être tenu responsable des dommages résultant d'une mauvaise utilisation des
                produits vendus, ou d'un cas de force majeure.
            </p>

            <h2>Article 10 — Données personnelles</h2>
            <p>
                Les données personnelles collectées lors de la commande sont traitées conformément à notre
                <a href="{{ route('legal.privacy') }}">{{ __('store.legal_privacy_title') }}</a>.
            </p>

            <h2>Article 11 — Litiges et médiation</h2>
            <p>
                En cas de litige, le Client peut recourir à un médiateur de la consommation dans les conditions
                prévues par le Code de la consommation. À défaut de résolution amiable, les tribunaux français seront
                seuls compétents.
            </p>

            <h2>Article 12 — Droit applicable</h2>
            <p>Les présentes CGV sont soumises au droit français.</p>
        </article>
    </div>
@endsection
