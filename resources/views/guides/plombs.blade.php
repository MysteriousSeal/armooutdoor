@extends('layouts.app')

@php
    // One grain is exactly 64.79891 milligrams; the page works in grams.
    $grain = 0.06479891;
    $foot = 0.3048;

    // Hard Air Magazine places a diabolo's transonic region from 900 fps:
    // the calculator, the table and the questions all measure against it.
    $transonic = 900 * $foot;

    $fr = fn (float $value, int $decimals = 0): string => number_format($value, $decimals, ',', ' ');

    // Muzzle speed in fps for an energy in joules and a weight in grains,
    // v = √(2E / m), the rifle assumed to give the same energy whatever it
    // is fed. The page says so beside every result.
    $fps = fn (float $joules, float $grains): float => sqrt(2 * $joules / ($grains * $grain / 1000)) / $foot;

    // The lightest pellet that still leaves under 900 fps, in grams.
    $floor = fn (float $joules): float => 2 * $joules / ($transonic ** 2) * 1000;

    // The calculator's opening state, rendered here so the markup already
    // holds a true answer when the script reveals it.
    $start = ['joules' => 19.0, 'grains' => 8.18];
    $startFps = $fps($start['joules'], $start['grains']);

    $energies = [5, 10, 15, 19.9, 24];

    // Three tins the manufacturer's pages describe: two 4,5 mm, one 5,5 mm.
    $tins = [
        ['name' => 'Finale Match Heavy', 'calibre' => '4,5 mm', 'grains' => 8.18],
        ['name' => 'Field Target Trophy', 'calibre' => '4,5 mm', 'grains' => 8.64],
        ['name' => 'Field Target Trophy', 'calibre' => '5,5 mm', 'grains' => 14.66],
    ];

    // Four pellets drawn in profile, flying right: the skirt and waist are
    // shared, only the nose changes, which is the whole point of the row.
    $bodyTop = 'M8,11 L70,22 L78,22 L82,12 L104,12';
    $bodyBottom = 'L104,58 L82,58 L78,48 L70,48 L8,59 Z';
    $heads = [
        ['key' => 'plate', 'name' => 'Tête plate', 'kicker' => 'Carton à 10 m', 'nose' => 'L108,14 L108,56 L104,58', 'read' => 'Un trou net, qui se compte.', 'body' => 'Son avant presque plat découpe dans le papier un trou rond et propre : c\'est la tête des plombs de match à 10 mètres. La forme se paie en vol : son coefficient balistique, qui dit à quel point un projectile garde sa vitesse dans l\'air, n\'est que d\'environ 0,010, et la tête plate ralentit et dérive d\'autant plus que la cible s\'éloigne.'],
        ['key' => 'ronde', 'name' => 'Tête ronde', 'kicker' => 'Loisir et distance', 'nose' => 'C122,12 130,24 130,35 C130,46 122,58 104,58', 'read' => 'Celle qui garde sa vitesse.', 'body' => 'Hard Air Magazine prend 0,030 comme coefficient balistique typique d\'une tête ronde, trois fois celui d\'une tête plate : moins de chute et de dérive quand la distance s\'allonge. H&N annonce son Field Target Trophy, une tête ronde, jusqu\'à 50 mètres.'],
        ['key' => 'pointue', 'name' => 'Tête pointue', 'kicker' => 'Pensée pour l\'impact', 'nose' => 'C116,14 128,26 136,35 C128,44 116,56 104,58', 'read' => 'Un argument de pénétration, pas de groupement.', 'body' => 'La pointe est vendue pour entrer dans une cible vivante. En France, la chasse et la destruction des nuisibles à l\'arme à air sont interdites : sur carton, la pointue se juge donc comme les autres, au groupement.'],
        ['key' => 'creuse', 'name' => 'Tête creuse', 'kicker' => 'Pensée pour l\'impact', 'nose' => 'C113,12 119,19 120,24 L111,29 L111,41 L120,46 C119,51 113,58 104,58', 'read' => 'Une cavité à l\'avant, pour l\'impact.', 'body' => 'Le creux au bout de la tête reprend l\'idée de la balle à pointe creuse. Même logique que la pointue, même limite chez nous : sur carton, seul le groupement départage deux têtes creuses.'],
    ];

    // Head diameters as the manufacturer prints them, one tin a card.
    $diameters = [
        ['size' => '4,49 à 4,50 mm', 'tin' => 'H&N Finale Match Heavy', 'body' => 'Un plomb de match de 8,18 grains, trié à la main, en boîtes de 500.'],
        ['size' => '4,50 à 4,52 mm', 'tin' => 'H&N Field Target Trophy 4,5 mm', 'body' => 'Une tête ronde de 8,64 grains, annoncée pour les distances moyennes, jusqu\'à 50 mètres.'],
        ['size' => '5,53 à 5,55 mm', 'tin' => 'H&N Field Target Trophy 5,5 mm', 'body' => 'La même tête en 5,5 mm : 14,66 grains, en boîtes de 250.'],
    ];

    $myths = [
        ['« Plus lourd, c\'est plus précis. »', 'Un plomb lourd aide une carabine puissante à rester sous la zone transsonique. Pour le reste, aucun essai publié ne prédit le groupement dans votre canon.'],
        ['« Une jupe un peu écrasée, ça ne se voit pas. »', 'Airgun World a vu des plombs à peine déformés s\'écarter de 1,5 à 2 pouces (3,8 à 5 cm) du groupement. Ils ne devraient pas abîmer la carabine, mais ils coûtent en précision.'],
        ['« Le plomb va être interdit, autant passer au sans-plomb. »', 'Le projet européen de juillet 2026 laisse les plombs d\'armes à air hors de la restriction. Le sans-plomb se choisit pour ce qu\'il donne dans votre canon.'],
    ];

    $pair = [$fps(19, 8.64), $fps(19, 14.66)];
    $floor199 = $floor(19.9);

    $faq = [
        ['Quel plomb pour une carabine à air de moins de 20 joules ?', 'Pour le carton à 10 mètres, une tête plate en 4,5 mm ; pour tirer plus loin, une tête ronde. Côté poids, une carabine réglée à 19,9 joules envoie un plomb de moins de '.$fr($floor199 / $grain, 2).' grains ('.$fr($floor199, 2).' g) à 900 FPS ou plus, là où la résistance de l\'air grimpe vite. Au-dessus de ce poids, c\'est le groupement dans votre canon qui décide.'],
        ['Plomb tête plate ou tête ronde ?', 'La tête plate découpe un trou net dans le carton : c\'est la tête des plombs de match à 10 mètres. La tête ronde est plus aérodynamique et dérive moins quand la distance s\'allonge : Hard Air Magazine lui prête un coefficient balistique typique de 0,030, contre environ 0,010 pour une tête plate. De près la plate, plus loin la ronde, et entre les deux, votre canon tranche.'],
        ['Plomb 4,5 ou 5,5 mm ?', 'Celui de votre canon : le calibre est fixé par la carabine, et c\'est à son achat qu\'il se choisit. Le 4,5 mm est le calibre des épreuves à 10 mètres et part plus vite à énergie égale : à 19 joules, un Field Target Trophy de 8,64 grains sort à '.$fr($pair[0]).' FPS. Le 5,5 mm, plus lourd, part moins vite ('.$fr($pair[1]).' FPS avec 14,66 grains) et demande plus d\'énergie : au moins 12 ft.lbs selon H&N, contre 5,5 ft.lbs en 4,5 mm.'],
        ['Combien pèse un plomb en grammes ?', 'Un grain vaut exactement 64,79891 milligrammes. Un plomb de 8,18 grains pèse donc '.$fr(8.18 * $grain, 2).' g, un plomb de 14,66 grains '.$fr(14.66 * $grain, 2).' g. Pour passer des grains aux grammes, multipliez par 0,0648.'],
        ['Que veut dire 4,50 ou 4,52 sur une boîte de plombs ?', 'C\'est le diamètre de la tête, en millimètres, pour un même calibre de 4,5 mm. H&N vend son Finale Match Heavy en 4,49 à 4,50 mm et son Field Target Trophy en 4,50 à 4,52 mm, parce que chaque canon est légèrement différent. Le chiffre reste indicatif : sur un lot annoncé à 4,51 mm, Hard Air Magazine a mesuré 78 % des plombs à 4,53 mm.'],
        ['Peut-on tirer les nuisibles à la carabine à air ?', 'Non. L\'arrêté du 1er août 1986 interdit l\'emploi des armes à air ou gaz comprimé pour la chasse de tout gibier et pour la destruction des animaux classés susceptibles d\'occasionner des dégâts. Les têtes creuses et pointues, pensées pour l\'impact, n\'ont pas cet usage en France.'],
        ['Les plombs en plomb vont-ils être interdits ?', 'Pas d\'après le projet de règlement européen transmis au Conseil le 10 juillet 2026 : la Commission y juge injustifié de restreindre les plombs d\'armes à air, dont les alternatives sont rares, moins précises et jusqu\'à quatre fois plus chères. Le Parlement européen et le Conseil ont jusqu\'au 10 octobre 2026 pour s\'y opposer ; sans objection, la Commission pourra l\'adopter.'],
        ['Faut-il se laver les mains après avoir manipulé des plombs ?', 'Oui. Le plomb entre dans le corps par la bouche, avec des mains sales, ou par le nez, avec les poussières. L\'INRS recommande de se laver les mains et le visage avant les repas et de ne pas boire, manger ni fumer là où l\'on est exposé, et il cite les stands de tir parmi les lieux concernés.'],
    ];

    $sources = [
        ['label' => 'Code de la sécurité intérieure, article R311-2 (catégories C et D, seuils de 2 et 20 joules)', 'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000052208392', 'host' => 'legifrance.gouv.fr'],
        ['label' => 'Arrêté du 1er août 1986, article 1er (armes à air interdites pour la chasse et la destruction)', 'url' => 'https://www.legifrance.gouv.fr/loda/id/JORFTEXT000000862758/', 'host' => 'legifrance.gouv.fr'],
        ['label' => 'Règles carabine ISSF diffusées par la FFTir, règle 7.4.6 (munitions)', 'url' => 'https://www.fftir.org/wp-content/uploads/2023/06/Reglement_ISSF_2022_Carabine_Diffusion.pdf', 'host' => 'fftir.org'],
        ['label' => 'H&N, Finale Match Heavy .177 (diamètres de tête, poids)', 'url' => 'https://www.hn-sport.de/en/target-shooting/finale-match-heavy-177', 'host' => 'hn-sport.de'],
        ['label' => 'H&N, Field Target Trophy .177 (diamètres, poids, distance, énergie minimale)', 'url' => 'https://www.hn-sport.de/en/air-gun-hunting-target-shooting/field-target-trophy-177', 'host' => 'hn-sport.de'],
        ['label' => 'H&N, Field Target Trophy .22 (diamètres, poids, énergie minimale)', 'url' => 'https://www.hn-sport.de/en/air-gun-hunting-target-shooting/field-target-trophy-22', 'host' => 'hn-sport.de'],
        ['label' => 'Pyramyd AIR, H&N Field Target Trophy .22 (tête ronde)', 'url' => 'https://www.pyramydair.com/product/h-n-field-target-trophy-22-cal-14-66-grains-round-nose-250ct?p=913', 'host' => 'pyramydair.com'],
        ['label' => 'Hard Air Magazine, essai du H&N Baracuda 8 (diamètres mesurés, essai dans son canon)', 'url' => 'https://hardairmagazine.com/reviews/hn-baracuda-8-pellet-test-review-177-caliber-8-44-grain/', 'host' => 'hardairmagazine.com'],
        ['label' => 'Hard Air Magazine, la balistique extérieure des diabolos (zone transsonique, coefficients)', 'url' => 'https://hardairmagazine.com/ham-columns/the-external-ballistics-of-diabolo-pellets/', 'host' => 'hardairmagazine.com'],
        ['label' => 'Airgun World, peut-on tirer des plombs abîmés ?', 'url' => 'https://airgun-world.com/shooting/is-it-safe-to-use-damaged-pellets/', 'host' => 'airgun-world.com'],
        ['label' => 'Wikipedia, Pellet (air gun) : têtes, jupe, sans-plomb, stabilité', 'url' => 'https://en.wikipedia.org/wiki/Pellet_(air_gun)', 'host' => 'en.wikipedia.org'],
        ['label' => 'Wikipedia, Grain (unit) : 64,79891 milligrammes', 'url' => 'https://en.wikipedia.org/wiki/Grain_(unit)', 'host' => 'en.wikipedia.org'],
        ['label' => 'INRS, plomb : ce qu\'il faut retenir (voies d\'exposition, hygiène)', 'url' => 'https://www.inrs.fr/risques/plomb/ce-qu-il-faut-retenir.html', 'host' => 'inrs.fr'],
        ['label' => 'Conseil de l\'Union européenne, document 11840/26 : projet de règlement REACH sur le plomb', 'url' => 'https://data.consilium.europa.eu/doc/document/ST-11840-2026-INIT/en/pdf', 'host' => 'consilium.europa.eu'],
        ['label' => 'FACE, fédération européenne des chasseurs, 20 juillet 2026 : le texte à l\'examen du Parlement et du Conseil jusqu\'au 10 octobre 2026', 'url' => 'https://www.face.eu/2026/07/europes-lead-gunshot-restriction-enters-final-eu-scrutiny-phase/', 'host' => 'face.eu'],
    ];

    $description = 'Tête plate ou ronde, 4,5 ou 5,5 mm, grains et grammes : quel plomb pour sa carabine à air selon sa puissance et son usage, calcul et sources à l\'appui.';
@endphp

@section('title', 'Quel plomb pour sa carabine à air ? - Armo Outdoor')
@section('meta_description', $description)
@section('og_type', 'article')
@section('canonical', route('guides.plombs'))

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/categories.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('css/guides.css') }}">
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'Article',
            'headline' => 'Quel plomb pour sa carabine à air ?',
            'description' => $description,
            'mainEntityOfPage' => route('guides.plombs'),
            'inLanguage' => 'fr-FR',
            'image' => versioned_asset(\App\Support\Guides::byRoute('guides.plombs')['image'] ?? 'images/hero.webp'),
            'datePublished' => \App\Support\Guides::byRoute('guides.plombs')['published'],
            'dateModified' => \App\Support\Guides::byRoute('guides.plombs')['updated'],
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
                ['@@type' => 'ListItem', 'position' => 3, 'name' => 'Quel plomb choisir', 'item' => route('guides.plombs')],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('content')
    <div class="container glab glab--plombs">
        @include('guides.partials.hero', [
            'crumb' => 'Quel plomb choisir',
            'kicker' => 'Munitions',
            'title' => 'Quel plomb pour sa carabine à air ?',
            'lede' => 'Tête plate ou ronde, 4,5 ou 5,5 mm, 8 ou 14 grains : le rayon plombs se lit mieux quand on sait ce que dit chaque chiffre de la boîte. Ce guide relie la forme de la tête au tir, le poids à la puissance de la carabine, le diamètre de tête au canon, et dit pourquoi le dernier mot revient toujours à un carton tiré chez soi.',
            'tags' => ['4,5 mm', '5,5 mm', 'Grains'],
        ])

        <nav class="glab-plan" aria-label="Plan du guide">
            <p class="glab-plan-kicker">Plan</p>
            <div class="glab-plan-links">
                <a href="#plombs-banc-title">Le calcul</a>
                <a href="#plombs-tetes-title">Les têtes</a>
                <a href="#plombs-calibre-title">4,5 ou 5,5 mm</a>
                <a href="#plombs-poids-title">Le poids</a>
                <a href="#plombs-diametre-title">Le diamètre</a>
                <a href="#plombs-essai-title">L'essai</a>
                <a href="#plombs-sans-title">Sans plomb</a>
                <a href="#plombs-mains-title">Les mains</a>
                <a href="#plombs-idees-title">Idées reçues</a>
                <a href="#plombs-faq-title">Questions</a>
                <a href="#plombs-sources-title">Sources</a>
            </div>
        </nav>

        <p class="glab-warning">
            <strong class="glab-warning-label">Ni chasse ni nuisibles</strong>
            En France, l'emploi des armes à air ou gaz comprimé est interdit pour la chasse de tout gibier et pour la destruction des animaux classés susceptibles d'occasionner des dégâts, les anciens nuisibles (arrêté du 1er août 1986). Ce guide juge les plombs sur carton.
        </p>

        {{-- The calculator. Hidden until the script runs: the floor table
             further down is the same answer for a reader without JavaScript. --}}
        <section class="glab-section" data-glab-plombs hidden aria-labelledby="plombs-banc-title">
            <h2 class="glab-title" id="plombs-banc-title">Votre carabine, <span class="glab-title-accent">votre plomb</span></h2>
            <p class="glab-lede">Entrez l'énergie de la carabine, le poids lu sur la boîte et ce que vous tirez. La page calcule la vitesse de départ, et le poids sous lequel le plomb entre dans la zone transsonique : à l'approche de la vitesse du son, la traînée, c'est-à-dire la résistance de l'air, y grimpe vite.</p>

            <div class="glab-calc-grid">
                <div class="glab-calc-fields">
                    <div class="glab-calc-field">
                        <label for="plombs-energy">Énergie de la carabine, en joules</label>
                        <input type="number" id="plombs-energy" step="0.1" min="1" max="100" value="19" data-glab-energy>
                    </div>
                    <div class="glab-calc-field">
                        <label for="plombs-weight">Poids du plomb, lu sur la boîte</label>
                        <div class="glab-calc-speed">
                            <input type="number" id="plombs-weight" step="0.01" min="0.01" max="100" value="8.18" data-glab-weight>
                            <select aria-label="Unité de poids" data-glab-weight-unit>
                                <option value="gr">grains</option>
                                <option value="g">grammes</option>
                            </select>
                        </div>
                    </div>
                    <div class="glab-calc-field" role="group" aria-labelledby="plombs-usage-label">
                        <span id="plombs-usage-label">Ce que vous tirez</span>
                        <div class="glab-chips glab-chips--range" data-glab-usage>
                            <button type="button" data-glab-value="carton" class="is-active" aria-pressed="true">Carton à 10 m</button>
                            <button type="button" data-glab-value="loisir" aria-pressed="false">Loisir</button>
                            <button type="button" data-glab-value="distance" aria-pressed="false">Jusqu'à 50 m</button>
                        </div>
                    </div>
                </div>

                <output class="glab-calc-result" for="plombs-energy plombs-weight">
                    <span class="glab-calc-energy"><strong data-glab-fps>{{ $fr($startFps) }}</strong> FPS au départ</span>
                    <span class="glab-calc-equiv" data-glab-mass>{{ $fr($startFps * $foot) }} m/s · {{ $fr($start['grains'], 2) }} gr = {{ $fr($start['grains'] * $grain, 3) }} g</span>
                    <span
                        class="glab-calc-band"
                        data-glab-zone
                        data-glab-under="Sous 900 FPS : hors de la zone transsonique"
                        data-glab-over="900 FPS ou plus : zone transsonique, la traînée grimpe vite"
                    >Sous 900 FPS : hors de la zone transsonique</span>
                </output>
            </div>

            {{-- The signature: a speed axis whose last third is hatched, the
                 region where a diabolo's drag climbs. Linear to 1 400 fps. --}}
            <figure class="glab-speed" data-glab-speed style="--glab-speed-at: {{ round(min(100, $startFps / 1400 * 100), 2) }}%">
                <div class="glab-speed-marks" aria-hidden="true">
                    <span style="--glab-mark-at: 0%">0</span>
                    <span style="--glab-mark-at: {{ round(900 / 1400 * 100, 2) }}%">900 FPS</span>
                    <span style="--glab-mark-at: 100%">1 400</span>
                </div>
                <div class="glab-speed-track">
                    <span class="glab-speed-zone" style="--glab-zone-from: {{ round(900 / 1400 * 100, 2) }}%"></span>
                    <span class="glab-speed-needle"></span>
                </div>
                <figcaption class="glab-speed-legend">
                    <span>Sous 900 FPS</span>
                    <span>Zone transsonique</span>
                </figcaption>
            </figure>

            <div class="glab-calc-limits">
                <h3>Avec cette carabine</h3>
                <ul>
                    <li><span>Poids plancher</span> <strong data-glab-floor-gr>{{ $fr($floor($start['joules']) / $grain, 2) }} gr</strong></li>
                    <li><span>Soit</span> <strong data-glab-floor-g>{{ $fr($floor($start['joules']), 2) }} g</strong></li>
                    <li class="is-law"><span>Classement</span> <strong data-glab-category>Catégorie D</strong></li>
                </ul>
                <p class="glab-calc-note">
                    Sous le poids plancher, le plomb part à 900 FPS ou plus. Le calcul suppose que la carabine donne la même énergie quel que soit le plomb : c'est une approximation, et un chronographe tranche (voir <a href="{{ route('guides.joules') }}">Joules et FPS</a>). Le classement suit l'article R311-2 : catégorie D de 2 à 20 joules, catégorie C à 20 joules et plus.
                </p>
            </div>

            <div class="glab-reco glab-plombs-usage">
                <p class="glab-reco-label">La tête pour cet usage</p>
                <div data-glab-usage-card="carton">
                    <p class="glab-reco-resume">Tête plate, en 4,5 mm</p>
                    <p>Le trou net d'une tête plate se compte sur le carton, et le 4,5 mm est le calibre des épreuves à 10 mètres. Trouvez le diamètre de tête qui groupe dans votre canon, puis gardez-le.</p>
                </div>
                <div data-glab-usage-card="loisir" hidden>
                    <p class="glab-reco-resume">Tête plate ou ronde, au groupement</p>
                    <p>L'écart aérodynamique entre les deux se creuse avec la distance ; de près, la tête plate marque plus lisiblement. Essayez les deux : votre canon choisit.</p>
                </div>
                <div data-glab-usage-card="distance" hidden>
                    <p class="glab-reco-resume">Tête ronde</p>
                    <p>Son coefficient balistique, typiquement 0,030 contre environ 0,010 pour une tête plate, limite la dérive quand la distance s'allonge. H&amp;N annonce son Field Target Trophy jusqu'à 50 mètres.</p>
                </div>
            </div>
        </section>

        <section class="glab-section" aria-labelledby="plombs-tetes-title">
            <h2 class="glab-title" id="plombs-tetes-title">Tête plate, ronde, pointue <span class="glab-title-accent">ou creuse ?</span></h2>
            <p class="glab-lede">Même jupe, même taille : seule la tête change, et elle change le tir. La jupe, c'est la partie creuse à l'arrière du plomb : sous la pression de l'air, elle s'évase et vient épouser le canon.</p>

            <div class="glab-families">
                @foreach ($heads as $head)
                    <article class="glab-family" data-glab-head="{{ $head['key'] }}">
                        <svg class="glab-pellet" viewBox="0 0 140 70" role="img" aria-label="{{ $head['name'] }}, de profil">
                            <path d="{{ $bodyTop }} {{ $head['nose'] }} {{ $bodyBottom }}"/>
                            <path class="glab-pellet-skirt" d="M8,17 L50,27 L50,43 L8,53"/>
                        </svg>
                        <p class="glab-family-kicker">{{ $head['kicker'] }}</p>
                        <h3>{{ $head['name'] }}</h3>
                        <p class="glab-family-read">{{ $head['read'] }}</p>
                        <p>{{ $head['body'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="glab-section" aria-labelledby="plombs-calibre-title">
            <h2 class="glab-title" id="plombs-calibre-title">Plomb 4,5 ou 5,5 mm : <span class="glab-title-accent">lequel choisir ?</span></h2>
            <p class="glab-lede glab-lede--thesis">Le calibre ne se choisit pas au rayon plombs : il est fixé par le canon. La question se pose à l'achat de la carabine.</p>

            <aside class="glab-joules-pair" aria-label="Même énergie, deux calibres">
                <p>
                    <span class="glab-joules-pair-kicker">19 J · 4,5 mm · 8,64 grains</span>
                    <strong>{{ $fr($pair[0]) }} FPS</strong>
                </p>
                <p>
                    <span class="glab-joules-pair-kicker">19 J · 5,5 mm · 14,66 grains</span>
                    <strong>{{ $fr($pair[1]) }} FPS</strong>
                </p>
                <p class="glab-joules-pair-note">
                    Le même Field Target Trophy dans les deux calibres : le 5,5 mm pèse près de 70 % de plus et part {{ $fr(round($pair[0]) - round($pair[1])) }} FPS plus lentement, loin de la zone transsonique.
                </p>
            </aside>

            <div class="glab-prose">
                <p>Le 4,5 mm est le seul calibre des épreuves de carabine à 10 mètres : la règle 7.4.6 de l'ISSF, que diffuse la FFTir, y autorise des projectiles de n'importe quelle forme, en plomb ou en matériau tendre similaire. C'est aussi le plus léger : à énergie égale, il part plus vite.</p>
                <p>Le 5,5 mm fait l'inverse. Son plomb plus lourd part moins vite, et il demande plus d'énergie : H&amp;N indique une énergie minimale de 12 ft.lbs en sortie de canon pour son Field Target Trophy en 5,5 mm, contre 5,5 ft.lbs en 4,5 mm (le pied-livre, ou ft.lbs, est l'unité d'énergie anglo-saxonne : 12 ft.lbs font environ 16 joules, 5,5 ft.lbs environ 7,5 joules). Le 5 mm et le 6,35 mm existent aussi, plus rares, et suivent la même logique : plus lourd, plus lent à énergie égale.</p>
                <p>Pour nettoyer le canon, la corde d'entretien se choisit au même calibre : <a href="{{ route('guides.entretien') }}">Entretenir son arme</a>.</p>
            </div>
        </section>

        <section class="glab-section" aria-labelledby="plombs-poids-title">
            <h2 class="glab-title" id="plombs-poids-title">Quel poids de plomb, <span class="glab-title-accent">en grains ou en grammes ?</span></h2>
            <p class="glab-lede">Les boîtes parlent en grains, parfois en grammes. Un grain vaut exactement 64,79891 milligrammes : 8,18 grains font {{ $fr(8.18 * $grain, 2) }} g.</p>

            <div class="glab-prose">
                <p>Plus la carabine est puissante, plus un plomb léger part vite. Or dès 900 FPS, la traînée d'un diabolo, le plomb à jupe de nos boîtes, grimpe vite, et la dérive avec elle ; plus un plomb approche de la vitesse du son, plus il devient instable. C'est pour ces carabines puissantes que quelques fabricants font des plombs plus lourds que la normale.</p>
                <p>900 FPS n'est pas un mur. Dans Hard Air Magazine, Bob Sterne dit préférer pour les JSB Exact une vitesse au milieu de la tranche des 900 à 1 000 FPS, meilleur compromis à ses yeux entre trajectoire tendue et dérive au vent. C'est donc un repère : le tableau donne le poids sous lequel on le franchit, votre canon dit le reste.</p>
            </div>

            <div class="glab-table-wrap">
                <table class="glab-table" data-glab-floor-table>
                    <caption>Vitesse au départ en FPS, à énergie supposée constante. Le poids plancher est le plus léger qui parte sous 900 FPS ; les cases foncées atteignent ou dépassent 900 FPS.</caption>
                    <thead>
                        <tr>
                            <th scope="col">Énergie</th>
                            <th scope="col">Poids plancher</th>
                            @foreach ($tins as $tin)
                                <th scope="col">{{ $tin['name'] }} {{ $tin['calibre'] }} · {{ $fr($tin['grains'], 2) }} gr</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($energies as $joules)
                            <tr>
                                <th scope="row">{{ $fr($joules, is_float($joules) ? 1 : 0) }} J</th>
                                <td><strong>{{ $fr($floor($joules) / $grain, 2) }} gr</strong> · {{ $fr($floor($joules), 2) }} g</td>
                                @foreach ($tins as $tin)
                                    @php($speed = $fps($joules, $tin['grains']))
                                    <td class="{{ $speed >= 900 ? 'is-transonic' : '' }}">{{ $fr($speed) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="glab-more-reading">La ligne à 24 joules est en catégorie C, soumise à déclaration : <a href="{{ route('guides.classification') }}">Classer son arme</a> détaille les régimes.</p>
        </section>

        <section class="glab-section" aria-labelledby="plombs-diametre-title">
            <h2 class="glab-title" id="plombs-diametre-title">4,49, 4,50 ou 4,52 : <span class="glab-title-accent">que dit le diamètre de tête ?</span></h2>
            <p class="glab-lede">Le « 4,5 mm » de la boîte est un calibre. Le chiffre à deux décimales est le diamètre de la tête, et il change d'un plomb à l'autre.</p>

            <dl class="glab-specs glab-place-cards glab-place-cards--figures">
                @foreach ($diameters as $diameter)
                    <div>
                        <dt>{{ $diameter['size'] }} <span class="glab-spec-kicker">{{ $diameter['tin'] }}</span></dt>
                        <dd>{{ $diameter['body'] }}</dd>
                    </div>
                @endforeach
            </dl>

            <div class="glab-prose">
                <p>Pourquoi plusieurs diamètres pour un même calibre ? Parce que la jupe s'évase pour épouser le canon et que la tête doit y entrer juste. H&amp;N le dit en une phrase : chaque carabine a un canon et une montée en pression légèrement différents. Les plombs de match se vendent ainsi de 4,48 à 4,52 mm.</p>
                <p>Le chiffre imprimé reste indicatif. Sur un lot de Baracuda 8 annoncé à 4,51 mm, Hard Air Magazine a mesuré 78 % des plombs à 4,53 mm. Quand un plomb groupe bien, notez son diamètre et son lot, pas seulement son nom.</p>
            </div>
        </section>

        <section class="glab-section" aria-labelledby="plombs-essai-title">
            <h2 class="glab-title" id="plombs-essai-title">Pourquoi essayer ses plombs <span class="glab-title-accent">dans son propre canon ?</span></h2>
            <p class="glab-lede glab-lede--thesis">Aucun essai publié ne prédit la précision d'un plomb dans votre carabine.</p>
            <p class="glab-lede">C'est Hard Air Magazine qui l'écrit dans ses essais de plombs : un plomb irrégulier sera imprécis, un plomb régulier a bien plus de chances de grouper, sans garantie. L'essai se fait donc chez soi, dans cet ordre.</p>

            <ol class="glab-rules">
                <li>
                    <h3>Écartez les plombs abîmés</h3>
                    <p>Une jupe déformée vient le plus souvent de l'emballage, des manipulations ou du transport, plus que de la fabrication, et elle suffit à envoyer un tir hors du groupement. Laissez les plombs dans leur boîte, pas en vrac dans une poche.</p>
                </li>
                <li>
                    <h3>Même jour, même appui, même distance</h3>
                    <p>Un essai ne compare que des plombs : la position, l'appui, la distance et la lumière restent fixes. Un plomb essayé la semaine suivante est un autre essai.</p>
                </li>
                <li>
                    <h3>Une cible neuve par plomb</h3>
                    <p>Une <a href="{{ route('categories.show', 'planches-cibles') }}">planche multi-cibles</a> donne une pastille neuve à chaque boîte, et le groupement se lit sans confondre deux plombs. Notez à côté le nom, le poids et le diamètre de tête.</p>
                </li>
                <li>
                    <h3>Gardez le meilleur groupement, puis essayez le diamètre voisin</h3>
                    <p>Le plomb qui groupe le mieux, celui dont les impacts se tiennent le plus serrés, est le vôtre, quelle que soit sa réputation. S'il existe en plusieurs diamètres de tête, passez à celui d'à côté : c'est le même plomb, ajusté autrement.</p>
                </li>
                <li>
                    <h3>Passez au chronographe si vous en avez un</h3>
                    <p>Le calcul de cette page suppose une énergie fixe ; le chronographe mesure la vitesse réelle avec ce plomb-là. <a href="{{ route('guides.joules') }}">Joules et FPS</a> explique comment lire la mesure.</p>
                </li>
            </ol>
        </section>

        <section class="glab-section" aria-labelledby="plombs-sans-title">
            <h2 class="glab-title" id="plombs-sans-title">Plomb <span class="glab-title-accent">ou sans plomb ?</span></h2>
            <p class="glab-lede">Plus léger, plus rapide, et pour l'instant ni imposé en compétition ni exigé par l'Europe.</p>

            <div class="glab-prose">
                <p>Un plomb sans plomb est fait de zinc, de fer, d'étain, de cuivre ou de leurs alliages, voire de plastique, et il est beaucoup plus léger qu'un plomb classique. À énergie égale, il part donc plus vite et atteint souvent la vitesse du son ; son coefficient balistique, en général plus faible, le fait aussi dériver davantage au vent. Sur une carabine puissante, passez son poids au calcul avant d'acheter la boîte. En compétition, rien ne l'impose, puisque la règle ISSF des 10 mètres autorise le plomb.</p>
                <p>L'Agence européenne des produits chimiques avait proposé de restreindre l'usage des projectiles contenant 1 % de plomb ou plus, plombs d'armes à air compris. Dans le projet de règlement transmis au Conseil de l'Union européenne le 10 juillet 2026, la Commission juge injustifié d'étendre la restriction à ces plombs, pour le tir sportif comme pour la chasse : les alternatives existent en faibles quantités, manquent de précision et coûtent jusqu'à quatre fois plus cher. Ce n'est encore qu'un projet : le Parlement européen et le Conseil ont jusqu'au 10 octobre 2026 pour s'y opposer.</p>
            </div>
        </section>

        <section class="glab-section" aria-labelledby="plombs-mains-title">
            <h2 class="glab-title" id="plombs-mains-title">Après la séance, <span class="glab-title-accent">lavez-vous les mains</span></h2>
            <p class="glab-lede glab-lede--thesis">Le plomb entre dans le corps par le nez, avec les poussières et les fumées, ou par la bouche, avec des mains sales.</p>

            <div class="glab-prose">
                <p>C'est ainsi que l'INRS, l'Institut national de recherche et de sécurité, résume l'exposition au plomb, et il range les stands de tir parmi les lieux concernés. Il en décrit aussi les effets : troubles de l'humeur et de la mémoire, atteinte des reins, anémie, effets sur la grossesse et sur la production de spermatozoïdes. Ses consignes visent les salariés, mais elles valent pour quiconque manipule des plombs : ne pas boire, manger ni fumer en tirant, se laver les mains et le visage avant de passer à table.</p>
            </div>

            <p class="glab-warning">
                <strong class="glab-warning-label">Trois gestes</strong>
                Les plombs restent dans leur boîte, jamais tenus entre les lèvres. Les mains se lavent avant de manger, de boire ou de fumer. Les boîtes se rangent hors de portée des enfants.
            </p>
        </section>

        <section class="glab-section" aria-labelledby="plombs-idees-title">
            <h2 class="glab-title" id="plombs-idees-title">Trois idées <span class="glab-title-accent">qui ouvrent les groupements</span></h2>
            <p class="glab-lede">On les lit sur les forums ; le carton ne les confirme pas.</p>

            <div class="glab-myths">
                @foreach ($myths as $myth)
                    <article class="glab-myth">
                        <p class="glab-myth-flag">On entend</p>
                        <p class="glab-myth-claim">{{ $myth[0] }}</p>
                        <p class="glab-myth-flag glab-myth-flag--truth">En réalité</p>
                        <p class="glab-myth-truth">{{ $myth[1] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="glab-faq" aria-labelledby="plombs-faq-title">
            <h2 class="glab-title" id="plombs-faq-title">Questions <span class="glab-title-accent">fréquentes</span></h2>
            @foreach ($faq as $qa)
                <details>
                    <summary>{{ $qa[0] }}</summary>
                    <div>
                        <p>{{ $qa[1] }}</p>
                    </div>
                </details>
            @endforeach

            <p class="glab-more-reading">Pour la séance elle-même, <a href="{{ route('guides.premiere-seance') }}">Votre première séance au stand</a> ; pour savoir où tirer, <a href="{{ route('guides.ou-tirer') }}">Où tirer légalement</a> ; et pour choisir le carton, <a href="{{ route('guides.cibles') }}">Bien choisir sa cible</a>.</p>
        </section>

        <section class="glab-section" aria-labelledby="plombs-sources-title">
            <h2 class="glab-title" id="plombs-sources-title">Les <span class="glab-title-accent">sources</span></h2>
            <p class="glab-lede">Les textes, les règles de tir, les fiches des fabricants et les essais publiés. Chaque chiffre de cette page vient de l'une d'elles, ou d'un calcul fait à partir d'elles.</p>

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
            <a href="{{ route('categories.show', 'plombs-et-billes-d-acier') }}" class="btn btn-primary">Voir les plombs</a>
            <a href="{{ route('categories.show', 'planches-cibles') }}" class="btn btn-secondary">Voir les planches cibles</a>
            <a href="{{ route('guides.index') }}" class="btn btn-secondary">Tous les guides</a>
        </p>
    </div>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/guides.js') }}" defer></script>
@endpush
