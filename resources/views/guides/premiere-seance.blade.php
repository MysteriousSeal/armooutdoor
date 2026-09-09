@extends('layouts.app')

@php
    $faq = [
        ['Faut-il une arme pour faire une première séance de tir ?', 'Non, et c\'est même la seule chose qu\'on vous demandera de ne pas apporter. Tant que la licence n\'est pas délivrée, la séance de découverte se fait avec le matériel du club : arme, munitions, cible, protections. Si vous avez déjà acheté une carabine, elle reste chez vous ce jour-là.'],
        ['Qu\'est-ce qu\'on apporte alors ?', 'Une pièce d\'identité en cours de validité, des chaussures fermées et plates, un haut ajusté plutôt qu\'une chemise flottante, et de quoi boire. C\'est tout. Une séance dure entre une heure et une heure et demie, dont une bonne partie debout à se concentrer.'],
        ['Pourquoi une pièce d\'identité ?', 'Parce que le club vérifie que vous n\'êtes pas inscrit au fichier national des personnes interdites d\'acquisition et de détention d\'armes avant de vous mettre une arme entre les mains. C\'est un contrôle administratif, il prend deux minutes, et il conditionne la séance.'],
        ['Combien coûte une séance de découverte ?', 'La plupart des clubs facturent entre vingt et cinquante euros, armes et munitions comprises, et beaucoup déduisent cette somme de la cotisation si vous adhérez ensuite. Certains ne facturent rien du tout lors des journées portes ouvertes. Le club est le seul à pouvoir répondre pour lui-même.'],
        ['Un mineur peut-il tirer en club ?', 'Oui, le tir sportif encadré accueille les mineurs, et la réglementation prévoit même l\'accès au pistolet à un coup de calibre 22 dès douze ans dans le cadre d\'un club. L\'autorisation des deux parents est demandée, et l\'encadrement est permanent. Hors du club, en revanche, la vente d\'une réplique reste interdite aux mineurs.'],
        ['Que se passe-t-il si je fais une erreur au pas de tir ?', 'On vous reprend, et c\'est le rôle de l\'encadrant. Un moniteur préfère cent fois vous arrêter que vous voir installer une mauvaise habitude. La seule erreur qui compte vraiment est de continuer un geste dont on n\'est pas sûr : dans le doute, on pose l\'arme sur la tablette, canon vers les cibles, et on demande.'],
        ['Faut-il un certificat médical ?', 'Pas pour une découverte encadrée, en général. Pour la licence, oui : un certificat médical de non-contre-indication à la pratique du tir sportif est demandé à la première demande, et le club vous dira sa validité et sa périodicité. C\'est l\'étape suivante, pas celle du premier jour.'],
    ];

    $sources = [
        ['label' => 'Les règles de sécurité du tir sportif', 'url' => 'https://www.fftir.org/les-regles-de-securite/', 'host' => 'fftir.org'],
        ['label' => 'Circulaire de sécurité : ce qu\'on ne fait jamais sur un pas de tir', 'url' => 'https://fftir-centre.fr/circulaire-de-securite/', 'host' => 'fftir-centre.fr'],
        ['label' => 'Sécurité des stands, à l\'usage des clubs', 'url' => 'https://www.lltir.fr/ligue/espace-clubs/securite-des-stands.html', 'host' => 'lltir.fr'],
        ['label' => 'Ta première séance de tir : à quoi vraiment s\'attendre', 'url' => 'https://cibleblanche.fr/blog/premiere-seance-tir-sportif', 'host' => 'cibleblanche.fr'],
        ['label' => 'Débuter le tir sportif : guide pratique pour les nouveaux tireurs', 'url' => 'https://progresseraupistolet.fr/debuter-le-tir-sportif-en-2024-2025-guide-pratique-pour-les-nouveaux-tireurs/', 'host' => 'progresseraupistolet.fr'],
        ['label' => 'Commencer le tir sportif', 'url' => 'https://tirsportifchabris.fr/blog/commencer-le-tir-sportif', 'host' => 'tirsportifchabris.fr'],
        ['label' => 'Sécurité du tir : les basiques pour pratiquer en confiance', 'url' => 'https://safeshooting.fr/securite-tir-les-basiques-pour-pratiquer-en-toute-confiance/', 'host' => 'safeshooting.fr'],
        ['label' => 'Règles de sécurité d\'une société de tir', 'url' => 'https://www.leralliement.com/regles-de-securite', 'host' => 'leralliement.com'],
    ];

    $headline = 'Votre première séance au stand de tir';
    $description = 'Ce qu\'on vous demandera à l\'entrée, ce qu\'on vous prêtera, les quatre règles, les commandements du pas de tir et ce qu\'il faut vraiment dans le sac. Une heure et demie, sans arme à vous.';
@endphp

@section('title', 'Votre première séance au stand de tir : ce qui vous attend — Armo Outdoor')
@section('meta_description', $description)
@section('og_type', 'article')
@section('canonical', route('guides.premiere-seance'))

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/categories.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('css/guides.css') }}">
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'Article',
            'headline' => $headline,
            'description' => $description,
            'mainEntityOfPage' => route('guides.premiere-seance'),
            'inLanguage' => 'fr-FR',
            'image' => versioned_asset(\App\Support\Guides::byRoute('guides.premiere-seance')['image'] ?? 'images/hero.webp'),
            'datePublished' => \App\Support\Guides::byRoute('guides.premiere-seance')['published'],
            'dateModified' => \App\Support\Guides::byRoute('guides.premiere-seance')['updated'],
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
                ['@@type' => 'ListItem', 'position' => 3, 'name' => 'Votre première séance au stand', 'item' => route('guides.premiere-seance')],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('content')
    <div class="container glab glab--seance">
        @include('guides.partials.hero', [
            'crumb' => 'Votre première séance au stand',
            'kicker' => 'Débuter',
            'title' => 'Votre première séance au stand',
            'lede' => 'Ce qu\'on vous demandera à l\'entrée, ce qu\'on vous prêtera, les quatre règles qui tiennent tout le reste, et ce qu\'il faut vraiment dans le sac. Vous n\'aurez pas besoin d\'une arme.',
            'tags' => ['Découverte', 'Sécurité', 'Le sac'],
        ])

        <nav class="glab-plan" aria-label="Plan du guide">
            <p class="glab-plan-kicker">Plan</p>
            <div class="glab-plan-links">
                <a href="#seance-deroule-title">Le déroulé</a>
                <a href="#seance-regles-title">Les quatre règles</a>
                <a href="#seance-jamais-title">Ce qu'on ne fait jamais</a>
                <a href="#seance-mots-title">Les mots du pas de tir</a>
                <a href="#seance-sac-title">Le sac</a>
                <a href="#seance-myths-title">On lit partout</a>
                <a href="#seance-apres-title">Après</a>
                <a href="#seance-faq-title">Questions</a>
                <a href="#seance-sources-title">Sources</a>
            </div>
        </nav>

        <p class="glab-warning">
            <strong class="glab-warning-label">Le règlement du club prime</strong>
            Cette page décrit ce qui se passe dans la plupart des stands français. Chaque club a
            son propre règlement intérieur, et c'est lui qui fait foi sur place : les
            commandements, les horaires et les conditions d'accès s'y lisent avant tout le reste.
        </p>

        <section class="glab-section" aria-labelledby="seance-deroule-title">
            <h2 class="glab-title" id="seance-deroule-title">Ce qui se passe <span class="glab-title-accent">vraiment</span></h2>
            <p class="glab-lede glab-lede--thesis">
                Vous n'aurez pas besoin d'une arme. C'est même la seule chose qu'on vous demandera
                de ne pas apporter.
            </p>

            <div class="glab-prose">
                <p>
                    Une première séance ressemble moins à ce qu'on imagine qu'à un cours de
                    conduite. On ne vous lâche pas devant une cible : on vous explique, on vous
                    installe, on corrige votre position, et vous tirez peu. La plupart des clubs
                    appellent cela une séance de découverte, la facturent entre vingt et cinquante
                    euros, armes et munitions comprises, et déduisent souvent cette somme de la
                    cotisation si vous adhérez ensuite.
                </p>
            </div>

            <ol class="glab-rules">
                <li>
                    <h3>L'arrivée et les papiers <span class="glab-rule-time">15 min</span></h3>
                    <p>
                        On vous demande une pièce d'identité en cours de validité : le club vérifie
                        que vous n'êtes pas inscrit au fichier des personnes interdites d'acquisition
                        et de détention d'armes. Suit un tour des lieux, où l'on vous montre le
                        râtelier, la ligne rouge du pas de tir et la sortie de secours. La séance est
                        couverte par l'assurance du club.
                    </p>
                </li>
                <li>
                    <h3>Le briefing sécurité <span class="glab-rule-time">20 min</span></h3>
                    <p>
                        Le plus long moment sans tirer de la journée, et le plus utile. On y pose les
                        quatre règles fondamentales, les commandements du pas de tir, et ce qu'on ne
                        fait jamais. Personne ne passe cette étape, y compris ceux qui ont déjà tiré
                        ailleurs.
                    </p>
                </li>
                <li>
                    <h3>Les premiers coups <span class="glab-rule-time">Le cœur de la séance</span></h3>
                    <p>
                        L'encadrant règle le matériel à votre morphologie, vous place, et vous
                        accompagne coup par coup. Le plus souvent, ce sera une carabine à air
                        comprimé à dix mètres : ni bruit, ni recul, et tout le temps de penser à la
                        position plutôt qu'à l'appréhension. Le club fournit l'arme, les munitions,
                        les cibles et les protections.
                    </p>
                </li>
                <li>
                    <h3>Le débriefing <span class="glab-rule-time">5 à 10 min</span></h3>
                    <p>
                        On regarde vos cartons, on vous dit ce qui a marché, et on vous explique par
                        quoi passer si vous continuez. C'est le moment de poser les questions
                        d'argent, de licence et d'horaires. Comptez une heure à une heure et demie
                        pour l'ensemble.
                    </p>
                </li>
            </ol>

            <ul class="glab-takeaways">
                <li>
                    <span class="glab-takeaway-label">Durée</span>
                    <span>Une heure à une heure trente, dont vingt minutes de briefing sécurité.</span>
                </li>
                <li>
                    <span class="glab-takeaway-label">Fourni</span>
                    <span>Arme, munitions, cibles, protections, encadrement et assurance de la séance.</span>
                </li>
                <li>
                    <span class="glab-takeaway-label">À vous</span>
                    <span>Une pièce d'identité, des chaussures fermées, de quoi boire. Rien d'autre.</span>
                </li>
            </ul>
        </section>

        <section class="glab-section" aria-labelledby="seance-regles-title">
            <h2 class="glab-title" id="seance-regles-title">Les quatre règles, <span class="glab-title-accent">et pourquoi elles se répètent</span></h2>
            <p class="glab-lede">
                Elles sont redondantes exprès. Il faut que les quatre tombent en même temps pour
                qu'un accident arrive, et c'est tout leur intérêt.
            </p>

            <dl class="glab-specs glab-place-cards">
                <div>
                    <dt>
                        Toujours chargée
                        <span class="glab-spec-kicker">Même quand vous venez de vérifier</span>
                    </dt>
                    <dd>La formulation de la fédération est en majuscules dans le texte : une arme doit TOUJOURS être considérée comme CHARGÉE. Vérifier ne change rien à la règle, parce que la règle existe justement pour les fois où l'on a mal vérifié.</dd>
                </div>
                <div>
                    <dt>
                        Jamais vers quelqu'un
                        <span class="glab-spec-kicker">Ni vers soi</span>
                    </dt>
                    <dd>Le canon ne pointe que vers les cibles, y compris quand l'arme est ouverte, posée, démontée ou manifestement vide. Sur un pas de tir, cela veut dire une direction unique, et personne devant cette direction.</dd>
                </div>
                <div>
                    <dt>
                        L'index hors du pontet
                        <span class="glab-spec-kicker">Jusqu'à la décision de tirer</span>
                    </dt>
                    <dd>Le doigt reste le long de la carcasse et ne rejoint la queue de détente qu'au moment où vous décidez de tirer. C'est le geste que les encadrants reprennent le plus souvent, et le seul qui empêche un départ involontaire.</dd>
                </div>
                <div>
                    <dt>
                        Savoir ce qu'il y a derrière
                        <span class="glab-spec-kicker">Devant, derrière et autour</span>
                    </dt>
                    <dd>On identifie la cible et ce qui l'entoure avant chaque tir. En stand, le point d'arrêt est construit pour cela, mais la règle ne s'y suspend pas : elle est ce qui vous suivra ailleurs.</dd>
                </div>
            </dl>

            <p class="glab-more-reading">
                Où cette question se pose vraiment, et ce que le droit dit du jardin, du terrain et
                du stand, est le sujet de notre guide
                <a href="{{ route('guides.ou-tirer') }}">Où tirer légalement</a>.
            </p>
        </section>

        <section class="glab-section" aria-labelledby="seance-jamais-title">
            <h2 class="glab-title" id="seance-jamais-title">Ce qu'on ne fait <span class="glab-title-accent">jamais</span></h2>
            <p class="glab-lede">
                Quatre interdits que les circulaires de sécurité des ligues écrivent noir sur
                blanc. Aucun ne souffre d'exception d'intention.
            </p>

            <ul class="glab-takeaways">
                <li>
                    <span class="glab-takeaway-label">Viser</span>
                    <span>Jamais en dehors du pas de tir. Une arme n'est ni un jouet ni une longue-vue, et une visée hors de la ligne fait vider un stand.</span>
                </li>
                <li>
                    <span class="glab-takeaway-label">Manipuler</span>
                    <span>Jamais brutalement, jamais refermer une arme d'un coup sec. Ce qui claque sur un pas de tir fait sursauter cinq personnes armées.</span>
                </li>
                <li>
                    <span class="glab-takeaway-label">Laisser</span>
                    <span>Jamais une arme sans surveillance. Elle reste sur la tablette, ouverte, canon vers les cibles, ou elle repart au râtelier.</span>
                </li>
                <li>
                    <span class="glab-takeaway-label">Se déplacer</span>
                    <span>Jamais avec une arme à la main, à la bretelle ou dans un holster, hors des disciplines qui bénéficient d'une dérogation ministérielle, comme le tir de vitesse.</span>
                </li>
            </ul>

            <div class="glab-prose">
                <p>
                    À cela s'ajoute la règle qui gouverne l'espace : personne ne passe devant la
                    ligne de tir tant qu'une arme n'est pas neutralisée. Neutralisée veut dire
                    déchargée, ouverte, chargeur retiré, posée sur la tablette avec le témoin de
                    chambre vide en place et le canon vers les cibles, et surtout personne dessus.
                    Le témoin, drapeau ou étiquette, existe pour être vu de loin par des gens qui
                    n'ont pas le temps de venir vérifier chez vous.
                </p>
            </div>
        </section>

        <section class="glab-section" aria-labelledby="seance-mots-title">
            <h2 class="glab-title" id="seance-mots-title">Les trois phrases <span class="glab-title-accent">que vous entendrez</span></h2>
            <p class="glab-lede">
                Les formulations varient d'un club à l'autre, les trois moments non. On les
                exécute d'abord, on les comprend ensuite.
            </p>

            <dl class="glab-specs glab-place-cards">
                <div>
                    <dt>
                        « Commencez le tir »
                        <span class="glab-spec-kicker">Et pas avant</span>
                    </dt>
                    <dd>Le pas de tir est ouvert : on peut prendre l'arme, la charger et tirer. Tant que la phrase n'est pas prononcée, on ne touche à rien, même si l'on est prêt depuis cinq minutes.</dd>
                </div>
                <div>
                    <dt>
                        « Cessez le feu »
                        <span class="glab-spec-kicker">Immédiatement, sans finir</span>
                    </dt>
                    <dd>On arrête, on décharge, on ouvre, on pose l'arme canon vers les cibles et on retire les mains. On ne termine pas sa série, on ne repose pas un coup engagé. N'importe qui, y compris vous, peut lancer un arrêt s'il voit quelque chose d'anormal.</dd>
                </div>
                <div>
                    <dt>
                        « Armes au repos »
                        <span class="glab-spec-kicker">Avant d'aller au but</span>
                    </dt>
                    <dd>Les armes sont posées, ouvertes, témoin de chambre vide en place, et l'on s'écarte de la tablette. C'est seulement à ce moment que quiconque passe devant la ligne, pour changer les cibles.</dd>
                </div>
            </dl>
        </section>

        <section class="glab-section" data-glab-selector="seance" aria-labelledby="seance-sac-title">
            <h2 class="glab-title" id="seance-sac-title">Le sac <span class="glab-title-accent">de séance</span></h2>
            <p class="glab-lede">
                Deux questions, et la liste de ce qui part avec vous. Pour une découverte, la
                bonne réponse est presque toujours « rien ».
            </p>

            <div class="glab-steps">
                <fieldset class="glab-step">
                    <legend>01 · Votre visite</legend>
                    <div class="glab-chips glab-chips--stacked" data-glab-group="visite">
                        <button type="button" data-glab-value="decouverte" data-glab-phrase="une séance de découverte" class="is-active">
                            Une séance de découverte
                            <small>votre toute première fois</small>
                        </button>
                        <button type="button" data-glab-value="licencie" data-glab-phrase="vos premières séances de licencié">
                            Vos premières séances de licencié
                            <small>avec le matériel du club</small>
                        </button>
                        <button type="button" data-glab-value="propre" data-glab-phrase="une séance avec votre propre arme">
                            Avec votre propre arme
                            <small>licence en poche</small>
                        </button>
                    </div>
                </fieldset>
                <fieldset class="glab-step">
                    <legend>02 · La discipline</legend>
                    <div class="glab-chips glab-chips--range" data-glab-group="arme">
                        <button type="button" data-glab-value="air" data-glab-phrase="à l'air comprimé, à 10 m" class="is-active">Air 10 m</button>
                        <button type="button" data-glab-value="poing" data-glab-phrase="au pistolet, à 25 m">Poing 25 m</button>
                        <button type="button" data-glab-value="carabine" data-glab-phrase="à la carabine, à 50 m">Carabine 50 m</button>
                    </div>
                </fieldset>
            </div>

            <div class="glab-reco">
                <p class="glab-reco-label">Dans le sac</p>
                <p class="glab-reco-resume" data-glab-resume>Pour une séance de découverte à l'air comprimé, à 10 m :</p>
                {{-- The default answer (découverte, air 10 m) rendered server-side:
                     crawlable links, and a real list without JavaScript. The script
                     re-renders it on interaction. --}}
                <ol class="glab-reco-list" data-glab-results>
                    <li>
                        <span class="glab-reco-rank">01</span>
                        <div class="glab-reco-head">
                            <h3>Une pièce d'identité</h3>
                            <span class="glab-reco-meta">En cours de validité</span>
                        </div>
                        <p>Le club la demande pour vérifier votre inscription au fichier des interdits d'acquisition avant de vous mettre une arme entre les mains. Sans elle, la séance ne commence pas.</p>
                    </li>
                    <li>
                        <span class="glab-reco-rank">02</span>
                        <div class="glab-reco-head">
                            <h3>Des chaussures fermées et plates</h3>
                            <span class="glab-reco-meta">Et un haut ajusté</span>
                        </div>
                        <p>Un étui à douilles chaudes trouve toujours le col d'une chemise flottante, et une semelle plate tient l'équilibre mieux qu'un talon. Rien à acheter : ce que vous avez fait l'affaire.</p>
                    </li>
                    <li>
                        <span class="glab-reco-rank">03</span>
                        <div class="glab-reco-head">
                            <h3>De quoi boire</h3>
                            <span class="glab-reco-meta">Une heure à une heure trente</span>
                        </div>
                        <p>Une séance de découverte dure entre une heure et une heure et demie, dont la moitié debout à se concentrer. Une gourde suffit.</p>
                        <a href="{{ route('categories.show', 'survie') }}">Voir les gourdes et le nécessaire</a>
                    </li>
                    <li>
                        <span class="glab-reco-rank">04</span>
                        <div class="glab-reco-head">
                            <h3>Surtout pas votre propre arme</h3>
                            <span class="glab-reco-meta">Même si vous en avez une</span>
                        </div>
                        <p>Tant que la licence n'est pas délivrée, la séance se fait avec le matériel du club, armes et munitions comprises. Arriver avec la sienne fait perdre du temps à tout le monde.</p>
                    </li>
                </ol>
            </div>
        </section>

        <section class="glab-section" aria-labelledby="seance-myths-title">
            <h2 class="glab-title" id="seance-myths-title">Trois choses <span class="glab-title-accent">qui retiennent les gens</span></h2>
            <p class="glab-lede">
                Elles sont fausses, et elles font renoncer plus de débutants que n'importe quelle
                formalité.
            </p>

            <div class="glab-myths">
                <article class="glab-myth">
                    <p class="glab-myth-flag">On lit</p>
                    <p class="glab-myth-claim">« Il faut acheter une arme avant d'essayer. »</p>
                    <p class="glab-myth-flag glab-myth-flag--truth">En réalité</p>
                    <p class="glab-myth-truth">
                        C'est l'inverse. Le club fournit tout pour la découverte, et refusera la
                        vôtre tant que la licence n'est pas là. Une séance encadrée est la façon la
                        moins chère de savoir si l'on aime cela avant de dépenser quoi que ce soit.
                    </p>
                </article>
                <article class="glab-myth">
                    <p class="glab-myth-flag">On lit</p>
                    <p class="glab-myth-claim">« Il faut une autorisation pour tirer une fois. »</p>
                    <p class="glab-myth-flag glab-myth-flag--truth">En réalité</p>
                    <p class="glab-myth-truth">
                        Une séance de découverte se fait sous encadrement, avec le matériel et
                        l'assurance du club. Ce qui demande des formalités, c'est de détenir une
                        arme chez soi, pas d'en tirer une sous la surveillance d'un moniteur.
                    </p>
                </article>
                <article class="glab-myth">
                    <p class="glab-myth-flag">On lit</p>
                    <p class="glab-myth-claim">« Il faut déjà savoir viser. »</p>
                    <p class="glab-myth-flag glab-myth-flag--truth">En réalité</p>
                    <p class="glab-myth-truth">
                        Personne ne regardera vos points le premier jour. Ce qu'on évalue, c'est si
                        vous appliquez les règles et si vous écoutez. La précision arrive plus tard,
                        et elle arrive à tout le monde.
                    </p>
                </article>
            </div>
        </section>

        <section class="glab-section" aria-labelledby="seance-apres-title">
            <h2 class="glab-title" id="seance-apres-title">Après la séance : <span class="glab-title-accent">licence et carnet</span></h2>
            <p class="glab-lede">
                Si vous continuez, deux documents entrent dans votre vie, et ils commandent tout
                le reste.
            </p>

            <div class="glab-prose">
                <p>
                    Le premier est la licence fédérale, délivrée par le club après adhésion et sur
                    présentation d'un certificat médical de non-contre-indication à la pratique du
                    tir sportif. Elle ouvre le pas de tir, elle couvre votre pratique, et elle vaut
                    motif légitime pour transporter une arme entre chez vous et le stand :
                    déchargée, démontée ou munie d'un dispositif la rendant inutilisable, dans une
                    mallette ou une housse, munitions rangées à part.
                </p>
                <p>
                    Le second est le carnet de tir. À chaque séance contrôlée, l'encadrant y appose
                    un visa daté. Ces visas ne servent à rien tant que vous tirez avec le matériel
                    du club, et ils deviennent la pièce maîtresse le jour où vous demandez une
                    autorisation d'acquisition. Le nombre de séances exigé et leur espacement sont
                    fixés par la réglementation, et votre club les connaît mieux que n'importe
                    quelle page générale.
                </p>
            </div>

            <p class="glab-more-reading">
                Ce que recouvrent les catégories D, C, B et A, et par où passe une première
                autorisation, est le sujet de notre guide
                <a href="{{ route('guides.classification') }}">Classer son arme</a>. Les mots que
                vous entendrez au comptoir sont au
                <a href="{{ route('guides.glossaire') }}">glossaire</a>, et le nettoyage du soir
                dans <a href="{{ route('guides.entretien') }}">Entretenir son arme</a>.
            </p>
        </section>

        <section class="glab-faq" aria-labelledby="seance-faq-title">
            <h2 class="glab-title" id="seance-faq-title">Questions <span class="glab-title-accent">fréquentes</span></h2>
            @foreach ($faq as $qa)
                <details>
                    <summary>{{ $qa[0] }}</summary>
                    <div>
                        <p>{{ $qa[1] }}</p>
                    </div>
                </details>
            @endforeach
        </section>

        <section class="glab-section" aria-labelledby="seance-sources-title">
            <h2 class="glab-title" id="seance-sources-title">Les <span class="glab-title-accent">sources</span></h2>
            <p class="glab-lede">
                Les règles de sécurité telles que la fédération et les ligues les écrivent, et les
                récits de séance de ceux qui les font tourner.
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
            <a href="{{ route('categories.show', 'temoin-de-chambre-vide') }}" class="btn btn-primary">Témoins de chambre vide</a>
            <a href="{{ route('categories.show', 'kit-stand-tir') }}" class="btn btn-secondary">Le kit de stand</a>
        </p>
    </div>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/guides.js') }}" defer></script>
@endpush
