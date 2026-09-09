@extends('layouts.app')

@php
    /*
     * The families, written once. The selector below reads these same rows,
     * and a visitor without JavaScript gets every row open with its terrains
     * and its seasons spelled out, which is the whole guide minus the sorting.
     *
     * Terrains and seasons carry a turn of phrase as well as a label: the
     * verdict is a sentence, and "au été" is what comes of gluing a
     * preposition to a list in JavaScript. French grammar stays in the
     * French file.
     */
    $terrains = [
        'sous-bois' => ['Sous-bois dense', 'en sous-bois dense'],
        'foret' => ['Forêt claire', 'en forêt claire'],
        'champs' => ['Champs et friches', 'dans les champs et les friches'],
        'rocaille' => ['Rocaille et garrigue', 'en rocaille et en garrigue'],
        'urbain' => ['Urbain et industriel', 'en milieu urbain'],
        'neige' => ['Neige', 'sur la neige'],
    ];

    $seasons = [
        'printemps' => ['Printemps', 'au printemps'],
        'ete' => ['Été', 'en été'],
        'automne' => ['Automne', 'en automne'],
        'hiver' => ['Hiver', 'en hiver'],
    ];

    $families = [
        [
            'key' => 'ce',
            'name' => 'CE',
            'aka' => 'Centre-Europe',
            'read' => 'Le motif des forces françaises depuis les années 1990, dessiné pour la forêt tempérée.',
            'body' => 'Quatre couleurs franches, des taches larges, aucune subtilité : il découpe une silhouette dans un sous-bois européen mieux que la plupart des motifs récents. Hors de la forêt, il devient une tache sombre.',
            'terrains' => ['sous-bois', 'foret'],
            'seasons' => ['printemps', 'ete'],
        ],
        [
            'key' => 'woodland',
            'name' => 'Woodland',
            'aka' => 'M81 et dérivés',
            'read' => 'Le cousin américain du CE, plus vert, plus contrasté encore.',
            'body' => 'Même logique de grandes taches, une dominante verte plus soutenue. Excellent dans un feuillage plein, trop vert dès que la végétation jaunit.',
            'terrains' => ['sous-bois', 'foret'],
            'seasons' => ['printemps', 'ete'],
        ],
        [
            'key' => 'multicam',
            'name' => 'Multicam',
            'aka' => 'et ses copies',
            'read' => 'Le plus polyvalent : des transitions douces au lieu de taches franches.',
            'body' => 'Beiges, verts tendres et bruns fondus les uns dans les autres, sans contour net. Il ne gagne nulle part et ne perd nulle part, ce qui en fait le bon choix quand on ignore où l\'on jouera. En France il rend surtout en fin d\'été et en automne, quand l\'herbe a jauni.',
            'terrains' => ['foret', 'champs', 'rocaille'],
            'seasons' => ['ete', 'automne'],
        ],
        [
            'key' => 'atacs',
            'name' => 'A-TACS',
            'aka' => 'AU et FG',
            'read' => 'Un motif flou, sans arête, pensé pour la distance moyenne.',
            'body' => 'Pas de taches mais des amas de petits pixels fondus, qui se lisent comme une texture plutôt que comme un dessin. Il tient mieux que les autres quand la lumière change au fil de la journée.',
            'terrains' => ['foret', 'champs', 'rocaille'],
            'seasons' => ['printemps', 'ete', 'automne'],
        ],
        [
            'key' => 'digital',
            'name' => 'Numérique',
            'aka' => 'EMR, pixel',
            'read' => 'Des pixels carrés qui se fondent à quinze mètres et se voient à trois.',
            'body' => 'Le pixel n\'est pas décoratif : il brouille le contour à distance moyenne, là où l\'œil cherche une ligne. De près il se lit comme un damier, ce qui le dessert dans un sous-bois où l\'on se poste à dix mètres.',
            'terrains' => ['foret', 'champs', 'urbain'],
            'seasons' => ['printemps', 'ete', 'automne'],
        ],
        [
            'key' => 'desert',
            'name' => 'Désert',
            'aka' => 'aride, sable',
            'read' => 'Pour la roche sèche et la garrigue, pas pour la forêt.',
            'body' => 'Sables, bruns clairs et gris. Dans le Midi, sur un causse ou une carrière, il fait le travail que le vert ne fait pas. Sous un couvert vert, il brille comme une lampe.',
            'terrains' => ['rocaille', 'champs'],
            'seasons' => ['ete', 'automne'],
        ],
        [
            'key' => 'python',
            'name' => 'Mimétique animal',
            'aka' => 'python, écailles',
            'read' => 'Une texture serrée plutôt qu\'un camouflage de terrain.',
            'body' => 'Motif de peau, régulier et fin. Il casse la matière d\'un vêtement plus qu\'il ne l\'accorde à un décor : à porter pour ce qu\'il est, un choix d\'allure, ou en complément d\'une tenue déjà accordée au terrain.',
            'terrains' => ['urbain'],
            'seasons' => ['printemps', 'ete', 'automne', 'hiver'],
        ],
    ];

    // What gives a person away, in the order it gives them away.
    $tells = [
        ['Le mouvement', 'L\'œil détecte un déplacement avant une couleur. Un motif parfait qui bouge vite est vu ; une tenue quelconque immobile ne l\'est pas. C\'est le premier des cinq et il n\'a rien à voir avec ce que vous achetez.'],
        ['La brillance', 'Une boucle, un verre d\'optique, une peau grasse, un canon nu : un reflet porte plus loin qu\'une couleur. Mat partout, y compris sur ce qui ne se voit pas de face.'],
        ['La silhouette', 'Deux épaules et une tête au-dessus se lisent comme un humain quelle que soit leur couleur. La forme se casse avec du relief, de la végétation, une position basse.'],
        ['L\'ombre', 'Se poster dans une ombre vous cache ; se poster devant le ciel ou en plein soleil vous dessine. La lumière décide avant le motif.'],
        ['La peau', 'Le visage et les mains restent deux taches claires au milieu d\'une tenue accordée. Cache-cou, cagoule et gants règlent en trente secondes ce qu\'aucun motif ne rattrape.'],
    ];

    $faq = [
        ['Quel camouflage pour débuter en France ?', 'Deux tenues couvrent presque tout : une forestière, CE ou Woodland, pour le sous-bois du printemps et de l\'été, et une polyvalente, Multicam ou un uni vert olive, pour le reste. Acheter les sept familles avant de connaître son terrain est la dépense la moins utile du sport.'],
        ['Un uni vert vaut-il un motif ?', 'Souvent oui. Un vert olive ou un coyote mat ne découpe pas la silhouette comme un motif, mais il ne jure avec rien et il vieillit bien. Beaucoup de pratiquants expérimentés jouent en uni et cassent la forme avec le relief plutôt qu\'avec le tissu.'],
        ['Le motif compte-t-il vraiment à courte distance ?', 'Moins qu\'on ne le croit. Sous dix mètres, l\'œil lit une forme et un mouvement, pas une texture. Le motif travaille surtout entre vingt et cent mètres, ce qui est précisément la distance à laquelle on est repéré sans le savoir.'],
        ['Faut-il assortir le sac, les poches et la casquette ?', 'Non, et vouloir tout assortir coûte cher pour rien. Ce qui compte est qu\'aucune pièce ne jure : un sac noir sur une tenue verte est plus visible qu\'un sac vert d\'un autre motif.'],
        ['Et pour la neige ?', 'Aucune des familles ci-dessus ne vaut sur la neige, et un motif hivernal ne sert que quelques jours par an en France. Un sur-vêtement blanc jetable par-dessus la tenue habituelle fait le travail pour le prix d\'un repas.'],
    ];
@endphp

@section('title', 'Choisir son camouflage : les cinq signes, les sept familles — Armo Outdoor')
@section('meta_description', 'Le mouvement, la brillance, la silhouette, l\'ombre et la peau vous trahissent avant le motif. Les sept familles de camouflage, et laquelle tient sur quel terrain français, saison par saison.')
@section('og_type', 'article')
@section('canonical', route('guides.camouflage'))

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/categories.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('css/guides/guides.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('css/guides/camouflage.css') }}">
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => 'Choisir son camouflage : les cinq signes, les sept familles',
            'description' => 'Le mouvement, la brillance, la silhouette, l\'ombre et la peau vous trahissent avant le motif. Les sept familles de camouflage, et laquelle tient sur quel terrain français, saison par saison.',
            'mainEntityOfPage' => route('guides.camouflage'),
            'inLanguage' => 'fr-FR',
            'datePublished' => '2026-09-09',
            'dateModified' => '2026-09-09',
            'author' => \App\Support\OrganizationSchema::reference(),
            'publisher' => \App\Support\OrganizationSchema::reference(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => collect($faq)->map(fn (array $qa): array => [
                '@type' => 'Question',
                'name' => $qa[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa[1]],
            ])->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => __('store.breadcrumb_home'), 'item' => localized_route('home')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Guides', 'item' => route('guides.index')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => 'Choisir son camouflage', 'item' => route('guides.camouflage')],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('content')
    <div class="container glab glab--cam">
        @include('guides.partials.hero', [
            'crumb' => 'Choisir son camouflage',
            'kicker' => 'Camouflage',
            'title' => 'Choisir son camouflage',
            'lede' => 'Le motif est la dernière des cinq choses qui vous trahissent, et la seule qui s\'achète. Voici les quatre autres, les sept familles, et laquelle tient sur votre terrain.',
            'tags' => ['5 signes', '7 familles', '6 terrains'],
        ])

        <nav class="glab-plan" aria-label="Plan du guide">
            <p class="glab-plan-kicker">Plan</p>
            <div class="glab-plan-links">
                <a href="#cam-tells-title">Ce qui vous trahit</a>
                <a href="#cam-picker-title">Le sélecteur</a>
                <a href="#cam-families-title">Les familles</a>
                <a href="#cam-kit-title">Ce qui reste à couvrir</a>
                <a href="#cam-faq-title">Questions</a>
            </div>
        </nav>

        <section class="glab-section" aria-labelledby="cam-tells-title">
            <h2 class="glab-title" id="cam-tells-title">Cinq choses vous trahissent. <span class="glab-title-accent">Le motif est la cinquième</span></h2>
            <p class="glab-lede glab-lede--thesis">
                On achète un motif parce que c'est la seule des cinq qui se vend. Les quatre
                autres sont gratuites et décident davantage.
            </p>

            <ol class="cam-tells">
                @foreach ($tells as $index => $tell)
                    <li class="cam-tell {{ $index === 0 ? 'is-first' : '' }}">
                        <span class="cam-tell-rank" aria-hidden="true">{{ $index + 1 }}</span>
                        <div class="cam-tell-copy">
                            <h3>{{ $tell[0] }}</h3>
                            <p>{{ $tell[1] }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>

            <p class="glab-prose">
                Rien de tout cela ne dit qu'un motif ne sert à rien. Il travaille entre vingt et
                cent mètres, là où l'œil cherche une ligne à suivre, et c'est exactement la
                distance à laquelle on se fait repérer sans le savoir. Il vient simplement après
                les quatre autres, pas avant.
            </p>
        </section>

        {{-- The selector. Revealed by the script rather than shipped open, so a
             page without JavaScript promises no control it cannot honour: the
             families below carry their terrains and their seasons in words. --}}
        <section class="glab-panel cam-picker" data-cam-picker hidden aria-labelledby="cam-picker-title">
            <h2 class="glab-title" id="cam-picker-title">Votre terrain, <span class="glab-title-accent">votre saison</span></h2>
            <p class="glab-prose">
                Deux réponses, et les familles se rangent : ce qui tient, ce qui passe, ce qui
                jure. Le classement vaut pour un terrain français et se lit comme un avis, pas
                comme une règle.
            </p>

            {{-- Pills rather than dropdowns: ten choices in all, few enough to
                 show at once, and a control you can see is a control you use.
                 Buttons in a group rather than radios, since nothing here is
                 submitted anywhere. --}}
            <div class="cam-picker-fields">
                <div class="cam-picker-field" role="group" aria-labelledby="cam-terrain-legend">
                    <span class="cam-picker-legend" id="cam-terrain-legend">Terrain</span>
                    <div class="cam-options" data-cam-terrain>
                        @foreach ($terrains as $key => $terrain)
                            <button
                                type="button"
                                class="cam-option"
                                value="{{ $key }}"
                                data-phrase="{{ $terrain[1] }}"
                                aria-pressed="{{ $key === 'sous-bois' ? 'true' : 'false' }}"
                            >{{ $terrain[0] }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="cam-picker-field" role="group" aria-labelledby="cam-season-legend">
                    <span class="cam-picker-legend" id="cam-season-legend">Saison</span>
                    <div class="cam-options" data-cam-season>
                        @foreach ($seasons as $key => $season)
                            <button
                                type="button"
                                class="cam-option"
                                value="{{ $key }}"
                                data-phrase="{{ $season[1] }}"
                                aria-pressed="{{ $key === 'ete' ? 'true' : 'false' }}"
                            >{{ $season[0] }}</button>
                        @endforeach
                    </div>
                </div>
            </div>

            <p class="cam-picker-verdict" data-cam-verdict role="status"></p>

            <ol class="cam-picker-results" data-cam-results></ol>
        </section>

        <section class="glab-section" aria-labelledby="cam-families-title">
            <h2 class="glab-title" id="cam-families-title">Sept familles, <span class="glab-title-accent">et ce que chacune vaut</span></h2>
            <p class="glab-prose">
                Les motifs se comptent par centaines et se rangent en quelques familles. Chaque
                vignette ci-dessous est dessinée à la palette de sa famille : ce n'est pas une
                photographie de tissu, c'est ce que l'œil retient d'un motif à vingt mètres,
                c'est-à-dire ses couleurs et la taille de ses taches.
            </p>

            <div class="cam-families" data-cam-families>
                @foreach ($families as $family)
                    <article
                        class="cam-family"
                        data-cam-family
                        data-terrains="{{ implode(' ', $family['terrains']) }}"
                        data-seasons="{{ implode(' ', $family['seasons']) }}"
                        data-name="{{ $family['name'] }}"
                    >
                        <span class="cam-swatch cam-swatch--{{ $family['key'] }}" aria-hidden="true"></span>
                        <div class="cam-family-copy">
                            <h3>{{ $family['name'] }} <span class="cam-family-aka">{{ $family['aka'] }}</span></h3>
                            <p class="cam-family-read">{{ $family['read'] }}</p>
                            <p>{{ $family['body'] }}</p>
                            <dl class="cam-family-facts">
                                <div>
                                    <dt>Terrain</dt>
                                    <dd>{{ collect($family['terrains'])->map(fn (string $t): string => $terrains[$t][0])->join(', ') }}</dd>
                                </div>
                                <div>
                                    <dt>Saison</dt>
                                    <dd>{{ collect($family['seasons'])->map(fn (string $s): string => $seasons[$s][0])->join(', ') }}</dd>
                                </div>
                            </dl>
                        </div>
                    </article>
                @endforeach
            </div>

            <aside class="glab-warning">
                <p class="glab-warning-label">La neige</p>
                <p>
                    Aucune de ces sept familles ne vaut sur la neige, et un motif hivernal ne sert
                    que quelques jours par an sous nos latitudes. Un sur-vêtement blanc jetable
                    par-dessus la tenue habituelle règle la question pour le prix d'un repas.
                </p>
            </aside>
        </section>

        <section class="glab-section" aria-labelledby="cam-kit-title">
            <h2 class="glab-title" id="cam-kit-title">Ce qui reste <span class="glab-title-accent">à couvrir</span></h2>
            <p class="glab-prose">
                Une tenue accordée au terrain laisse deux taches claires : le visage et les mains.
                Ce sont les deux dernières choses qu'on couvre et les deux premières qu'on voit.
            </p>

            <div class="cam-kit">
                <a class="cam-kit-item" href="{{ localized_route('categories.show', ['category' => 'cache-cou']) }}">
                    <span class="cam-kit-label">Le cou et le bas du visage</span>
                    <span class="cam-kit-name">Cache-cou</span>
                    <span class="cam-kit-note">Se monte et se descend d'une main, se porte toute l'année.</span>
                </a>
                <a class="cam-kit-item" href="{{ localized_route('categories.show', ['category' => 'cagoules']) }}">
                    <span class="cam-kit-label">Le visage entier</span>
                    <span class="cam-kit-name">Cagoules</span>
                    <span class="cam-kit-note">Couvre ce que la peinture faciale couvrait, sans le démaquillage.</span>
                </a>
                <a class="cam-kit-item" href="{{ localized_route('categories.show', ['category' => 'gants']) }}">
                    <span class="cam-kit-label">Les mains</span>
                    <span class="cam-kit-name">Gants</span>
                    <span class="cam-kit-note">Deux taches mobiles, donc les plus repérables de la tenue.</span>
                </a>
                <a class="cam-kit-item" href="{{ localized_route('categories.show', ['category' => 'casquettes']) }}">
                    <span class="cam-kit-label">La tête et l'ombre du regard</span>
                    <span class="cam-kit-name">Casquettes</span>
                    <span class="cam-kit-note">La visière casse la ligne du front et éteint le reflet des yeux.</span>
                </a>
            </div>

            <p class="glab-prose">
                Et sur le reste du matériel, une règle qui ne coûte rien : rien de brillant. Une
                boucle nue, un ruban adhésif clair, un verre d'optique au soleil portent plus loin
                qu'une couleur mal choisie.
            </p>
        </section>

        <section class="glab-faq" aria-labelledby="cam-faq-title">
            <h2 class="glab-title" id="cam-faq-title">Questions <span class="glab-title-accent">fréquentes</span></h2>
            @foreach ($faq as $qa)
                <details class="glab-faq-item">
                    <summary>{{ $qa[0] }}</summary>
                    <p>{{ $qa[1] }}</p>
                </details>
            @endforeach
        </section>

        <nav class="glab-more-reading" aria-label="À lire ensuite">
            <p class="glab-plan-kicker">À lire ensuite</p>
            <div class="glab-plan-links">
                <a href="{{ localized_route('categories.show', ['category' => 'vetements']) }}">Le rayon vêtements</a>
                <a href="{{ route('guides.ou-tirer') }}">Où tirer légalement</a>
                <a href="{{ route('guides.glossaire') }}">Le glossaire</a>
                <a href="{{ route('guides.index') }}">Tous les guides</a>
            </div>
        </nav>
    </div>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/guides/camouflage.js') }}" defer></script>
@endpush
