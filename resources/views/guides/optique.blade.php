@extends('layouts.app')

@php
    $faq = [
        ['À quelle distance faut-il régler sa lunette ?', 'À celle où vous tirez le plus souvent, et à aucune autre. Un zéro est une distance : la lunette est montée au-dessus du canon, la trajectoire monte vers la ligne de visée, la croise, puis redescend. Pour une carabine à plomb de 10 joules, 10 à 15 mètres. Pour une 20 joules, 20 à 25 mètres. Pour une PCP, 25 ou 50 mètres. Régler à 50 mètres une carabine qu\'on tire à 12 revient à viser bas toute la séance.'],
        ['Combien vaut un clic sur ma lunette ?', 'Cela dépend de deux choses : la graduation de la tourelle et la distance de la cible. Un clic de 1/4 MOA déplace l\'impact de 0,73 cm à 100 mètres, mais de 0,18 cm seulement à 25 mètres et de 0,07 cm à 10 mètres. Un clic de 0,1 MRAD vaut 1 cm à 100 mètres, 0,25 cm à 25 mètres. La calculette de cette page fait la conversion dans les deux sens.'],
        ['Pourquoi faut-il autant de clics à 10 mètres ?', 'Parce qu\'un clic est un angle, pas une longueur. Le même angle couvre dix fois moins de terrain à 10 mètres qu\'à 100. Corriger 3 centimètres à 10 mètres demande une quarantaine de clics de 1/4 MOA, là où la même correction à 100 mètres en demanderait quatre. Ce n\'est pas une lunette fatiguée, c\'est de la géométrie.'],
        ['MOA ou MRAD, lequel choisir ?', 'Celui que porte votre réticule. Une tourelle en MRAD sur un réticule gradué en MOA vous fera convertir à chaque correction, et c\'est là que naissent les erreurs. Si le choix est ouvert, le MRAD est décimal et se marie au mètre : 0,1 MRAD vaut 1 cm à 100 m, 2 cm à 200 m, sans calcul. Le MOA se marie au pouce et à la yard, d\'où les cibles à grille en pouces.'],
        ['Combien de coups avant de toucher aux tourelles ?', 'Trois, au minimum, et sans bouger l\'arme entre les deux. Un seul impact ne dit rien : il peut être votre coup, votre appui ou votre plomb. Ce qu\'on corrige, c\'est le centre d\'un groupement, mesuré depuis le point visé, jamais le trou le plus proche du centre.'],
        ['Ma lunette se dérègle toute seule, pourquoi ?', 'Une lunette bouge rarement seule. Les colliers se desserrent, surtout après vingt à trente coups sur une carabine à ressort dont le recul part dans les deux sens. Un lot de plombs différent change le zéro. La bague de parallaxe déréglée fait nager le réticule et déplace le point d\'impact selon la position de l\'œil. Vérifiez les vis, le lot et la parallaxe avant de toucher aux tourelles.'],
        ['Faut-il une cible particulière pour régler une optique ?', 'Une cible à grille, oui. La grille transforme un groupement décentré en une mesure, et la mesure en un nombre de clics : sans elle, on estime, et on tourne trop. Nos cibles carrées existent en grille au pouce, pour les tourelles en MOA, et en repères centimétriques. Les pastilles autocollantes rebouchent les impacts entre deux séries pour garder un carton lisible.'],
    ];

    $sources = [
        ['label' => 'Comprendre les clics de réglage en MOA et MRAD', 'url' => 'https://safeshooting.fr/reglage-lunette-point-rouge-moa-mrad/', 'host' => 'safeshooting.fr'],
        ['label' => 'MOA et MRAD expliqués simplement : comprendre les réglages d\'une lunette', 'url' => 'https://www.2a-inc.fr/cabinet-d-etude-de-collection-armuriere/technologies-des-armes-a-feu-fonctionnement-materiaux-et-balistique/moa-et-mrad-expliques-simplement-comprendre-les-reglages-d-une-lunette-de-tir', 'host' => '2a-inc.fr'],
        ['label' => 'Réglage lunette de tir : le guide du zérotage', 'url' => 'https://red-dot-sight.eu/blogs/news/comment-regler-zerotage-sur-lunette-de-tir', 'host' => 'red-dot-sight.eu'],
        ['label' => 'La lunette de tir, guide complet', 'url' => 'https://app.10point9.fr/encyclopedie/technique/lunette-de-tir-guide', 'host' => '10point9.fr'],
        ['label' => 'Monter et régler une lunette sur une carabine à plomb', 'url' => 'https://tirsportifchabris.fr/blog/montage-lunette-carabine-a-plomb', 'host' => 'tirsportifchabris.fr'],
        ['label' => 'Comment régler une lunette de carabine à plomb', 'url' => 'https://www.hyperprotec.com/blog/armes-a-plomb/comment-regler-une-lunette-de-carabine-a-plomb/', 'host' => 'hyperprotec.com'],
        ['label' => 'Réglage lunette de tir sur carabine à plomb', 'url' => 'https://www.armurerie-loisir.fr/content/24-reglage-lunette-de-tir-carabine-a-plomb', 'host' => 'armurerie-loisir.fr'],
        ['label' => 'Connaître et comprendre comment régler sa lunette de visée', 'url' => 'https://www.toptir.fr/blog/connaitre-comprendre-comment-regler-lunette-de-visee/', 'host' => 'toptir.fr'],
        ['label' => 'Comment régler une carabine à plomb', 'url' => 'https://www.carabine-a-plomb.com/regler-carabine-a-plomb/', 'host' => 'carabine-a-plomb.com'],
    ];

    $headline = 'Régler sa lunette : le zéro, les clics, la distance';
    $description = 'MOA ou MRAD, ce que vaut vraiment un clic à 10, 25 et 50 mètres, et une calculette qui convertit vos centimètres d\'écart en clics de tourelle. Le zéro est une distance, pas un réglage.';
@endphp

@section('title', 'Régler sa lunette de tir : le zéro, les clics, la distance — Armo Outdoor')
@section('meta_description', $description)
@section('og_type', 'article')
@section('canonical', route('guides.optique'))

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/categories.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('css/guides.css') }}">
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'Article',
            'headline' => $headline,
            'description' => $description,
            'mainEntityOfPage' => route('guides.optique'),
            'inLanguage' => 'fr-FR',
            'image' => versioned_asset(\App\Support\Guides::byRoute('guides.optique')['image'] ?? 'images/hero.webp'),
            'datePublished' => \App\Support\Guides::byRoute('guides.optique')['published'],
            'dateModified' => \App\Support\Guides::byRoute('guides.optique')['updated'],
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
                ['@@type' => 'ListItem', 'position' => 3, 'name' => 'Régler sa lunette', 'item' => route('guides.optique')],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('content')
    <div class="container glab glab--optique">
        @include('guides.partials.hero', [
            'crumb' => 'Régler sa lunette',
            'kicker' => 'Optique',
            'title' => 'Régler sa lunette',
            'lede' => 'Un zéro n\'est pas un réglage, c\'est une distance. Ce guide dit ce que vaut un clic là où vous tirez vraiment, et convertit vos centimètres d\'écart en tours de tourelle.',
            'tags' => ['Zéro', 'MOA et MRAD', 'Clics'],
        ])

        <nav class="glab-plan" aria-label="Plan du guide">
            <p class="glab-plan-kicker">Plan</p>
            <div class="glab-plan-links">
                <a href="#opt-zero-title">Le zéro</a>
                <a href="#opt-unites-title">MOA et MRAD</a>
                <a href="#opt-calc-title">Convertisseur</a>
                <a href="#opt-seance-title">La séance</a>
                <a href="#opt-parallaxe-title">La parallaxe</a>
                <a href="#opt-derive-title">Ce qui dérègle</a>
                <a href="#opt-myths-title">On lit partout</a>
                <a href="#opt-faq-title">Questions</a>
                <a href="#opt-sources-title">Sources</a>
            </div>
        </nav>

        <section class="glab-section" aria-labelledby="opt-zero-title">
            <h2 class="glab-title" id="opt-zero-title">Un zéro <span class="glab-title-accent">est une distance</span></h2>
            <p class="glab-lede glab-lede--thesis">
                « Ma lunette est réglée » ne veut rien dire tant qu'on n'a pas dit à quelle
                distance.
            </p>

            <div class="glab-prose">
                <p>
                    La lunette est montée quatre à cinq centimètres au-dessus de l'axe du canon.
                    Le projectile part donc sous la ligne de visée, monte vers elle, la croise une
                    première fois, continue de monter un peu, puis retombe et la recroise plus
                    loin. Un tir n'a pas un point de rencontre avec la ligne de visée : il en a
                    deux, et régler une lunette consiste à choisir lequel des deux tombe là où
                    vous tirez.
                </p>
                <p>
                    De là vient la seule règle qui compte : on règle à la distance où l'on tire le
                    plus souvent. Une carabine à plomb de dix joules vit entre dix et quinze
                    mètres, une vingt joules entre vingt et vingt-cinq, une PCP à vingt-cinq ou
                    cinquante. Réglée à cinquante mètres, la première tirera haut sur toute la
                    plage où elle sert réellement, et le tireur passera sa séance à compenser une
                    erreur qu'il a lui-même installée.
                </p>
                <p>
                    Le corollaire est moins agréable : hors de sa distance de zéro, une arme tape
                    toujours ailleurs. Ce n'est pas un défaut de réglage, c'est la trajectoire. Un
                    zéro bien choisi ne supprime pas cet écart, il le rend petit là où vous
                    l'utilisez.
                </p>
            </div>

            <ul class="glab-takeaways">
                <li>
                    <span class="glab-takeaway-label">Choisir</span>
                    <span>La distance où vous tirez le plus souvent, pas la plus longue dont l'arme est capable.</span>
                </li>
                <li>
                    <span class="glab-takeaway-label">Noter</span>
                    <span>La distance de zéro, le lot de plombs et la position des colliers, une fois pour toutes.</span>
                </li>
                <li>
                    <span class="glab-takeaway-label">Accepter</span>
                    <span>Ailleurs qu'au zéro, l'impact est plus haut ou plus bas. Toujours.</span>
                </li>
            </ul>
        </section>

        <section class="glab-section" aria-labelledby="opt-unites-title">
            <h2 class="glab-title" id="opt-unites-title">Trois mots, <span class="glab-title-accent">une seule idée</span></h2>
            <p class="glab-lede">
                MOA, MRAD, clic : trois façons de désigner un angle. Et un angle couvre d'autant
                plus de terrain que la cible est loin.
            </p>

            <dl class="glab-specs glab-place-cards">
                <div>
                    <dt>
                        MOA
                        <span class="glab-spec-kicker">2,91 cm à 100 m</span>
                    </dt>
                    <dd>La minute d'angle, un soixantième de degré. Elle couvre 2,908 cm à 100 mètres, et à peu près un pouce à 100 yards, ce qui explique les cibles à grille en pouces. Les tourelles courantes avancent d'un quart de MOA par clic, certaines d'un huitième.</dd>
                </div>
                <div>
                    <dt>
                        MRAD
                        <span class="glab-spec-kicker">10 cm à 100 m</span>
                    </dt>
                    <dd>Le milliradian, un millième de radian. Il couvre exactement 10 cm à 100 mètres, 20 cm à 200, et se marie au mètre sans conversion. Les tourelles avancent le plus souvent de 0,1 MRAD par clic, soit 1 cm à 100 mètres.</dd>
                </div>
                <div>
                    <dt>
                        Le clic
                        <span class="glab-spec-kicker">La fraction que la tourelle avance</span>
                    </dt>
                    <dd>Ce n'est pas une unité, c'est un cran. Sa valeur est écrite sur la tourelle, en MOA ou en MRAD, et ce qu'il déplace en centimètres change avec la distance. Un clic n'a donc pas de valeur en soi : il en a une à 10 mètres, une autre à 50.</dd>
                </div>
            </dl>

            <div class="glab-prose">
                <p>
                    La règle à retenir tient en une phrase : le réticule et les tourelles doivent
                    parler la même langue. Un réticule gradué en MOA sous une tourelle en MRAD
                    vous oblige à convertir à chaque correction, debout au pas de tir, et c'est
                    exactement là que les erreurs entrent. Si le choix vous appartient, prenez le
                    MRAD pour sa décimalité, le MOA si vos cibles et vos habitudes sont en pouces.
                </p>
            </div>
        </section>

        <section class="glab-section" aria-labelledby="opt-calc-title">
            <h2 class="glab-title" id="opt-calc-title">Le convertisseur <span class="glab-title-accent">de clics</span></h2>
            <p class="glab-lede">
                Mesurez l'écart entre le centre de votre groupement et le point visé, dites à
                quelle distance, et lisez le nombre de crans à tourner sur chaque tourelle.
            </p>

            {{-- The instrument, in one element: the fields, the two answers and
                 the ladder underneath, so the script mounts once and re-renders
                 all three. The defaults are rendered server-side and are the
                 answer the script would give for those same inputs. --}}
            <div data-glab-clicks>
                <div class="glab-calc-grid">
                    <div class="glab-calc-fields">
                        <div class="glab-calc-field">
                            <label for="opt-distance">Distance de tir, en mètres</label>
                            <input type="number" id="opt-distance" min="1" max="600" step="1" value="25" data-glab-distance>
                        </div>
                        <div class="glab-calc-field">
                            <label for="opt-turret">Valeur d'un clic</label>
                            <select id="opt-turret" data-glab-turret>
                                <option value="0.25|moa">1/4 MOA par clic</option>
                                <option value="0.125|moa">1/8 MOA par clic</option>
                                <option value="0.5|moa">1/2 MOA par clic</option>
                                <option value="0.1|mrad">0,1 MRAD par clic</option>
                                <option value="0.05|mrad">0,05 MRAD par clic</option>
                            </select>
                        </div>
                        <div class="glab-calc-field">
                            <label for="opt-drop">Écart vertical du groupement</label>
                            <div class="glab-calc-speed">
                                <input type="number" id="opt-drop" min="0" max="200" step="0.1" value="4" data-glab-drop>
                                <select aria-label="Sens de l'écart vertical" data-glab-drop-way>
                                    <option value="vers le haut">trop bas</option>
                                    <option value="vers le bas">trop haut</option>
                                </select>
                            </div>
                        </div>
                        <div class="glab-calc-field">
                            <label for="opt-drift">Écart horizontal du groupement</label>
                            <div class="glab-calc-speed">
                                <input type="number" id="opt-drift" min="0" max="200" step="0.1" value="2" data-glab-drift>
                                <select aria-label="Sens de l'écart horizontal" data-glab-drift-way>
                                    <option value="vers la droite">trop à gauche</option>
                                    <option value="vers la gauche">trop à droite</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="glab-calc-result">
                        <div class="glab-clicks">
                            <div class="glab-click">
                                <span class="glab-click-axis">Hausse</span>
                                <span class="glab-click-count" data-glab-drop-count>22</span>
                                <span class="glab-click-way" data-glab-drop-label>clics vers le haut</span>
                            </div>
                            <div class="glab-click">
                                <span class="glab-click-axis">Dérive</span>
                                <span class="glab-click-count" data-glab-drift-count>11</span>
                                <span class="glab-click-way" data-glab-drift-label>clics vers la droite</span>
                            </div>
                        </div>
                        <p class="glab-calc-band" data-glab-band>À 25 m, un clic déplace l'impact de 1,8 mm.</p>
                    </div>
                </div>

                <div class="glab-calc-limits">
                    <h3>Ce que vaut ce clic, distance par distance</h3>
                    <ul>
                        <li>
                            <span>10 m</span>
                            <strong data-glab-rung>0,7 mm</strong>
                        </li>
                        <li>
                            <span>25 m</span>
                            <strong data-glab-rung>1,8 mm</strong>
                        </li>
                        <li>
                            <span>50 m</span>
                            <strong data-glab-rung>3,6 mm</strong>
                        </li>
                        <li class="is-reference">
                            <span>100 m</span>
                            <strong data-glab-rung>7,3 mm</strong>
                        </li>
                    </ul>
                    <p class="glab-calc-note">
                        Voilà pourquoi une correction de trois centimètres demande une quarantaine
                        de clics à dix mètres et quatre à cent : le clic est un angle, et l'angle
                        ne couvre pas la même largeur selon la distance. Ce n'est pas une lunette
                        fatiguée. Le sens des tourelles se lit sur les graduations : la plupart
                        sont gravées <em>UP</em> ou <em>H</em> pour la hausse, <em>R</em> ou
                        <em>D</em> pour la dérive à droite.
                    </p>
                </div>
            </div>
        </section>

        <section class="glab-section" aria-labelledby="opt-seance-title">
            <h2 class="glab-title" id="opt-seance-title">La séance de réglage, <span class="glab-title-accent">dans l'ordre</span></h2>
            <p class="glab-lede">
                Six gestes. L'ordre compte plus que chacun d'eux, parce que chaque étape sautée
                se paie en munitions et en doute.
            </p>

            <ol class="glab-rules">
                <li>
                    <h3>Poser l'arme, pas la tenir</h3>
                    <p>
                        Tout ce que vous mesurez ensuite suppose que l'arme ne bouge pas. Un sac de
                        tir, un tapis, un bipied ou un simple sac de riz sous la crosse : le but est
                        que la seule variable de la séance soit la lunette. Une carabine tenue à
                        l'épaule vous fera corriger votre propre tremblement. Vérifiez au passage le
                        serrage des colliers, qui est la cause la plus fréquente d'un zéro qui
                        s'évade.
                    </p>
                </li>
                <li>
                    <h3>Un seul lot, un seul plomb</h3>
                    <p>
                        Le zéro appartient au couple arme-projectile. Changer de marque, de poids ou
                        même de boîte suffit à décaler le point d'impact, et une séance de réglage
                        conduite avec trois lots différents ne règle rien. Choisissez celui que vous
                        tirerez ensuite, et notez-le.
                    </p>
                </li>
                <li>
                    <h3>Trois coups, jamais un</h3>
                    <p>
                        Un impact isolé ne dit rien : il peut venir de vous, de l'appui ou du plomb.
                        Trois à cinq coups, sans toucher à l'arme entre les deux, dessinent un
                        groupement, et c'est le groupement qu'on corrige. Si les trois coups
                        s'éparpillent sur dix centimètres, arrêtez : le problème n'est pas le
                        réglage.
                    </p>
                </li>
                <li>
                    <h3>Mesurer depuis le centre du groupement</h3>
                    <p>
                        Depuis le centre, pas depuis le trou le plus proche du point visé, et
                        jusqu'au point visé, pas jusqu'au centre de la cible si vous avez visé
                        ailleurs. Une cible à grille rend cette mesure immédiate : on lit deux
                        nombres, un vertical et un horizontal, et on les entre dans le convertisseur
                        ci-dessus.
                    </p>
                </li>
                <li>
                    <h3>Tourner, puis retirer trois coups</h3>
                    <p>
                        On applique les deux corrections, on retire une série, on vérifie. La
                        tentation est de tourner puis de recommencer à mesurer sur le même carton :
                        les impacts se mélangent et la lecture devient fausse. Une pastille
                        autocollante rebouche la série précédente et rend le carton neuf pour trois
                        centimes.
                    </p>
                </li>
                <li>
                    <h3>Reculer par paliers</h3>
                    <p>
                        Régler d'emblée à cinquante mètres coûte des munitions à chercher le papier.
                        On centre à dix ou quinze mètres, on recule à vingt-cinq, puis à cinquante
                        si l'arme et le stand le permettent. Chaque palier demande moins de clics
                        que le précédent, ce qui est exactement l'inverse de l'intuition, et
                        exactement ce que dit le tableau plus haut.
                    </p>
                </li>
            </ol>

            <p class="glab-more-reading">
                Quelle cible pour quelle distance, et combien de feuilles prévoir, est le sujet de
                notre guide <a href="{{ route('guides.cibles') }}">Bien choisir sa cible</a> ; les
                mots de la fiche technique sont au <a href="{{ route('guides.glossaire') }}">glossaire</a>.
            </p>
        </section>

        <section class="glab-section" aria-labelledby="opt-parallaxe-title">
            <h2 class="glab-title" id="opt-parallaxe-title">La parallaxe, <span class="glab-title-accent">l'autre réglage</span></h2>
            <p class="glab-lede">
                Celui qu'on oublie, et qui déplace le point d'impact sans qu'on ait touché à une
                seule tourelle.
            </p>

            <div class="glab-prose">
                <p>
                    Dans une lunette, l'image de la cible et le réticule se forment sur deux plans.
                    Quand ces deux plans ne coïncident pas, bouger l'œil derrière l'oculaire fait
                    nager le réticule sur la cible : c'est la parallaxe. Le tir part alors où
                    l'œil était placé, pas où vous croyiez viser, et deux séances menées avec deux
                    positions de joue donnent deux zéros.
                </p>
                <p>
                    Le remède est une bague, en bout d'objectif ou sur le côté, graduée en mètres
                    ou en yards. On la tourne jusqu'à ce que l'image soit nette <em>et</em> que le
                    réticule cesse de bouger quand on remue légèrement la tête. Beaucoup de
                    lunettes bon marché n'en ont pas : elles sont figées à une distance donnée,
                    souvent une centaine de mètres pour les modèles pensés pour l'arme à feu, ce
                    qui les rend inconfortables à dix mètres. Pour la carabine à plomb, cherchez
                    une parallaxe réglable descendant à dix mètres, ou une lunette figée courte.
                </p>
            </div>

            <ul class="glab-takeaways">
                <li>
                    <span class="glab-takeaway-label">Le test</span>
                    <span>Remuez la tête derrière l'oculaire : si le réticule bouge sur la cible, la parallaxe est fausse.</span>
                </li>
                <li>
                    <span class="glab-takeaway-label">Avant les tourelles</span>
                    <span>On règle la parallaxe et la netteté du réticule d'abord. Sinon on corrige un défaut avec l'autre.</span>
                </li>
                <li>
                    <span class="glab-takeaway-label">À dix mètres</span>
                    <span>Une lunette figée à 100 m est inutilisable au stand court. Vérifiez la plage avant d'acheter.</span>
                </li>
            </ul>
        </section>

        <section class="glab-section" aria-labelledby="opt-derive-title">
            <h2 class="glab-title" id="opt-derive-title">Ce qui dérègle <span class="glab-title-accent">un zéro déjà bon</span></h2>
            <p class="glab-lede">
                Avant de retoucher les tourelles, éliminez les cinq causes qui n'ont rien à voir
                avec elles.
            </p>

            <ul class="glab-takeaways">
                <li>
                    <span class="glab-takeaway-label">Les colliers</span>
                    <span>Ils se desserrent, surtout sur une carabine à ressort dont le recul part dans les deux sens. Vingt à trente coups suffisent.</span>
                </li>
                <li>
                    <span class="glab-takeaway-label">Le lot</span>
                    <span>Un poids ou une marque de plomb différents déplacent le point d'impact. Le zéro appartient au couple, pas à l'arme seule.</span>
                </li>
                <li>
                    <span class="glab-takeaway-label">La parallaxe</span>
                    <span>Bague déréglée, joue posée ailleurs : le réticule nage et le tir suit l'œil.</span>
                </li>
                <li>
                    <span class="glab-takeaway-label">La distance</span>
                    <span>Le stand d'aujourd'hui n'est pas celui d'hier. Un télémètre coûte moins cher qu'une séance passée à corriger une distance supposée.</span>
                </li>
                <li>
                    <span class="glab-takeaway-label">Vous</span>
                    <span>Appui différent, épaulé différent, fatigue de fin de séance. C'est la cause la plus fréquente, et la seule qui ne se corrige pas au tournevis.</span>
                </li>
            </ul>
        </section>

        <section class="glab-section" aria-labelledby="opt-myths-title">
            <h2 class="glab-title" id="opt-myths-title">Trois choses <span class="glab-title-accent">qu'on lit partout</span></h2>
            <p class="glab-lede">
                Elles coûtent des munitions, et parfois une lunette qu'on croit morte.
            </p>

            <div class="glab-myths">
                <article class="glab-myth">
                    <p class="glab-myth-flag">On lit</p>
                    <p class="glab-myth-claim">« Un clic, c'est un centimètre. »</p>
                    <p class="glab-myth-flag glab-myth-flag--truth">En réalité</p>
                    <p class="glab-myth-truth">
                        Uniquement pour une tourelle de 0,1 MRAD, et uniquement à 100 mètres. La
                        même tourelle ne déplace l'impact que de 2,5 mm à 25 mètres. En 1/4 MOA,
                        le clic ne vaut jamais un centimètre rond à aucune distance ronde.
                    </p>
                </article>
                <article class="glab-myth">
                    <p class="glab-myth-flag">On lit</p>
                    <p class="glab-myth-claim">« Plus de grossissement, plus de précision. »</p>
                    <p class="glab-myth-flag glab-myth-flag--truth">En réalité</p>
                    <p class="glab-myth-truth">
                        Le grossissement agrandit la cible et le tremblement avec elle, assombrit
                        l'image et rend la parallaxe critique. À dix mètres, un 4x confortable bat
                        un 24x qu'on n'arrive pas à stabiliser. Le grossissement ne rend pas
                        l'arme plus précise, il rend l'erreur plus visible.
                    </p>
                </article>
                <article class="glab-myth">
                    <p class="glab-myth-flag">On lit</p>
                    <p class="glab-myth-claim">« Un laser de canon suffit à régler. »</p>
                    <p class="glab-myth-flag glab-myth-flag--truth">En réalité</p>
                    <p class="glab-myth-truth">
                        Le prérèglage optique aligne grossièrement la lunette sur l'axe du canon,
                        et son mérite est de vous faire atteindre le papier au premier coup. Il ne
                        connaît ni la trajectoire, ni votre plomb, ni votre distance : le zéro se
                        finit à la cible, jamais autrement.
                    </p>
                </article>
            </div>
        </section>

        <section class="glab-faq" aria-labelledby="opt-faq-title">
            <h2 class="glab-title" id="opt-faq-title">Questions <span class="glab-title-accent">fréquentes</span></h2>
            @foreach ($faq as $qa)
                <details>
                    <summary>{{ $qa[0] }}</summary>
                    <div>
                        <p>{{ $qa[1] }}</p>
                    </div>
                </details>
            @endforeach
        </section>

        <section class="glab-section" aria-labelledby="opt-sources-title">
            <h2 class="glab-title" id="opt-sources-title">Les <span class="glab-title-accent">sources</span></h2>
            <p class="glab-lede">
                Les valeurs d'angle et les protocoles de réglage recoupés page par page. Les
                chiffres de cette page se vérifient, et devraient l'être.
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

        <p class="glab-more-reading">
            La boutique ne vend pas de lunette de visée : elle vend ce qui rend le réglage
            lisible. Les <a href="{{ route('categories.show', 'cibles-carrees') }}">cibles à grille</a>
            pour mesurer, les <a href="{{ route('categories.show', 'pastilles-autocollantes') }}">pastilles</a>
            pour reboucher, les <a href="{{ route('categories.show', 'longues-vues') }}">longues-vues</a>
            pour voir vos impacts sans quitter le poste, les
            <a href="{{ route('categories.show', 'telemetres') }}">télémètres</a> pour connaître la
            distance au lieu de la supposer.
        </p>

        <p class="glab-ctas">
            <a href="{{ route('categories.show', 'cibles-carrees') }}" class="btn btn-primary">Cibles à grille</a>
            <a href="{{ route('categories.show', 'optiques') }}" class="btn btn-secondary">Le rayon optiques</a>
        </p>
    </div>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/guides.js') }}" defer></script>
@endpush
