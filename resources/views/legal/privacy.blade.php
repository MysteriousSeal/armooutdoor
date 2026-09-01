@extends('layouts.app')

@section('title', __('store.legal_privacy_title').' — '.config('app.name'))
@section('canonical', route('legal.privacy'))

@push('head')
    {{-- The section itself has no page, so it is not a step a trail can
         name: Google wants an address for every element but the last. --}}
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@@type' => 'ListItem', 'position' => 1, 'name' => __('store.breadcrumb_home'), 'item' => route('home')],
                ['@@type' => 'ListItem', 'position' => 2, 'name' => __('store.legal_privacy_title'), 'item' => route('legal.privacy')],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('content')
    <div class="container legal-wrap">
        @include('legal.partials.chrome', [
            'title' => __('store.legal_privacy_title'),
            'lede' => "Les données que nous collectons, pourquoi, combien de temps, et les droits que vous gardez dessus.",
            'page' => 'privacy',
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
                <li>Données de fréquentation : pages consultées et adresse IP, mesurées par le site lui-même.
                    Si vous l'acceptez via le bandeau cookies, l'identifiant de session déjà nécessaire au
                    fonctionnement du site (voir « Cookies » ci-dessous) sert aussi à regrouper les pages d'une
                    même visite ; en cas de refus, la mesure se poursuit sans cet identifiant.</li>
                <li>Pièce d'identité : uniquement si vous en déposez une depuis « Mes documents », pour prouver
                    votre majorité lorsque votre commande contient un article qui y est réservé. Le fichier est
                    chiffré dès sa réception, conservé hors de toute zone accessible depuis le site, et supprimé
                    dès qu'il a été vérifié. Le dépôt est facultatif : sans lui, seule la vente des articles
                    réservés aux majeurs est impossible.</li>
                <li>Mesure d'audience externe : si, et seulement si, vous l'acceptez via le bandeau cookies, les
                    pages consultées et quelques événements de la boutique (mise au panier, passage en caisse,
                    commande validée) sont transmis à deux sous-traitants de mesure d'audience : PostHog, sur ses
                    serveurs situés dans l'Union européenne, et Google Analytics (Google LLC), aux États-Unis.
                    Le transfert vers Google repose sur la décision d'adéquation de la Commission européenne du
                    10 juillet 2023 relative au cadre de protection des données UE–États-Unis. L'adresse IP est
                    anonymisée avant transmission à Google, et les signaux publicitaires ainsi que la
                    personnalisation des annonces sont désactivés. Aucune donnée que vous saisissez (nom,
                    adresse, coordonnées bancaires) n'est transmise à l'un ou à l'autre. En cas de refus, ou
                    tant que vous n'avez pas répondu, aucun de ces scripts n'est chargé et aucune donnée ne
                    leur est envoyée.</li>
            </ul>
            <p>Aucune donnée bancaire n'est stockée par nos soins ; le paiement est traité par un prestataire tiers sécurisé.</p>

            <h2>Finalités du traitement</h2>
            <ul>
                <li>Traitement et suivi des commandes, livraison, facturation.</li>
                <li>Gestion du compte client (adresses, liste de souhaits, historique).</li>
                <li>Réponse aux demandes de contact et service après-vente.</li>
                <li>Vérification de la majorité du Client avant l'expédition d'un article qui y est réservé,
                    lorsqu'une pièce d'identité a été déposée.</li>
                <li>Respect de nos obligations légales et comptables.</li>
                <li>Mesure d'audience du site, sur la base de notre intérêt légitime à connaître sa fréquentation.</li>
            </ul>

            <h2>Base légale</h2>
            <p>
                Les traitements reposent sur l'exécution du contrat de vente (commande, livraison), le respect
                d'obligations légales (facturation, garanties, interdiction de vente de certains articles aux
                mineurs) et, le cas échéant, le consentement du Client.
            </p>

            <h2>Destinataires des données</h2>
            <p>
                Les données sont destinées à {{ $company->value('company_name') }} et, le cas échéant, à ses prestataires techniques,
                dans la stricte limite nécessaire à l'exécution de la commande : le prestataire de paiement (Stripe),
                les transporteurs choisis à la commande (La Poste/Colissimo, Chronopost, Mondial Relay), le service
                de points relais (Sendcloud), la mesure d'audience (PostHog et Google Analytics, si vous l'avez acceptée) et l'hébergeur du
                site. Aucune donnée n'est vendue à des tiers.
            </p>
            <p>
                Les pièces d'identité font exception : elles ne sont transmises à aucun prestataire. Elles sont
                lisibles par les seuls responsables de la boutique, depuis une page qui leur est réservée, et
                chaque consultation est enregistrée dans un journal interne.
            </p>

            <h2>Durée de conservation</h2>
            <p>
                Les données liées à un compte client sont conservées pendant la durée de vie du compte. Les données
                liées à une commande sont conservées pendant la durée nécessaire au respect des obligations légales
                et comptables (jusqu'à 10 ans pour les documents comptables).
            </p>
            <p>
                Une pièce d'identité est supprimée dès qu'elle a été vérifiée, sans attendre : seuls subsistent le
                résultat de la vérification, sa date et, le cas échéant, la date de validité lue sur le document.
                Vous pouvez également supprimer une pièce déposée à tout moment depuis « Mes documents », y compris
                avant sa vérification.
            </p>

            <h2>Vos droits</h2>
            <p>
                Conformément au Règlement Général sur la Protection des Données (RGPD), vous disposez d'un droit
                d'accès, de rectification, d'effacement, de limitation, d'opposition et de portabilité de vos données.
                Vous pouvez exercer ces droits en écrivant à {{ $company->value('contact_email') }}.
            </p>
            <p>
                Vous disposez également du droit d'introduire une réclamation auprès de la Commission Nationale de
                l'Informatique et des Libertés (CNIL), à l'adresse
                <a href="https://www.cnil.fr" rel="noopener noreferrer">www.cnil.fr</a>.
            </p>

            <h2>Cookies</h2>
            <p>
                Le site utilise des cookies strictement nécessaires à son fonctionnement (panier, session de connexion,
                préférence d'affichage clair/sombre). Ces cookies ne nécessitent pas de consentement préalable au titre
                de la réglementation applicable. Si vous y consentez via le bandeau affiché à votre première visite,
                le même identifiant de session sert aussi, en interne, à regrouper les pages consultées au cours d'une
                même visite à des fins de mesure d'audience, et déclenche le chargement de PostHog et de Google
                Analytics, qui déposent leurs propres cookies de mesure d'audience, respectivement dans l'Union
                européenne et aux États-Unis. Aucun de ces cookies n'est utilisé à des fins de suivi
                publicitaire ou de profilage. En l'absence de consentement, aucun des deux n'est chargé. Vous pouvez revenir sur votre choix à tout
                moment via le lien « Cookies » en pied de page.
            </p>

            <h2>Sécurité</h2>
            <p>
                Nous mettons en œuvre les mesures techniques et organisationnelles appropriées pour protéger vos
                données contre tout accès non autorisé, perte ou divulgation. Le site est servi exclusivement en
                HTTPS et les mots de passe sont stockés sous forme chiffrée.
            </p>
            <p>
                Les pièces d'identité bénéficient de mesures renforcées : le fichier est chiffré avant d'être écrit,
                stocké hors de toute zone accessible depuis le site et sous un nom qui ne peut être deviné, son accès
                est limité aux seuls responsables de la boutique, chaque consultation est journalisée, et le fichier
                est détruit dès la vérification faite.
            </p>
        </article>
        </div>
    </div>
@endsection
