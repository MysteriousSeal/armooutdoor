@extends('layouts.app')

@php
    // The four leaks, in the order they happened. `short` names a leak on the
    // record, `phrase` finishes the sentence « Avec … : ».
    $incidents = [
        ['key' => 'fftir', 'short' => 'FFTir', 'label' => 'Une licence à la FFTir', 'hint' => 'fuite d\'octobre 2025', 'phrase' => 'une licence FFTir'],
        ['key' => 'sia', 'short' => 'SIA', 'label' => 'Des armes inscrites au SIA', 'hint' => 'extraction révélée en mars 2026', 'phrase' => 'des armes au SIA'],
        ['key' => 'lavaux', 'short' => 'Lavaux', 'label' => 'Une commande à l\'Armurerie Lavaux', 'hint' => 'commandes depuis 2025, fuite d\'août 2026', 'phrase' => 'une commande chez Lavaux'],
        ['key' => 'naturabuy', 'short' => 'NaturaBuy', 'label' => 'Un compte NaturaBuy', 'hint' => 'fuite du 10 septembre 2026', 'phrase' => 'un compte NaturaBuy'],
    ];

    // One line of the record per field, and per leak what the sources say:
    // x exposed, o not exposed, ? not stated. « Non précisé » is kept rather
    // than guessed: a guide that fills a silence with a reassurance is wrong
    // in the one direction that costs the reader.
    $fields = [
        ['label' => 'Nom et prénom', 'fftir' => 'x', 'sia' => 'x', 'lavaux' => 'x', 'naturabuy' => 'x'],
        ['label' => 'Date et lieu de naissance', 'fftir' => 'x', 'sia' => '?', 'lavaux' => '?', 'naturabuy' => '?'],
        ['label' => 'Adresse postale', 'fftir' => 'x', 'sia' => 'x', 'lavaux' => 'x', 'naturabuy' => 'x'],
        ['label' => 'Téléphone', 'fftir' => 'x', 'sia' => '?', 'lavaux' => 'x', 'naturabuy' => 'x'],
        ['label' => 'Adresse e-mail', 'fftir' => 'x', 'sia' => 'x', 'lavaux' => 'o', 'naturabuy' => 'x'],
        ['label' => 'Numéro de licence', 'fftir' => 'x', 'sia' => '?', 'lavaux' => '?', 'naturabuy' => '?'],
        ['label' => 'Mot de passe', 'fftir' => '?', 'sia' => '?', 'lavaux' => 'o', 'naturabuy' => 'x', 'note' => ['naturabuy' => 'chiffré']],
        ['label' => 'Achats et transactions', 'fftir' => '?', 'sia' => 'x', 'lavaux' => 'x', 'naturabuy' => 'o'],
        ['label' => 'Armes détenues', 'fftir' => 'o', 'sia' => 'x', 'lavaux' => '?', 'naturabuy' => '?'],
        ['label' => 'Numéros de série', 'fftir' => 'o', 'sia' => '?', 'lavaux' => 'o', 'naturabuy' => 'o'],
        ['label' => 'Numéro SIA', 'fftir' => '?', 'sia' => '?', 'lavaux' => 'o', 'naturabuy' => 'o'],
        ['label' => 'Données bancaires', 'fftir' => 'o', 'sia' => '?', 'lavaux' => 'o', 'naturabuy' => 'o'],
    ];

    $stateLabels = ['x' => 'Oui', 'o' => 'Non', '?' => 'Non précisé'];
    $stateClasses = ['x' => 'is-exposed', 'o' => 'is-safe', '?' => 'is-unknown'];

    // What to do, each card naming the leaks it answers; « all » is for
    // everyone, whatever they tick.
    $checks = [
        ['for' => 'all', 'title' => 'Personne ne viendra chercher vos armes', 'meta' => 'Ni appel, ni visite', 'body' => 'La police, la gendarmerie et la douane ne vous appelleront jamais et ne viendront jamais spontanément chez vous pour récupérer vos armes après une fuite. Si quelqu\'un se présente, n\'ouvrez pas : demandez son matricule à sept chiffres, notez le numéro qui vous a appelé, et composez le 17 pour vérifier.'],
        ['for' => 'naturabuy', 'title' => 'Changez votre mot de passe NaturaBuy', 'meta' => 'En tapant l\'adresse vous-même', 'body' => 'NaturaBuy impose une réinitialisation à la reconnexion. Faites-la depuis le site ouvert par vos soins, jamais depuis un lien reçu par e-mail ou par SMS : les faux messages de réinitialisation arrivent justement dans les semaines qui suivent une fuite.'],
        ['for' => 'naturabuy', 'title' => 'Et partout où il servait', 'meta' => 'Un mot de passe par site', 'body' => 'Un mot de passe qui fuite vaut pour tous les sites où il était le même, même chiffré. Changez-le partout ; un gestionnaire de mots de passe évite que cela se reproduise.'],
        ['for' => 'fftir sia naturabuy', 'title' => 'Méfiez-vous des e-mails qui parlent de vos armes', 'meta' => 'Votre adresse e-mail circule', 'body' => 'Avec votre nom et votre adresse e-mail, un message qui évoque votre licence, vos armes ou une commande devient très crédible. On ne clique pas depuis le message : on ouvre le site par ses propres moyens.'],
        ['for' => 'fftir', 'title' => 'Votre date de naissance circule aussi', 'meta' => 'Fuite FFTir', 'body' => 'Elle sert souvent à vérifier une identité au téléphone. Un interlocuteur qui vous la cite pour paraître légitime ne prouve rien : il cite ce qui a fuité.'],
        ['for' => 'lavaux', 'title' => 'Vos achats sont connus', 'meta' => 'Fuite Lavaux', 'body' => 'Le détail de vos commandes a fuité avec votre adresse et votre téléphone. Un appel ou un courrier qui cite précisément un achat n\'est pas une preuve de légitimité non plus.'],
        ['for' => 'sia', 'title' => 'Vos armes sont liées à votre nom', 'meta' => 'Extraction SIA', 'body' => 'C\'est le seul des quatre fichiers qui associe des armes, avec leur type, leur modèle et leur catégorie, à des détenteurs. Le service central des armes a contacté les personnes concernées. Ne supprimez pas votre compte SIA pour autant : il reste obligatoire.'],
        ['for' => 'all', 'title' => 'Rangez comme si l\'information circulait', 'meta' => 'Coffre, pièce retirée, munitions à part', 'body' => 'Les catégories A et B se conservent dans un coffre-fort ou une armoire forte, la catégorie C selon l\'une des solutions de l\'article R314-4, et les munitions hors d\'accès libre. La fuite ne change pas la règle ; elle la rend concrète.'],
        ['for' => 'all', 'title' => 'Signalez ce qui vous paraît anormal', 'meta' => 'Le 17 en cas d\'urgence', 'body' => 'Un repérage, un appel étrange, une visite suspecte : signalez-le à la police ou à la gendarmerie. Un fait isolé peut être la pièce qui manquait à une enquête.'],
    ];

    $faq = [
        ['Comment savoir si mes données font partie de ces fuites ?', 'Les organismes ont écrit aux personnes concernées : la FFTir à ses licenciés, le service central des armes aux détenteurs dont les armes figuraient dans l\'extraction du SIA, NaturaBuy à ses membres le 11 septembre 2026. Si vous étiez licencié, inscrit, client ou membre pendant la période, partez du principe que vous en faites partie : les réflexes de cette page ne coûtent rien si ce n\'est pas le cas.'],
        ['Des faux policiers peuvent-ils vraiment venir chez moi ?', 'Oui, c\'est arrivé : quelques jours après le piratage de la FFTir, un faux policier s\'est introduit chez un tireur, et des repérages ont été signalés. Les forces de l\'ordre ne viennent jamais spontanément récupérer des armes après une fuite. Avant d\'ouvrir, demandez le matricule à sept chiffres de votre visiteur et appelez le 17 pour le vérifier.'],
        ['Mes armes sont-elles désormais connues des cambrioleurs ?', 'Un seul des quatre fichiers relie des armes à des noms : l\'extraction du SIA, soit 62 511 armes avec leur type, leur modèle et leur catégorie. Le ministre de l\'Intérieur y a rattaché 20 à 30 cambriolages, et un suspect a été interpellé en Vendée en avril 2026. Les trois autres fichiers ne listent pas vos armes, mais une licence ou un compte spécialisé à côté d\'une adresse suffit à désigner un domicile probable.'],
        ['Dois-je supprimer mon compte SIA ?', 'Non. Depuis le 1er janvier 2025, un compte SIA est obligatoire pour acheter, vendre ou faire réviser une arme chez un armurier, et pour les chasseurs et les licenciés qui détiennent des armes. La fuite est passée par un compte d\'armurier compromis, pas par le vôtre, et supprimer votre compte n\'effacerait rien de ce qui a déjà été extrait.'],
        ['Mon mot de passe NaturaBuy était chiffré : dois-je quand même le changer ?', 'Oui. Un mot de passe chiffré peut être attaqué une fois le fichier copié, et NaturaBuy impose de toute façon une réinitialisation à la reconnexion. Changez-le en ouvrant le site vous-même, et changez-le aussi partout où vous utilisiez le même.'],
        ['Faut-il porter plainte ou saisir la CNIL ?', 'Si vous êtes victime d\'une visite, d\'une escroquerie ou d\'un cambriolage, portez plainte auprès de la police ou de la gendarmerie. La CNIL, elle, reçoit en ligne les plaintes contre un organisme qui ne répond pas à vos demandes sur vos données : savoir ce qu\'il conserve, ou en demander la suppression.'],
        ['Et armooutdoor.fr, a-t-il été touché ?', 'Aucune de ces quatre fuites ne concerne notre boutique en ligne. Nos paiements passent par Stripe et nous ne conservons aucune donnée bancaire ; ce que nous gardons, et combien de temps, est détaillé dans notre politique de confidentialité. Les commandes passées chez nous sur NaturaBuy, en revanche, relèvent de la plateforme NaturaBuy.'],
    ];

    $sources = [
        ['label' => 'FFTir, communication relative à un incident de sécurité (dates, données, consignes)', 'url' => 'https://www.fftir.org/communication-relative-a-un-incident-de-securite/', 'host' => 'fftir.org'],
        ['label' => 'Cybermalveillance.gouv.fr, violation de données personnelles de la FFTir', 'url' => 'https://www.cybermalveillance.gouv.fr/tous-nos-contenus/actualites/violation-de-donnees-personnelles-fftir-202511', 'host' => 'cybermalveillance.gouv.fr'],
        ['label' => 'Europe 1, la Fédération française de tir piratée', 'url' => 'https://www.europe1.fr/societe/federation-francaise-de-tir-piratee-linquietude-des-tireurs-sportifs-apres-le-vol-de-donnees-personnelles-870393', 'host' => 'europe1.fr'],
        ['label' => 'SafeShooting, piratage du SIA : fuite de données, ce que l\'on sait', 'url' => 'https://safeshooting.fr/piratage-sia-fuite-donnees-armes-france/', 'host' => 'safeshooting.fr'],
        ['label' => 'Leto, Armurerie Lavaux : fuite de données et risque pour les détenteurs', 'url' => 'https://www.leto.legal/news/armurerie-lavaux-fuite-donnees-detenteurs-armes-2026', 'host' => 'leto.legal'],
        ['label' => 'SafeShooting, cyberattaque NaturaBuy : quelles données ont été volées', 'url' => 'https://safeshooting.fr/cyberattaque-naturabuy-fuite-donnees-2026/', 'host' => 'safeshooting.fr'],
        ['label' => 'Cyberattaque.org, NaturaBuy victime d\'une cyberattaque', 'url' => 'https://www.cyberattaque.org/naturabuy-victime-dune-cyberattaque-un-risque-pour-les-detenteurs-darmes/', 'host' => 'cyberattaque.org'],
        ['label' => 'Préfecture de l\'Isère, inscription obligatoire au SIA pour les détenteurs d\'armes', 'url' => 'https://www.isere.gouv.fr/Actualites/Espace-presse/Communiques-de-presse/Inscription-obligatoire-au-SIA-pour-les-detenteurs-d-armes-avant-le-31-decembre-2024', 'host' => 'isere.gouv.fr'],
        ['label' => 'Code de la sécurité intérieure, articles R314-1 à R314-11 (conservation des armes et des munitions)', 'url' => 'https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000025503132/LEGISCTA000029655363/', 'host' => 'legifrance.gouv.fr'],
        ['label' => 'CNIL, adresser une plainte', 'url' => 'https://www.cnil.fr/fr/adresser-une-plainte', 'host' => 'cnil.fr'],
        ['label' => 'UFA, cyber attaques : les armes pour cibles', 'url' => 'https://armes-ufa.com/spip.php?article4081=', 'host' => 'armes-ufa.com'],
    ];

    $description = 'Détenteur d\'arme : ce que les fuites FFTir, SIA, Lavaux et NaturaBuy ont exposé, les faux policiers, et vos réflexes compte par compte.';
@endphp

@section('title', 'Détenteur d\'arme : protéger ses données et son domicile - Armo Outdoor')
@section('meta_description', $description)
@section('og_type', 'article')
@section('canonical', route('guides.donnees'))

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/categories.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('css/guides.css') }}">
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'Article',
            'headline' => 'Détenteur d\'arme : protéger ses données et son domicile',
            'description' => $description,
            'mainEntityOfPage' => route('guides.donnees'),
            'inLanguage' => 'fr-FR',
            'image' => versioned_asset(\App\Support\Guides::byRoute('guides.donnees')['image'] ?? 'images/hero.webp'),
            'datePublished' => \App\Support\Guides::byRoute('guides.donnees')['published'],
            'dateModified' => \App\Support\Guides::byRoute('guides.donnees')['updated'],
            'author' => \App\Support\OrganizationSchema::reference(),
            'publisher' => \App\Support\OrganizationSchema::reference(),
            'citation' => collect($sources)->map(fn (array $source): array => [
                '@@type' => 'CreativeWork',
                'name' => $source['label'],
                'url' => $source['url'],
            ])->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'FAQPage',
            'mainEntity' => collect($faq)->map(fn (array $qa): array => [
                '@@type' => 'Question',
                'name' => $qa[0],
                'acceptedAnswer' => ['@@type' => 'Answer', 'text' => $qa[1]],
            ])->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@@type' => 'ListItem', 'position' => 1, 'name' => __('store.breadcrumb_home'), 'item' => localized_route('home')],
                ['@@type' => 'ListItem', 'position' => 2, 'name' => 'Guides', 'item' => route('guides.index')],
                ['@@type' => 'ListItem', 'position' => 3, 'name' => 'Protéger ses données', 'item' => route('guides.donnees')],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('content')
    <div class="container glab glab--donnees">
        @include('guides.partials.hero', [
            'crumb' => 'Protéger ses données',
            'kicker' => 'Sécurité',
            'title' => 'Détenteur d\'arme : protéger ses données et son domicile',
            'lede' => 'En moins d\'un an, quatre fichiers qui touchent les tireurs, les chasseurs et les détenteurs d\'armes ont fuité : la Fédération française de tir, le SIA, l\'Armurerie Lavaux et NaturaBuy. On ne rattrape pas un fichier qui a fuité. Ce guide dit ce qui en est sorti, pourquoi c\'est votre adresse plus que vos armes qui intéresse, et ce qui reste sous votre contrôle : cochez vos comptes, la page vous dit quoi faire.',
            'tags' => ['FFTir', 'SIA', 'NaturaBuy'],
        ])

        <nav class="glab-plan" aria-label="Plan du guide">
            <p class="glab-plan-kicker">Plan</p>
            <div class="glab-plan-links">
                <a href="#donnees-concerne-title">Suis-je concerné ?</a>
                <a href="#donnees-fuites-title">Les quatre fuites</a>
                <a href="#donnees-tableau-title">Champ par champ</a>
                <a href="#donnees-adresse-title">Pourquoi votre adresse</a>
                <a href="#donnees-idees-title">Idées reçues</a>
                <a href="#donnees-naturabuy-title">NaturaBuy</a>
                <a href="#donnees-sia-title">Le SIA</a>
                <a href="#donnees-faq-title">Questions</a>
                <a href="#donnees-sources-title">Sources</a>
            </div>
        </nav>

        <p class="glab-warning">
            <strong class="glab-warning-label">Si quelqu'un vient chercher vos armes</strong>
            La police, la gendarmerie et la douane ne vous appelleront jamais et ne viendront
            jamais spontanément chez vous pour récupérer vos armes après une fuite de données.
            N'ouvrez pas, ne montrez rien, et composez le 17.
        </p>

        <section class="glab-section" data-glab-fuites aria-labelledby="donnees-concerne-title">
            <h2 class="glab-title" id="donnees-concerne-title">Suis-je <span class="glab-title-accent">concerné ?</span></h2>
            <p class="glab-lede">
                Cochez ce qui vous correspond. La fiche montre ce qui a fuité à votre sujet,
                la liste dessous ce qu'il faut faire, dans cet ordre. Rien n'est envoyé :
                tout se passe dans cette page.
            </p>

            <div class="glab-steps">
                <fieldset class="glab-step">
                    <legend>01 · Vos comptes</legend>
                    <div class="glab-chips glab-chips--stacked glab-checks">
                        @foreach ($incidents as $incident)
                            <label>
                                <input
                                    type="checkbox"
                                    value="{{ $incident['key'] }}"
                                    data-glab-incident
                                    data-glab-short="{{ $incident['short'] }}"
                                    data-glab-phrase="{{ $incident['phrase'] }}"
                                >
                                <span>
                                    {{ $incident['label'] }}
                                    <small>{{ $incident['hint'] }}</small>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                {{-- The record, as a leaked file holds it. Neutral until something
                     is ticked; the full answer for every leak is the table below. --}}
                <dl class="glab-fiche" aria-label="Ce qui a fuité à votre sujet">
                    <div class="glab-fiche-head">
                        <span>Votre fiche</span>
                        <span>Exposé ?</span>
                    </div>
                    @foreach ($fields as $field)
                        <div
                            class="glab-fiche-row"
                            data-glab-field
                            @foreach ($incidents as $incident)
                                data-{{ $incident['key'] }}="{{ $field[$incident['key']] }}"
                            @endforeach
                            @foreach ($field['note'] ?? [] as $key => $note)
                                data-note-{{ $key }}="{{ $note }}"
                            @endforeach
                        >
                            <dt>{{ $field['label'] }}</dt>
                            <dd>
                                <span class="glab-fiche-state" data-glab-state>·</span>
                                <span class="glab-fiche-from" data-glab-from></span>
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            <div class="glab-reco">
                <p class="glab-reco-label">Vos réflexes</p>
                <p class="glab-reco-resume" data-glab-resume data-glab-empty="Cochez ce qui vous concerne. Ces réflexes valent pour tous :" aria-live="polite">Pour chacune des quatre fuites :</p>
                {{-- Every card rendered server-side, so the page reads in full
                     without script; the script keeps the ones that apply. --}}
                <ol class="glab-reco-list">
                    @foreach ($checks as $check)
                        <li data-glab-for="{{ $check['for'] }}">
                            <span class="glab-reco-rank">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <div class="glab-reco-head">
                                <h3>{{ $check['title'] }}</h3>
                                <span class="glab-reco-meta">{{ $check['meta'] }}</span>
                            </div>
                            <p>{{ $check['body'] }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        <section class="glab-section" aria-labelledby="donnees-fuites-title">
            <h2 class="glab-title" id="donnees-fuites-title">Quatre fuites <span class="glab-title-accent">en un an</span></h2>
            <p class="glab-lede">
                Une fédération, un registre d'État, une armurerie en ligne et une place de
                marché. Dans l'ordre où elles sont arrivées.
            </p>

            <dl class="glab-specs glab-place-cards">
                <div>
                    <dt>
                        FFTir
                        <span class="glab-spec-kicker">18 au 20 octobre 2025</span>
                    </dt>
                    <dd>Les données de 274 000 licenciés : nom, prénom, date et lieu de naissance,
                        adresse postale, e-mail, téléphone et numéro de licence. Aucune donnée
                        médicale ni bancaire, et rien sur les armes : la fédération ne les connaît
                        pas.</dd>
                </div>
                <div>
                    <dt>
                        SIA
                        <span class="glab-spec-kicker">Révélée le 26 mars 2026</span>
                    </dt>
                    <dd>Pas une faille du système lui-même : un compte d'armurier compromis, par
                        lequel ont été extraites les données de 62 511 armes, avec leur type, leur
                        modèle et leur catégorie, les coordonnées et l'e-mail de leurs détenteurs.
                        L'authentification à deux facteurs a été généralisée et les détenteurs
                        prévenus.</dd>
                </div>
                <div>
                    <dt>
                        Armurerie Lavaux
                        <span class="glab-spec-kicker">Signalée le 1er août 2026</span>
                    </dt>
                    <dd>Les commandes passées depuis 2025 : nom, adresse postale, téléphone et
                        détail des produits achetés. Ni mots de passe, ni adresses e-mail, ni
                        données bancaires, ni numéros SIA ou de série.</dd>
                </div>
                <div>
                    <dt>
                        NaturaBuy
                        <span class="glab-spec-kicker">10 septembre 2026</span>
                    </dt>
                    <dd>Nom, prénom, e-mail, adresse postale, téléphone, identifiants et mots de
                        passe chiffrés. Ni mots de passe en clair, ni pièces d'identité, ni données
                        bancaires, ni numéros SIA ou de série, ni détail des commandes. Le site a
                        été coupé, la faille corrigée, la CNIL notifiée et une plainte déposée.</dd>
                </div>
            </dl>
        </section>

        <section class="glab-section" aria-labelledby="donnees-tableau-title">
            <h2 class="glab-title" id="donnees-tableau-title">Ce qui a fuité, <span class="glab-title-accent">champ par champ</span></h2>
            <p class="glab-lede">
                Ce que disent les sources, fuite par fuite. « Non précisé » veut dire qu'elles
                n'en parlent pas : ce n'est pas une garantie que la donnée est restée à l'abri.
            </p>

            <div class="glab-table-wrap">
                <table class="glab-table">
                    <thead>
                        <tr>
                            <th scope="col">Donnée</th>
                            @foreach ($incidents as $incident)
                                <th scope="col">{{ $incident['short'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($fields as $field)
                            <tr>
                                <td>{{ $field['label'] }}</td>
                                @foreach ($incidents as $incident)
                                    @php($state = $field[$incident['key']])
                                    <td class="{{ $stateClasses[$state] }}">{{ $stateLabels[$state] }}@if ($state === 'x' && isset($field['note'][$incident['key']])), {{ $field['note'][$incident['key']] }}@endif</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="glab-section" aria-labelledby="donnees-adresse-title">
            <h2 class="glab-title" id="donnees-adresse-title">Pourquoi c'est <span class="glab-title-accent">votre adresse</span> qui compte</h2>
            <p class="glab-lede glab-lede--thesis">
                Pour un cambrioleur, la question n'est pas « quelles armes possède cette
                personne », mais « à quelle adresse habite quelqu'un qui en possède
                probablement ».
            </p>

            <div class="glab-prose">
                <p>
                    Trois des quatre fichiers ne disent rien de vos armes. Seule l'extraction
                    passée par un compte d'armurier du SIA reliait des armes à des noms. Et
                    pourtant, une licence de tir, un compte sur une place de marché spécialisée ou
                    une commande dans une armurerie sont chacun un indice, associé à une adresse
                    exacte et à un numéro de téléphone. C'est cette combinaison qui a de la
                    valeur, pas le détail du râtelier.
                </p>
                <p>
                    C'est aussi pour cela que « je n'ai qu'une carabine de loisir » ne protège
                    de rien : ce n'est pas la valeur de votre matériel qui vous désigne, c'est la
                    case cochée à côté de votre adresse. Le ministre de l'Intérieur a rattaché 20
                    à 30 cambriolages à la fuite du SIA, et un suspect a été interpellé en Vendée
                    en avril 2026.
                </p>
            </div>
        </section>

        <section class="glab-section" aria-labelledby="donnees-idees-title">
            <h2 class="glab-title" id="donnees-idees-title">Trois idées <span class="glab-title-accent">qui mettent en danger</span></h2>
            <p class="glab-lede">
                Elles sont fausses, et ce sont celles sur lesquelles comptent les escrocs.
            </p>

            <div class="glab-myths">
                <article class="glab-myth">
                    <p class="glab-myth-flag">On entend</p>
                    <p class="glab-myth-claim">« Après la fuite, la police va passer récupérer les armes. »</p>
                    <p class="glab-myth-flag glab-myth-flag--truth">En réalité</p>
                    <p class="glab-myth-truth">
                        Jamais. Quelques jours après le piratage de la FFTir, un faux policier
                        s'est introduit chez un tireur. Un contrôle réel s'annonce par les voies
                        officielles ; un visiteur qui veut voir vos armes tout de suite vient les
                        chercher.
                    </p>
                </article>
                <article class="glab-myth">
                    <p class="glab-myth-flag">On entend</p>
                    <p class="glab-myth-claim">« Mes données ne valent rien, je n'ai qu'une carabine. »</p>
                    <p class="glab-myth-flag glab-myth-flag--truth">En réalité</p>
                    <p class="glab-myth-truth">
                        Ce n'est pas le matériel qui a de la valeur, c'est l'adresse d'un détenteur
                        probable. Une licence ou un compte spécialisé suffit à vous faire figurer
                        sur la liste.
                    </p>
                </article>
                <article class="glab-myth">
                    <p class="glab-myth-flag">On entend</p>
                    <p class="glab-myth-claim">« Je supprime mon compte SIA, c'est lui qui a fuité. »</p>
                    <p class="glab-myth-flag glab-myth-flag--truth">En réalité</p>
                    <p class="glab-myth-truth">
                        Le compte SIA est obligatoire depuis le 1er janvier 2025 pour acheter, vendre
                        ou faire réviser une arme. La fuite est passée par un compte d'armurier, et
                        supprimer le vôtre n'effacerait rien de ce qui a déjà été extrait.
                    </p>
                </article>
            </div>
        </section>

        <section class="glab-section" aria-labelledby="donnees-naturabuy-title">
            <h2 class="glab-title" id="donnees-naturabuy-title">Si vous avez <span class="glab-title-accent">un compte NaturaBuy</span></h2>
            <p class="glab-lede">
                Nous vendons également sur NaturaBuy, et une partie de nos clients y a donc un
                compte. Autant être direct.
            </p>

            <div class="glab-prose">
                <p>
                    Changez votre mot de passe en tapant vous-même l'adresse du site, pas depuis
                    un lien reçu par e-mail ou par SMS, même parfaitement rédigé et arrivé au bon
                    moment : les semaines qui suivent une fuite sont exactement celles des faux
                    messages de réinitialisation. Si ce mot de passe servait ailleurs, changez-le
                    partout.
                </p>
                <p>
                    Attendez-vous ensuite à des messages ciblés. Avec votre nom, votre adresse et
                    votre numéro, un message qui parle de chasse, de tir ou d'une commande devient
                    très convaincant. La règle ne change pas : on ne clique pas depuis le message,
                    on va sur le site par ses propres moyens.
                </p>
            </div>
        </section>

        <section class="glab-section" aria-labelledby="donnees-sia-title">
            <h2 class="glab-title" id="donnees-sia-title">Et le SIA, <span class="glab-title-accent">faut-il s'en méfier ?</span></h2>
            <p class="glab-lede">
                La question se pose, et elle mérite une réponse honnête plutôt qu'un slogan.
            </p>

            <div class="glab-prose">
                <p>
                    L'extraction n'est pas passée par une faille du système, mais par le compte
                    compromis d'un armurier, un professionnel autorisé : c'est le maillon humain
                    qui a cédé. Depuis, l'authentification à deux facteurs a été généralisée pour
                    ces accès, et le service central des armes a contacté les détenteurs
                    concernés.
                </p>
                <p>
                    Le SIA, lui, n'est pas optionnel : depuis le 1er janvier 2025, il faut un
                    compte pour acheter, vendre ou faire réviser une arme chez un armurier, et la
                    détention elle-même en dépend. Ce que l'on peut légitimement demander aux
                    organismes qui détiennent nos données, c'est ce qu'ils conservent, pendant
                    combien de temps, et qui y a accès. S'ils ne répondent pas, la CNIL reçoit les
                    plaintes.
                </p>
            </div>
        </section>

        <section class="glab-faq" aria-labelledby="donnees-faq-title">
            <h2 class="glab-title" id="donnees-faq-title">Questions <span class="glab-title-accent">fréquentes</span></h2>
            @foreach ($faq as $qa)
                <details>
                    <summary>{{ $qa[0] }}</summary>
                    <div>
                        <p>{{ $qa[1] }}</p>
                        @if ($loop->last)
                            <p><a href="{{ url('/confidentialite') }}">Lire notre politique de confidentialité</a></p>
                        @endif
                    </div>
                </details>
            @endforeach
        </section>

        <section class="glab-section" aria-labelledby="donnees-sources-title">
            <h2 class="glab-title" id="donnees-sources-title">Les <span class="glab-title-accent">sources</span></h2>
            <p class="glab-lede">
                Les communications des organismes, les textes, et la presse qui a suivi chaque
                fuite. Vérifiez : cette page ne demande pas d'être crue sur parole.
            </p>

            <ul class="glab-sources">
                @foreach ($sources as $source)
                    <li>
                        <a href="{{ $source['url'] }}" target="_blank" rel="noopener nofollow">
                            <span class="glab-source-num">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="glab-source-copy">
                                <span class="glab-source-label">{{ $source['label'] }}</span>
                                <span class="glab-source-host">{{ $source['host'] }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        <p class="glab-ctas">
            <a href="{{ route('guides.transport') }}" class="btn btn-primary">Transporter son arme</a>
            <a href="{{ route('guides.index') }}" class="btn btn-secondary">Tous les guides</a>
        </p>
    </div>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/guides.js') }}" defer></script>
@endpush
