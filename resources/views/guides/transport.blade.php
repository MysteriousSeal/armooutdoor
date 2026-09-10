@extends('layouts.app')

@php
    $faq = [
        ['Mon arme de catégorie B doit-elle voyager déchargée et démontée ?', 'Déchargée, toujours. Démontée ou verrouillée, c\'est l\'un ou l\'autre : l\'article R315-4 du code de la sécurité intérieure demande que l\'arme ne soit pas immédiatement utilisable, soit par un dispositif de sécurité (une mallette à verrou, une chambre bloquée), soit en retirant l\'une de ses pièces essentielles, transportée à part. Les deux méthodes se valent devant la loi ; la mallette est simplement la plus pratique.'],
        ['Les munitions doivent-elles être dans un contenant séparé de l\'arme ?', 'Le texte ne l\'impose pas au mot près : l\'article R315-4 ne parle que de l\'arme, pas des munitions. Ce qui rend la séparation quasi systématique en pratique, c\'est ailleurs : les règlements de club, les licences fédérales et les conditions d\'assurance en font une condition presque toujours écrite, et un contrôle qui trouve l\'arme chargée dans son étui ne s\'embarrasse pas de la nuance entre la loi et le règlement du club.'],
        ['Les deux envois espacés de 24 heures de l\'article R315-13, ça s\'applique à mon trajet jusqu\'au stand ?', 'Non, et c\'est la confusion la plus facile à faire sur ce sujet. R315-13 régit les « expéditions », le vocabulaire de l\'envoi confié à un tiers : emballage sans mention du contenu, cartons cerclés, entreprise de transport prévenue. L\'article R315-12 qui ouvre cette section précise qu\'elle vise aussi les particuliers, mais toujours au sens d\'un envoi par transporteur ou par la Poste, pas d\'un trajet en voiture jusqu\'au club. Le vôtre reste sous R315-4 : dispositif de sécurité ou démontage, rien de plus.'],
        ['Et pour une carabine de catégorie C, entre la maison et le terrain de chasse ?', 'Le permis de chasser validé pour l\'année en cours vaut motif légitime de port et de transport pour la catégorie C et les armes du a de la catégorie D destinées à la chasse, dit l\'article R315-2. Aucun article n\'impose ensuite le dispositif de sécurité prévu pour la B ; en pratique, arme déchargée et sous housse, munitions à part, reste ce que les fédérations recommandent, sans que la loi l\'exige au même degré.'],
        ['Une carabine à air comprimé, catégorie D : que dit la loi sur son transport ?', 'Qu\'il faut un motif légitime, comme pour les autres catégories : l\'article R315-1 ne fait pas d\'exception pour la D. Mais aucun article ne lui attache de titre tout fait comme le permis de chasser à la C ou la licence à la B : ni le permis, ni la licence, ni la carte de collectionneur ne mentionnent la D pour un usage de loisir. Le trajet vers un terrain ou une reprise après une séance est traité en pratique comme légitime, sans qu\'un texte le dise en toutes lettres.'],
        ['Une réplique d\'airsoft, c\'est pareil ?', 'Juridiquement, une réplique de catégorie D suit exactement la même règle que la carabine à air comprimé du dessus. La Fédération française d\'airsoft recommande, en plus, de la transporter démontée de sa source d\'énergie (pas de batterie branchée, pas de gaz engagé), rangée dans une housse ou une valise non transparente, billes à part, non visible depuis l\'extérieur du véhicule. Ce que recouvrent les catégories elles-mêmes est le sujet de notre guide Classer son arme.'],
        ['Un contrôle peut-il vérifier tout cela sur la route ?', 'Oui. Pour les catégories A et B, le transport sans motif légitime hors du domicile est puni de cinq ans d\'emprisonnement et 75 000 euros d\'amende par l\'article 222-54 du code pénal. Pour les catégories C et D, le code de la sécurité intérieure prévoit aussi une sanction, à un niveau moindre ; dans tous les cas, la licence ou le permis en cours de validité suffit à répondre à la question posée sur place.'],
    ];

    $sources = [
        ['label' => 'Code de la sécurité intérieure, articles R315-1 à R315-4 (interdictions, motifs légitimes, transport non immédiatement utilisable)', 'url' => 'https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000025503132/LEGISCTA000029655429/', 'host' => 'legifrance.gouv.fr'],
        ['label' => 'Code de la sécurité intérieure, articles R315-12 et R315-13 (le champ des expéditions, et les deux envois à 24 heures pour la A et la B)', 'url' => 'https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000025503132/LEGISCTA000029655457/', 'host' => 'legifrance.gouv.fr'],
        ['label' => 'Code pénal, article 222-54 (transport sans motif légitime des catégories A et B : la peine)', 'url' => 'https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000006070719/LEGISCTA000032632507/', 'host' => 'legifrance.gouv.fr'],
        ['label' => 'Port et transport d\'armes pour chasseur ou tireur sportif', 'url' => 'https://www.armes-ufa.com/spip.php?article1080=', 'host' => 'armes-ufa.com'],
        ['label' => 'Conseils pragmatiques pour le transport d\'armes de tir', 'url' => 'https://www.armes-ufa.com/spip.php?article2684=', 'host' => 'armes-ufa.com'],
        ['label' => 'Foire aux questions sur le transport d\'armes de chasse', 'url' => 'https://www.chasseurdefrance.com/faq/armes-chasse/transport-armes/', 'host' => 'chasseurdefrance.com'],
        ['label' => 'Comment transporter mes répliques en France ?', 'url' => 'https://ffairsoft.org/ufaqs/comment-transporter-mes-repliques-en-france/', 'host' => 'ffairsoft.org'],
    ];
@endphp

@section('title', 'Transporter son arme et ses munitions : la loi, le coffre, le club - Armo Outdoor')
@section('meta_description', 'Ce que la loi impose vraiment pour transporter une arme entre le domicile, le club ou la chasse, catégorie par catégorie, et ce qu\'elle ne dit pas pour une carabine à air comprimé ou une réplique d\'airsoft.')
@section('og_type', 'article')
@section('canonical', route('guides.transport'))

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/categories.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('css/guides.css') }}">
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'Article',
            'headline' => 'Transporter son arme et ses munitions : la loi, le coffre, le club',
            'description' => 'Ce que la loi impose vraiment pour transporter une arme entre le domicile, le club ou la chasse, catégorie par catégorie, et ce qu\'elle ne dit pas pour une carabine à air comprimé ou une réplique d\'airsoft.',
            'mainEntityOfPage' => route('guides.transport'),
            'inLanguage' => 'fr-FR',
            'image' => versioned_asset(\App\Support\Guides::byRoute('guides.transport')['image'] ?? 'images/hero.webp'),
            'datePublished' => \App\Support\Guides::byRoute('guides.transport')['published'],
            'dateModified' => \App\Support\Guides::byRoute('guides.transport')['updated'],
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
                ['@@type' => 'ListItem', 'position' => 3, 'name' => 'Transporter son arme', 'item' => route('guides.transport')],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('content')
    <div class="container glab glab--transport">
        @include('guides.partials.hero', [
            'crumb' => 'Transporter son arme',
            'kicker' => 'Transport',
            'title' => 'Transporter son arme et ses munitions',
            'lede' => 'Entre le domicile et le club, ou le domicile et le terrain de chasse, une seule question technique décide de tout : l\'arme est-elle immédiatement utilisable. Pour la catégorie B, la loi répond en détail. Pour la C et la D, elle est beaucoup plus silencieuse qu\'on ne le croit. Ce guide dit ce que le texte impose, ce qu\'il laisse aux règlements de club et au bon sens, et corrige une confusion fréquente sur les envois par transporteur, qui n\'a rien à voir avec votre trajet en voiture.',
            'tags' => ['Catégorie B', 'Chasse', 'Airsoft'],
        ])

        <nav class="glab-plan" aria-label="Plan du guide">
            <p class="glab-plan-kicker">Plan</p>
            <div class="glab-plan-links">
                <a href="#transport-question-title">La règle</a>
                <a href="#transport-categories-title">Catégorie par catégorie</a>
                <a href="#transport-r315-4-title">Le texte qui décide</a>
                <a href="#transport-etui-title">Choisir l'étui</a>
                <a href="#transport-munitions-title">Les munitions</a>
                <a href="#transport-mythe-title">Ce que la loi ne dit pas</a>
                <a href="#transport-airsoft-title">L'airsoft</a>
                <a href="#transport-faq-title">Questions</a>
                <a href="#transport-sources-title">Sources</a>
            </div>
        </nav>

        <p class="glab-warning">
            <strong class="glab-warning-label">Pas un conseil juridique</strong>
            Ceci décrit l'état du droit à la date de publication. Pour un cas précis, la
            gendarmerie ou la préfecture du département sont les interlocutrices compétentes :
            les arrêtés locaux priment sur ce que dit une page générale.
        </p>

        <section class="glab-section" aria-labelledby="transport-question-title">
            <h2 class="glab-title" id="transport-question-title">La question <span class="glab-title-accent">qui compte</span></h2>
            <p class="glab-lede glab-lede--thesis">
                Ce n'est pas le mode de transport qui compte, c'est la catégorie de l'arme, et
                pour une seule d'entre elles la loi ne laisse aucune place à l'interprétation.
            </p>

            <div class="glab-prose">
                <p>
                    L'article R315-1 du code de la sécurité intérieure interdit, sauf motif
                    légitime, le transport des armes, éléments d'arme et munitions des catégories
                    A et B, et, dans un texte distinct, le port et le transport sans motif légitime
                    des catégories C et D. Autrement dit : même une carabine à air comprimé ou une
                    réplique d'airsoft ne se transportent pas « pour rien » hors de chez vous, en
                    droit strict.
                </p>
                <p>
                    Mais « motif légitime » n'est pas défini de la même façon pour toutes les
                    catégories. L'article R315-2 énumère des titres tout faits : le permis de
                    chasser validé, pour la chasse ; la licence de tir sportif en cours, pour le
                    tir ; la carte de collectionneur, pour l'exposition ou l'étude. L'article
                    R315-3 y ajoute la reconstitution historique. Aucun de ces titres ne vise
                    nommément l'airsoft de loisir ou le air-gun de loisir : ce vide est traité
                    plus bas, ni comme une interdiction ni comme une permission explicite.
                </p>
            </div>
        </section>

        <section class="glab-section" aria-labelledby="transport-categories-title">
            <h2 class="glab-title" id="transport-categories-title">Catégorie <span class="glab-title-accent">par catégorie</span></h2>
            <p class="glab-lede">
                Trois catégories, trois réponses différentes à la même question : qu'est-ce qui
                vaut motif légitime, et qu'est-ce que le texte exige ensuite.
            </p>

            <dl class="glab-specs glab-place-cards">
                <div>
                    <dt>
                        Catégorie D
                        <span class="glab-spec-kicker">Motif légitime demandé, aucun titre nommé</span>
                    </dt>
                    <dd>Le transport hors du domicile exige un motif légitime comme les autres
                        catégories, mais aucun texte ne lui attache un titre tout fait comme le
                        permis de chasser à la C. Le trajet vers un terrain, un stand ou une
                        reprise après une séance est traité en pratique comme légitime, sans
                        qu'un article le dise en toutes lettres.</dd>
                </div>
                <div>
                    <dt>
                        Catégorie C
                        <span class="glab-spec-kicker">Le permis ou la licence suffisent</span>
                    </dt>
                    <dd>Le permis de chasser validé de l'année vaut motif légitime de port et de
                        transport ; la licence de tir sportif en cours fait de même pour le tir.
                        Aucun article n'impose ensuite le dispositif de sécurité prévu pour la B.</dd>
                </div>
                <div>
                    <dt>
                        Catégorie B
                        <span class="glab-spec-kicker">Le seul texte qui décide vraiment</span>
                    </dt>
                    <dd>L'article R315-4 impose que l'arme ne soit pas immédiatement utilisable,
                        par un dispositif technique ou en retirant l'un de ses éléments. La
                        licence de tir sportif en cours de validité vaut motif légitime. Sans lui,
                        le transport hors domicile coûte cinq ans et 75 000 euros.</dd>
                </div>
            </dl>

            <p class="glab-more-reading">
                Ce que recouvrent ces catégories est le sujet de notre guide
                <a href="{{ route('guides.classification') }}">Classer son arme</a>. Une fois sur
                place, ce que le droit autorise selon le lieu (chez soi, sur un terrain, en
                stand) est le sujet de
                <a href="{{ route('guides.ou-tirer') }}">Où tirer légalement</a>. Cette page ne
                traite que du transport.
            </p>
        </section>

        <section class="glab-section" aria-labelledby="transport-r315-4-title">
            <h2 class="glab-title" id="transport-r315-4-title">Le seul texte <span class="glab-title-accent">qui décide</span></h2>
            <p class="glab-lede">
                Pour la catégorie B, l'article R315-4 offre deux méthodes, à égalité devant la
                loi.
            </p>

            <div class="glab-prose">
                <p>
                    Un dispositif technique qui empêche l'usage immédiat (une mallette à verrou,
                    une chambre bloquée), ou le retrait d'un élément essentiel de l'arme,
                    transporté séparément. Aucune des deux n'est « la bonne » ; la mallette
                    verrouillée est simplement la plus pratique pour la majorité des tireurs.
                </p>
            </div>

            <p class="glab-warning">
                <strong class="glab-warning-label">Ce que R315-4 ne dit pas</strong>
                Le texte ne dit rien des munitions elles-mêmes. La séparation de l'arme et des
                munitions est une pratique quasi universelle, recommandée par la FFTir et l'UFA
                et souvent écrite dans les règlements de club et les conditions d'assurance, mais
                ce n'est pas une obligation qu'on trouve au mot près dans R315-4.
            </p>
        </section>

        <section class="glab-section" aria-labelledby="transport-etui-title">
            <h2 class="glab-title" id="transport-etui-title">Choisir <span class="glab-title-accent">le bon étui</span></h2>
            <p class="glab-lede">
                Ce que la loi demande pour l'arme, et ce que l'usage recommande pour tout le
                reste.
            </p>

            <div class="glab-prose">
                <p>
                    Pour l'arme : une housse, un étui rigide ou une mallette à verrou selon la
                    catégorie et la fréquence de transport. Ce que R315-4 demande, c'est le
                    verrouillage ou le démontage, pas un modèle précis, et notre rayon
                    <a href="{{ route('categories.show', 'poches-etuis') }}">housses et étuis</a>
                    couvre les deux usages.
                </p>
                <p>
                    Pour les munitions : un étui séparé, dans un compartiment distinct du
                    véhicule si possible, comme notre rayon
                    <a href="{{ route('categories.show', 'etuis-a-munitions') }}">étuis à
                    munitions</a>.
                </p>
            </div>
        </section>

        <section class="glab-section" aria-labelledby="transport-munitions-title">
            <h2 class="glab-title" id="transport-munitions-title">Les munitions, <span class="glab-title-accent">au repos</span></h2>
            <p class="glab-lede">
                Entre deux sorties, la même logique de séparation continue chez vous.
            </p>

            <div class="glab-prose">
                <p>
                    Chez vous, entre deux sorties : une
                    <a href="{{ route('categories.show', 'boites-munitions') }}">boîte de
                    munitions</a> dédiée, séparée de l'arme, idéalement dans une armoire ou un
                    meuble fermé.
                </p>
            </div>
        </section>

        <section class="glab-section" aria-labelledby="transport-mythe-title">
            <h2 class="glab-title" id="transport-mythe-title">Ce que R315-12 et R315-13 <span class="glab-title-accent">ne disent pas</span></h2>
            <p class="glab-lede">
                Une règle réelle, mais appliquée au mauvais trajet.
            </p>

            <div class="glab-myths">
                <article class="glab-myth">
                    <p class="glab-myth-flag">On lit</p>
                    <p class="glab-myth-claim">« Il faut deux envois séparés de 24 heures pour transporter une arme de catégorie B. »</p>
                    <p class="glab-myth-flag glab-myth-flag--truth">En réalité</p>
                    <p class="glab-myth-truth">
                        Cette règle (R315-13) vise les expéditions confiées à un transporteur ou
                        à la Poste, encadrées par tout un vocabulaire de fret : cartons cerclés,
                        véhicule fermé à clé, entreprise de transport prévenue du contenu.
                        L'article R315-12, qui ouvre cette section, précise qu'elle vise aussi les
                        particuliers, mais toujours au sens d'un envoi par transporteur. Le trajet
                        personnel jusqu'au stand reste sous R315-4 : dispositif de sécurité ou
                        démontage, rien de plus.
                    </p>
                </article>
            </div>
        </section>

        <section class="glab-section" aria-labelledby="transport-airsoft-title">
            <h2 class="glab-title" id="transport-airsoft-title">L'airsoft, <span class="glab-title-accent">un régime distinct</span></h2>
            <p class="glab-lede">
                Une réplique se classe comme une arme, et se transporte comme elle.
            </p>

            <div class="glab-prose">
                <p>
                    Sous 2 joules, hors classification ; entre 2 et 20 joules, en catégorie D
                    comme une carabine à air comprimé, donc soumise à la même exigence de motif
                    légitime et au même vide de titre nommé que la catégorie D ci-dessus (le
                    détail des seuils est dans notre guide
                    <a href="{{ route('guides.classification') }}">Classer son arme</a>).
                </p>
                <p>
                    La Fédération française d'airsoft recommande un transport démontée de sa
                    source d'énergie (batterie débranchée, pas de gaz engagé), rangée dans une
                    housse ou une valise non transparente, billes à part, non visible depuis
                    l'extérieur du véhicule. Certaines préfectures ont pris des arrêtés locaux qui
                    vont plus loin ; seule la préfecture du département concerné fait foi pour un
                    trajet donné.
                </p>
            </div>
        </section>

        <section class="glab-faq" aria-labelledby="transport-faq-title">
            <h2 class="glab-title" id="transport-faq-title">Questions <span class="glab-title-accent">fréquentes</span></h2>
            @foreach ($faq as $qa)
                <details>
                    <summary>{{ $qa[0] }}</summary>
                    <div>
                        <p>{{ $qa[1] }}</p>
                    </div>
                </details>
            @endforeach
        </section>

        <section class="glab-section" aria-labelledby="transport-sources-title">
            <h2 class="glab-title" id="transport-sources-title">Les <span class="glab-title-accent">sources</span></h2>
            <p class="glab-lede">
                Les textes eux-mêmes, plutôt que ce qu'on en dit. Vérifiez : cette page ne demande
                pas d'être crue sur parole.
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
            <a href="{{ route('categories.show', 'poches-etuis') }}" class="btn btn-primary">Housses et étuis</a>
            <a href="{{ route('guides.index') }}" class="btn btn-secondary">Tous les guides</a>
        </p>
    </div>
@endsection
