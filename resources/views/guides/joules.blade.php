@extends('layouts.app')

@php
    // The reference table, written once: the JS calculator reads the same
    // weights, and the rows below are what a visitor without JavaScript
    // gets instead of it.
    $weights = ['0,12', '0,20', '0,25', '0,28', '0,30', '0,36', '0,40'];
    $speeds = [280, 300, 330, 350, 400, 450, 500];

    // E = ½ m v², with the foot per second turned into metres.
    $energy = fn (string $weight, int $fps): float => 0.5
        * ((float) str_replace(',', '.', $weight) / 1000)
        * (($fps * 0.3048) ** 2);

    // How close a cell runs to the two-joule line, so the table shades
    // towards it and says plainly which pairs have crossed.
    $band = function (float $joules): string {
        $ratio = $joules / 2;

        return match (true) {
            $ratio >= 1 => 'is-over',
            $ratio >= 0.75 => 'is-near',
            $ratio >= 0.5 => 'is-mid',
            default => '',
        };
    };

    $faq = [
        ['350 FPS, ça fait combien de joules ?', 'Cela dépend de la bille. Avec une 0,20 g, 350 FPS valent 1,14 joule. Avec une 0,25 g, la même vitesse vaut 1,43 joule, et avec une 0,28 g, 1,60 joule. Une vitesse seule ne dit rien : c\'est le couple bille et vitesse qui fait l\'énergie.'],
        ['Pourquoi les terrains parlent en joules et les vendeurs en FPS ?', 'Parce qu\'un chronographe mesure une vitesse, pas une énergie. Le vendeur annonce ce que lit l\'appareil, avec la bille d\'essai. Le terrain, lui, doit fixer une limite qui vaille quelle que soit la bille chargée, et seule l\'énergie tient ce rôle.'],
        ['Ma réplique chrone 340 FPS un jour et 365 le lendemain, est-ce normal ?', 'Oui. Une batterie fraîche pousse plus qu\'une batterie en fin de charge, un gaz froid pousse moins qu\'un gaz à vingt degrés, un hop-up trop serré freine la bille, et deux chronographes ne s\'accordent pas au FPS près. Un écart de cinq pour cent au fil de la journée est ordinaire.'],
        ['À partir de quelle énergie une réplique devient-elle une arme ?', 'À 2 joules. En dessous, l\'objet n\'est juridiquement pas une arme. De 2 à 20 joules, c\'est la catégorie D, en vente libre pour un majeur. À 20 joules et au-delà, c\'est la catégorie C, soumise à déclaration.'],
        ['Une bille plus lourde donne-t-elle plus d\'énergie ?', 'À vitesse égale, oui : l\'énergie suit la masse. Mais sur une même réplique, la bille plus lourde sort moins vite, et l\'énergie reste à peu près la même. C\'est la portée utile et la stabilité qui changent, pas le chiffre au chronographe.'],
    ];
@endphp

@section('title', 'Joules et FPS : la conversion, la calculette et les seuils — Armo Outdoor')
@section('meta_description', 'Convertir des FPS en joules et l\'inverse : la formule, une calculette bille par bille, le tableau de 280 à 500 FPS, les seuils de 2 et 20 joules et les limites de terrain.')
@section('og_type', 'article')
@section('canonical', route('guides.joules'))

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/categories.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('css/guides/guides.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('css/guides/joules.css') }}">
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'Article',
            'headline' => 'Joules et FPS : la conversion, la calculette et les seuils',
            'description' => 'Convertir des FPS en joules et l\'inverse : la formule, une calculette bille par bille, le tableau de 280 à 500 FPS, les seuils de 2 et 20 joules et les limites de terrain.',
            'mainEntityOfPage' => route('guides.joules'),
            'inLanguage' => 'fr-FR',
            'datePublished' => '2026-09-07',
            'dateModified' => '2026-09-07',
            'author' => \App\Support\OrganizationSchema::reference(),
            'publisher' => \App\Support\OrganizationSchema::reference(),
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
                ['@@type' => 'ListItem', 'position' => 3, 'name' => 'Joules et FPS', 'item' => route('guides.joules')],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('content')
    <div class="container glab glab--joules">
        @include('guides.partials.hero', [
            'crumb' => 'Joules et FPS',
            'kicker' => 'Énergie',
            'title' => 'Joules et FPS',
            'lede' => 'Le magasin annonce des FPS, la loi compte en joules, le terrain aussi. Voici la formule qui relie les deux, une calculette, et les raisons pour lesquelles la même réplique ne chrone pas deux fois pareil.',
            'tags' => ['½ m v²', '2 J', '20 J'],
        ])

        <nav class="glab-plan" aria-label="Plan du guide">
            <p class="glab-plan-kicker">Plan</p>
            <div class="glab-plan-links">
                <a href="#glab-why-title">Deux unités</a>
                <a href="#glab-calc-title">Calculette</a>
                <a href="#glab-formula-title">Formule</a>
                <a href="#glab-table-title">Tableau</a>
                <a href="#glab-thresholds-title">Seuils</a>
                <a href="#glab-variance-title">Le chrono</a>
                <a href="#glab-faq-title">Questions</a>
            </div>
        </nav>

        <section class="glab-section" aria-labelledby="glab-why-title">
            <h2 class="glab-title" id="glab-why-title">Deux unités, <span class="glab-title-accent">une seule réalité</span></h2>
            <p class="glab-lede glab-lede--thesis">
                Un chronographe mesure une vitesse. La loi et les terrains, eux, comptent en énergie.
            </p>

            <div class="glab-units">
                <article class="glab-unit">
                    <p class="glab-unit-kicker">Ce que lit le chrono</p>
                    <h3>FPS</h3>
                    <p class="glab-unit-read">Une vitesse</p>
                    <p>
                        « Feet per second » : le nombre de pieds parcourus en une seconde par la
                        bille au sortir du canon. C'est ce que les fiches produit annoncent.
                    </p>
                </article>
                <article class="glab-unit is-key">
                    <p class="glab-unit-kicker">Ce que compte la loi</p>
                    <h3>Joule</h3>
                    <p class="glab-unit-read">Une énergie</p>
                    <p>
                        Ce que la bille emporte, et ce qu'elle est capable de faire à l'arrivée.
                        La seule grandeur qui tienne quand la bille change.
                    </p>
                </article>
            </div>

            <aside class="glab-joules-pair" aria-label="Même énergie, deux vitesses">
                <p>
                    <span class="glab-joules-pair-kicker">Même réplique · 0,20 g · 350 FPS</span>
                    <strong>1,14 J</strong>
                </p>
                <p>
                    <span class="glab-joules-pair-kicker">Même réplique · 0,28 g · 296 FPS</span>
                    <strong>1,14 J</strong>
                </p>
                <p class="glab-joules-pair-note">
                    La vitesse a changé de cinquante pieds. L'énergie, non. Comparer deux répliques
                    sur leurs FPS sans la bille, c'est comparer deux prix sans la monnaie.
                </p>
            </aside>
        </section>

        {{-- The calculator. Hidden until the page knows it has JavaScript:
             the reference table below is the answer for everyone else, so
             nothing here promises a control that cannot work. --}}
        <section class="glab-panel" data-glab-joules hidden aria-labelledby="glab-calc-title">
            <h2 class="glab-title" id="glab-calc-title">La <span class="glab-title-accent">calculette</span></h2>
            <p class="glab-lede">
                Le poids de la bille et la vitesse au chronographe. Le reste se lit tout seul.
            </p>

            <div class="glab-calc-grid">
                <div class="glab-calc-fields">
                    <div class="glab-calc-field">
                        <label for="glab-calc-weight">Poids de la bille</label>
                        <select id="glab-calc-weight" data-glab-weight>
                            @foreach ($weights as $weight)
                                <option value="{{ str_replace(',', '.', $weight) }}" @selected($weight === '0,20')>{{ $weight }} g</option>
                            @endforeach
                            <option value="custom">Autre poids</option>
                        </select>
                    </div>

                    <div class="glab-calc-field" data-glab-custom hidden>
                        <label for="glab-calc-custom">Poids exact, en grammes</label>
                        <input type="number" id="glab-calc-custom" step="0.01" min="0.01" max="10" value="0.20" data-glab-custom-input>
                    </div>

                    <div class="glab-calc-field">
                        <label for="glab-calc-speed">Vitesse mesurée</label>
                        <div class="glab-calc-speed">
                            <input type="number" id="glab-calc-speed" step="1" min="1" max="3000" value="350" data-glab-speed>
                            <select aria-label="Unité de vitesse" data-glab-unit>
                                <option value="fps">FPS</option>
                                <option value="ms">m/s</option>
                            </select>
                        </div>
                    </div>
                </div>

                <output class="glab-calc-result" data-glab-result for="glab-calc-weight glab-calc-speed">
                    <span class="glab-calc-energy"><strong data-glab-joules-value>1,14</strong> joule</span>
                    <span class="glab-calc-equiv" data-glab-equiv>350 FPS · 107 m/s</span>
                    <span class="glab-calc-band" data-glab-band>Sous 2 J : juridiquement pas une arme</span>
                </output>
            </div>

            @include('guides.partials.energy-scale', ['at' => 1.14, 'caps' => [1.14, 1.49, 1.88]])

            {{-- The question people actually arrive with: the field caps in
                 joules, the chronograph reads FPS, and the bill is theirs. --}}
            <div class="glab-calc-limits">
                <h3>Avec cette bille, les limites de terrain courantes tombent à</h3>
                <ul data-glab-limits>
                    <li><span>1,14 J</span> <strong>350 FPS</strong></li>
                    <li><span>1,49 J</span> <strong>400 FPS</strong></li>
                    <li><span>1,88 J</span> <strong>450 FPS</strong></li>
                    <li><span>2,00 J</span> <strong>464 FPS</strong></li>
                </ul>
                <p class="glab-calc-note">
                    Ces trois premières valeurs sont des usages de terrain, pas la loi. Chaque
                    terrain publie les siennes, souvent avec une distance minimale d'engagement
                    pour les répliques les plus énergiques. La quatrième, 2 joules, est la seule
                    qui soit dans le code.
                </p>
            </div>
        </section>

        <section class="glab-section" aria-labelledby="glab-formula-title">
            <h2 class="glab-title" id="glab-formula-title">La <span class="glab-title-accent">formule</span></h2>
            <p class="glab-lede">
                Une multiplication, une division par deux, et une conversion de pieds en mètres.
            </p>

            <figure class="glab-formula">
                <p class="glab-formula-line"><em>E</em> = ½ · <em>m</em> · <em>v</em><sup>2</sup></p>
                <figcaption>
                    <span><em>E</em> en joules</span>
                    <span><em>m</em> en kilogrammes</span>
                    <span><em>v</em> en mètres par seconde</span>
                </figcaption>
            </figure>

            <ol class="glab-worked">
                <li>
                    <span class="glab-worked-kicker">Masse</span>
                    <span class="glab-worked-from">0,20 g</span>
                    <span class="glab-worked-to">0,0002 kg</span>
                </li>
                <li>
                    <span class="glab-worked-kicker">Vitesse</span>
                    <span class="glab-worked-from">350 FPS</span>
                    <span class="glab-worked-to">106,7 m/s</span>
                </li>
                <li class="is-result">
                    <span class="glab-worked-kicker">Énergie</span>
                    <span class="glab-worked-from">½ × 0,0002 × 106,7²</span>
                    <strong class="glab-worked-to">1,14 J</strong>
                </li>
            </ol>
            <p class="glab-worked-note">Un pied vaut 0,3048 mètre.</p>

            <div class="glab-square">
                <article>
                    <p class="glab-square-kicker">Doubler la masse</p>
                    <p class="glab-square-result">× 2 l'énergie</p>
                </article>
                <article class="is-key">
                    <p class="glab-square-kicker">Doubler la vitesse</p>
                    <p class="glab-square-result">× 4 l'énergie</p>
                </article>
            </div>
            <p class="glab-square-note">
                Le carré est la partie qui compte. Cinquante FPS de plus se voient tout de suite
                sur le résultat : un terrain qui tolère un dépassement de vitesse ne tolère pas
                le même dépassement d'énergie.
            </p>
        </section>

        <section class="glab-panel" aria-labelledby="glab-table-title">
            <h2 class="glab-title" id="glab-table-title">Le <span class="glab-title-accent">tableau</span></h2>
            <p class="glab-lede">
                Quarante-neuf réponses, sans rien calculer. Plus la case fonce, plus elle approche
                des 2 joules ; les cases pleines les ont franchis.
            </p>

            <ul class="glab-table-key">
                <li><i class="is-mid"></i> dès 1 J</li>
                <li><i class="is-near"></i> dès 1,5 J</li>
                <li><i class="is-over"></i> 2 J et plus</li>
            </ul>

            <div class="glab-table-wrap">
                <table class="glab-table">
                    <caption>Énergie en joules, par poids de bille et vitesse au chronographe.</caption>
                    <thead>
                        <tr>
                            <th scope="col">Bille</th>
                            @foreach ($speeds as $fps)
                                <th scope="col">{{ $fps }} FPS</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($weights as $weight)
                            <tr>
                                <th scope="row">{{ $weight }} g</th>
                                @foreach ($speeds as $fps)
                                    @php($cell = $energy($weight, $fps))
                                    <td class="{{ $band($cell) }}">{{ number_format($cell, 2, ',', ' ') }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="glab-panel" aria-labelledby="glab-thresholds-title">
            <h2 class="glab-title" id="glab-thresholds-title">Les <span class="glab-title-accent">seuils</span></h2>
            <p class="glab-lede">
                2 joules, 20 joules. Tout le droit français des armes à air tient sur ces deux nombres.
            </p>

            @include('guides.partials.energy-scale')

            <dl class="glab-specs">
                <div>
                    <dt>Sous 2 J <em>hors catégorie</em></dt>
                    <dd>L'objet n'est juridiquement pas une arme. Les répliques d'airsoft du commerce français sont conçues pour rester là. La vente aux mineurs reste interdite dès 0,08 J.</dd>
                </div>
                <div>
                    <dt>2 à 20 J <em>catégorie D</em></dt>
                    <dd>Carabines et pistolets à plombs, billes acier. Achat libre pour un majeur, mais port et transport exigent un motif légitime.</dd>
                </div>
                <div>
                    <dt>Dès 20 J <em>catégorie C</em></dt>
                    <dd>Déclaration, titre de chasse ou de tir, compte SIA. Le texte dit « supérieure ou égale », donc 20 J exactement bascule déjà.</dd>
                </div>
            </dl>

            <p class="glab-more-reading">
                Le détail des quatre régimes, chargeur compris, est dans notre guide
                <a href="{{ route('guides.classification') }}">Classer son arme</a>.
            </p>
        </section>

        <section class="glab-section" aria-labelledby="glab-variance-title">
            <h2 class="glab-title" id="glab-variance-title">Pourquoi la même réplique <span class="glab-title-accent">ne chrone pas deux fois pareil</span></h2>
            <p class="glab-lede">
                Cinq pour cent d'écart au fil d'une journée n'a rien d'anormal. Voici d'où ils viennent.
            </p>

            <ol class="glab-rules">
                <li>
                    <h3>La bille</h3>
                    <p>
                        C'est la première variable, et la seule qui change vraiment le chiffre annoncé.
                        Un contrôle fait en 0,20 g et une partie jouée en 0,28 g ne mesurent pas la même
                        chose. Le diamètre compte aussi : une bille mal calibrée frotte, et ce qu'elle
                        perd en friction ne se retrouve pas au chronographe.
                        <a href="{{ route('blog.show', 'billes-airsoft-poids-bio-et-qualite-ce-qui-justifie-lecart-de-prix') }}">Notre article sur les billes</a>
                        revient sur ce que le poids et la qualité changent.
                    </p>
                </li>
                <li>
                    <h3>La température</h3>
                    <p>
                        Une réplique à gaz est thermodynamique : le gaz froid se détend moins, et une
                        matinée d'hiver coûte facilement un cinquième de la puissance d'un après-midi
                        d'été. Une réplique électrique s'en moque presque, mais sa batterie non : pleine
                        charge et fin de charge ne poussent pas le piston de la même façon.
                    </p>
                </li>
                <li>
                    <h3>Le hop-up</h3>
                    <p>
                        Un hop-up trop serré freine la bille avant qu'elle ne sorte : le chronographe
                        lit moins, alors que rien n'a été démonté. Un contrôle sérieux se fait hop-up
                        relâché, sans quoi on mesure le réglage plutôt que la réplique.
                    </p>
                </li>
                <li>
                    <h3>Le chronographe lui-même</h3>
                    <p>
                        Deux appareils ne s'accordent pas au FPS près, et la lumière ambiante suffit à
                        les faire diverger. Une mesure isolée ne vaut rien : on tire cinq à dix billes
                        et on lit la moyenne, en écartant la première, souvent basse.
                    </p>
                </li>
            </ol>
        </section>

        <section class="glab-panel" aria-labelledby="glab-faq-title">
            <h2 class="glab-title" id="glab-faq-title">Questions <span class="glab-title-accent">fréquentes</span></h2>

            <div class="glab-faq">
                @foreach ($faq as $qa)
                    <details>
                        <summary>{{ $qa[0] }}</summary>
                        <div>
                            <p>{{ $qa[1] }}</p>
                        </div>
                    </details>
                @endforeach
            </div>

            <p class="glab-more-reading">
                Et pour la séance qui suit, de quoi occuper la ligne :
                <a href="{{ route('guides.cibles') }}">Bien choisir sa cible</a>.
            </p>

            <p class="glab-ctas">
                <a href="{{ route('categories.show', 'repliques-airsoft') }}" class="btn btn-primary">Voir le rayon répliques</a>
                <a href="{{ route('categories.show', 'cibles') }}" class="btn btn-secondary">Voir les cibles</a>
            </p>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/guides/joules.js') }}" defer></script>
@endpush
