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
                effectuant un achat, ci-après « le Client ». Le Vendeur est {{ $company->value('company_name') }},
                {{ $company->value('address') }}, SIRET {{ $company->value('siret') }}, joignable à
                {{ $company->value('contact_email') }} et au {{ $company->value('phone') }}. Toute commande implique
                l'acceptation sans réserve des présentes CGV.
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
                Les prix sont indiqués en euros, toutes taxes comprises. {{ $company->vatMention() }} : aucune TVA
                n'est facturée et les prix affichés sont les prix nets à payer. Les frais de livraison sont calculés
                lors de la commande, en fonction de l'adresse, du poids et du mode de livraison choisis, et affichés
                avant validation du paiement. Le Vendeur peut proposer la gratuité des frais de livraison à partir
                d'un montant d'achat et pour les modes de livraison indiqués sur le site ; le montant et les modes
                concernés sont ceux affichés au moment de la commande.
            </p>

            <h2>Article 5 : Commande</h2>
            <p>
                La commande est validée après sélection des produits, choix de l'adresse de livraison, du transporteur
                et du mode de paiement. Avant de payer, le Client accède à un récapitulatif détaillant les produits,
                leur prix, les frais de livraison et le total à payer, et dispose de la possibilité de corriger
                d'éventuelles erreurs. La validation du paiement vaut confirmation de la commande et emporte
                obligation de paiement. Un e-mail de confirmation est envoyé après validation.
            </p>
            <p>
                Conformément à l'article L213-1 du Code de la consommation, le Vendeur archive les contrats conclus
                pour un montant égal ou supérieur à 120 euros pendant dix ans à compter de la livraison, et en donne
                accès au Client sur simple demande adressée à {{ $company->value('contact_email') }}.
            </p>

            <h2>Article 6 : Paiement</h2>
            <p>
                Le paiement s'effectue en ligne au moment de la commande, par carte bancaire via notre prestataire
                de paiement Stripe. Aucune donnée bancaire n'est conservée par le Vendeur. La commande n'est traitée
                qu'après confirmation du paiement.
            </p>

            <h2>Article 7 : Livraison</h2>
            <p>
                Les produits sont livrés en France métropolitaine uniquement, à l'adresse indiquée par le Client
                lors de la commande, ou retirés dans un point relais choisi par le Client. Le délai de livraison est
                indiqué lors de la commande ; à défaut d'indication, le Vendeur livre au plus tard trente jours après
                la conclusion du contrat, conformément à l'article L216-1 du Code de la consommation.
            </p>
            <p>
                En cas de dépassement, le Client peut enjoindre au Vendeur de livrer dans un délai supplémentaire
                raisonnable, puis, à défaut d'exécution, résoudre le contrat par lettre recommandée ou par écrit sur
                un autre support durable (articles L216-2 et L216-3). Les sommes versées lui sont alors remboursées
                au plus tard dans les quatorze jours suivant la résolution.
            </p>
            <p>
                Conformément à l'article L216-4 du Code de la consommation, le risque de perte ou d'endommagement des
                produits est transféré au Client au moment où celui-ci, ou un tiers qu'il a désigné, prend
                physiquement possession des produits.
            </p>
            <p>
                Certains articles, notamment les cartouches de gaz sous pression, peuvent faire l'objet de
                restrictions d'acheminement propres au transporteur retenu. Le cas échéant, les modes de livraison
                disponibles pour ces articles sont restreints au moment de la commande.
            </p>

            <h2>Article 8 : Droit de rétractation</h2>
            <p>
                Conformément à la loi, le Client dispose d'un délai de 14 jours pour exercer son droit de rétractation.
                Les modalités sont détaillées dans notre page dédiée au
                <a href="{{ route('legal.withdrawal') }}">{{ __('store.legal_withdrawal_title') }}</a>.
            </p>

            <h2>Article 9 : Garanties légales</h2>
            <p>
                Tous les produits fournis par le Vendeur bénéficient de plein droit et sans supplément de prix,
                indépendamment de toute garantie commerciale, de la garantie légale de conformité et de la garantie
                des vices cachés.
            </p>
            <p>
                Au titre de la <strong>garantie légale de conformité</strong> (articles L217-3 et suivants du Code de
                la consommation), le Client dispose d'un délai de <strong>deux ans à compter de la délivrance</strong>
                du produit pour agir. Il peut choisir entre la réparation et le remplacement du bien, sous réserve des
                conditions de coût prévues à l'article L217-12, et est dispensé de rapporter la preuve du défaut de
                conformité pendant les vingt-quatre mois suivant la délivrance. La garantie légale de conformité
                s'applique indépendamment de toute garantie commerciale éventuellement consentie.
            </p>
            <p>
                Au titre de la <strong>garantie des vices cachés</strong> (articles 1641 et suivants du Code civil),
                le Client peut agir dans un délai de <strong>deux ans à compter de la découverte du vice</strong>, et
                choisir entre la résolution de la vente et une réduction du prix.
            </p>
            <p>
                Pour mettre en œuvre l'une de ces garanties, le Client écrit à {{ $company->value('contact_email') }}
                en indiquant son numéro de commande et la nature du défaut.
            </p>

            <h2>Article 10 : Responsabilité</h2>
            <p>
                Le Vendeur ne saurait être tenu responsable des dommages résultant d'une utilisation des produits non
                conforme à leur destination ou aux consignes fournies, ni d'un cas de force majeure. La présente clause
                ne limite en rien les garanties légales mentionnées à l'article 9, ni la responsabilité du Vendeur en
                cas de dommage corporel ou de faute lourde ou dolosive.
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
                À défaut de résolution amiable, le litige relève des juridictions françaises, sans préjudice du droit
                du Client de saisir à son choix la juridiction du lieu de son domicile ou celle du lieu où le Vendeur
                demeure.
            </p>

            <h2>Article 13 : Droit applicable</h2>
            <p>Les présentes CGV sont soumises au droit français.</p>
        </article>
        </div>
    </div>
@endsection
