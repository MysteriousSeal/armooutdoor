@extends('layouts.app')

@section('title', __('store.legal_privacy_title').' — '.config('app.name'))
@section('canonical', route('legal.privacy'))

@section('content')
    <div class="container legal-wrap">
        @include('legal.partials.chrome', ['title' => __('store.legal_privacy_title')])

        <article class="legal-doc">
            @unless ($company->isComplete())
                <p class="legal-notice">
                    Certaines informations de cette page sont encore des espaces réservés. Complétez-les dans
                    <a href="{{ route('admin.settings.company.edit') }}">l'administration → Réglages → Company &amp; legal</a>.
                </p>
            @endunless

            <h2>Responsable de traitement</h2>
            <p>
                Le responsable du traitement des données personnelles collectées sur ce site est {{ $company->value('company_name') }},
                {{ $company->value('address') }}, joignable à {{ $company->value('contact_email') }}.
            </p>

            <h2>Données collectées</h2>
            <p>Nous collectons les données suivantes, uniquement lorsqu'elles sont nécessaires :</p>
            <ul>
                <li>Identité : nom, prénom.</li>
                <li>Coordonnées : e-mail, téléphone, adresses de livraison et de facturation.</li>
                <li>Données de commande : produits achetés, historique de commandes.</li>
                <li>Données de connexion : identifiants de compte, mot de passe (stocké de façon chiffrée).</li>
                <li>Données de fréquentation : pages consultées et adresse IP, mesurées par le site lui-même —
                    sans transmission à un tiers. Si vous l'acceptez via le bandeau cookies, l'identifiant de
                    session déjà nécessaire au fonctionnement du site (voir « Cookies » ci-dessous) sert aussi à
                    regrouper les pages d'une même visite ; en cas de refus, la mesure se poursuit sans cet
                    identifiant.</li>
            </ul>
            <p>Aucune donnée bancaire n'est stockée par nos soins ; le paiement est traité par un prestataire tiers sécurisé.</p>

            <h2>Finalités du traitement</h2>
            <ul>
                <li>Traitement et suivi des commandes, livraison, facturation.</li>
                <li>Gestion du compte client (adresses, liste de souhaits, historique).</li>
                <li>Réponse aux demandes de contact et service après-vente.</li>
                <li>Respect de nos obligations légales et comptables.</li>
                <li>Mesure d'audience du site, sur la base de notre intérêt légitime à connaître sa fréquentation.</li>
            </ul>

            <h2>Base légale</h2>
            <p>
                Les traitements reposent sur l'exécution du contrat de vente (commande, livraison), le respect
                d'obligations légales (facturation, garanties) et, le cas échéant, le consentement du Client.
            </p>

            <h2>Destinataires des données</h2>
            <p>
                Les données sont destinées à {{ $company->value('company_name') }} et, le cas échéant, à ses prestataires techniques,
                dans la stricte limite nécessaire à l'exécution de la commande : le prestataire de paiement (Stripe),
                les transporteurs choisis à la commande (La Poste/Colissimo, Chronopost, Mondial Relay), le service
                de points relais (Sendcloud) et l'hébergeur du site. Aucune donnée n'est vendue à des tiers.
            </p>

            <h2>Durée de conservation</h2>
            <p>
                Les données liées à un compte client sont conservées pendant la durée de vie du compte. Les données
                liées à une commande sont conservées pendant la durée nécessaire au respect des obligations légales
                et comptables (jusqu'à 10 ans pour les documents comptables).
            </p>

            <h2>Vos droits</h2>
            <p>
                Conformément au Règlement Général sur la Protection des Données (RGPD), vous disposez d'un droit
                d'accès, de rectification, d'effacement, de limitation, d'opposition et de portabilité de vos données.
                Vous pouvez exercer ces droits en écrivant à {{ $company->value('contact_email') }}.
            </p>
            <p>
                Vous disposez également du droit d'introduire une réclamation auprès de la Commission Nationale de
                l'Informatique et des Libertés (CNIL) — <a href="https://www.cnil.fr" rel="noopener noreferrer">www.cnil.fr</a>.
            </p>

            <h2>Cookies</h2>
            <p>
                Le site utilise des cookies strictement nécessaires à son fonctionnement (panier, session de connexion,
                préférence d'affichage clair/sombre). Ces cookies ne nécessitent pas de consentement préalable au titre
                de la réglementation applicable. Si vous y consentez via le bandeau affiché à votre première visite,
                le même identifiant de session sert aussi, en interne, à regrouper les pages consultées au cours d'une
                même visite à des fins de mesure d'audience ; il n'est ni partagé ni utilisé à des fins de suivi
                publicitaire ou de profilage. Vous pouvez revenir sur votre choix à tout moment via le lien
                « Cookies » en pied de page.
            </p>

            <h2>Sécurité</h2>
            <p>
                Nous mettons en œuvre les mesures techniques et organisationnelles appropriées pour protéger vos
                données contre tout accès non autorisé, perte ou divulgation.
            </p>
        </article>
    </div>
@endsection
