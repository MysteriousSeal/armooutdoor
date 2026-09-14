@extends('layouts.app')

@php
    // Saturation pressure of CO2 by temperature, NIST Chemistry WebBook. The
    // table below prints these rows, and the gauge reads the same rows back
    // from the markup: one copy of the figures, and a reader without
    // JavaScript gets the table instead of the instrument.
    $pressures = [-20 => 19.696, -15 => 22.908, -10 => 26.487, -5 => 30.459, 0 => 34.851, 5 => 39.695, 10 => 45.022, 15 => 50.871, 20 => 57.291, 25 => 64.342, 30 => 72.137];
    $reference = $pressures[20];

    $fr = fn (float $value, int $places = 1): string => number_format($value, $places, ',', ' ');

    // What each band of temperature changes, bounds inclusive. Every figure
    // in these sentences comes from a source listed at the foot of the page.
    $zones = [
        ['key' => 'froid', 'from' => -20, 'to' => 4, 'range' => 'Sous 5 °C', 'title' => 'Le froid', 'body' => 'Sous 40 bar, soit moins de 70 % de la pression à 20 °C, et à peine plus d\'un tiers vers -20 °C. Chaque tir part plus faible, et Umarex prévient qu\'une bille peut alors rester dans le canon. Gardez la cartouche de rechange dans une poche intérieure et espacez les tirs.'],
        ['key' => 'frais', 'from' => 5, 'to' => 14, 'range' => '5 à 14 °C', 'title' => 'Le frais', 'body' => 'De 40 à 51 bar. Pyramyd AIR parle de puissance réduite dès qu\'on passe sous 10 °C. La réplique tire, mais avec 13 à 31 % de pression en moins qu\'à 20 °C.'],
        ['key' => 'confort', 'from' => 15, 'to' => 30, 'range' => '15 à 30 °C', 'title' => 'La plage d\'usage', 'body' => 'De 51 à 72 bar. Umarex situe l\'usage normal entre 15 et 21 °C, et Pyramyd AIR juge le CO2 à son meilleur entre 21 et 27 °C. C\'est là que les vitesses annoncées ont un sens, à condition de savoir à quelle température elles ont été mesurées.'],
        ['key' => 'chaud', 'from' => 31, 'to' => 48, 'range' => '31 à 48 °C', 'title' => 'Au-delà du point critique', 'body' => 'Passé le point critique, vers 31 °C, il n\'y a plus de liquide ni de vapeur distincts : la pression ne se lit plus dans la table, elle dépend de la quantité de CO2 enfermée. Dans une cartouche pleine, elle continue de monter avec la chaleur. Rangez à l\'ombre : Umarex rappelle que le plein soleil suffit à atteindre 50 °C.'],
        ['key' => 'danger', 'from' => 49, 'to' => 50, 'range' => 'Dès 49 °C', 'title' => 'La cartouche peut éclater', 'body' => 'Crosman écrit qu\'une cartouche peut exploser au-dessus de 48,9 °C, Umarex au-dessus de 50 °C. Voiture fermée en été, plage arrière, tableau de bord au soleil : ni la cartouche ni la réplique n\'y ont leur place.'],
    ];

    $presets = [-5 => 'Matin d\'hiver', 10 => 'Automne', 20 => 'Référence', 50 => 'Plein soleil'];

    // The dial: 0 to 80 bar over 240 degrees, centred on (120, 120). Angles
    // run clockwise from straight up.
    $polar = fn (float $radius, float $degrees): array => [
        round(120 + $radius * sin(deg2rad($degrees)), 2),
        round(120 - $radius * cos(deg2rad($degrees)), 2),
    ];
    $angleOf = fn (float $bar): float => -120 + min($bar, 80) / 80 * 240;
    [$arcStartX, $arcStartY] = $polar(92, -120);
    [$arcEndX, $arcEndY] = $polar(92, 120);

    $faq = [
        ['Combien de tirs avec une cartouche de CO2 de 12 g ?', 'Cela dépend surtout de la culasse, fixe ou mobile, et de la température. Hard Air Magazine a mesuré, à 18 °C, 83 tirs en moyenne avec les pistolets à culasse fixe et 66 tirs avec ceux à culasse mobile. Nos fiches annoncent une cinquantaine de tirs pour une culasse fixe : une estimation prudente. Par temps froid ou en tir rapide, comptez moins.'],
        ['À partir de quelle température une réplique CO2 perd-elle de la puissance ?', 'Dès qu\'il fait plus froid que sa plage d\'usage : Umarex situe l\'usage normal entre 15 et 21 °C, et Pyramyd AIR parle de puissance réduite sous 10 °C. En chiffres, la cartouche est à 57,3 bar à 20 °C, à 45,0 bar à 10 °C et à 34,9 bar à 0 °C.'],
        ['Faut-il réchauffer une cartouche de CO2 avant de tirer ?', 'La garder au chaud, oui : une poche intérieure la tient plus près de 20 °C que l\'air d\'un matin d\'hiver. La chauffer, non. La fiche de sécurité demande de la tenir à l\'écart de la chaleur, et les fabricants préviennent qu\'elle peut éclater au-delà de 48,9 °C (Crosman) ou de 50 °C (Umarex).'],
        ['Peut-on laisser la cartouche de CO2 dans la réplique ?', 'Pas plus d\'un jour ou deux. Crosman et Umarex demandent de ne pas ranger l\'arme avec une cartouche dedans, pour préserver les joints, et Pyramyd AIR conseille de ne pas dépasser 24 à 48 heures. Videz-la en tirant à vide, puis retirez-la.'],
        ['CO2 ou gaz : lequel marche le mieux en hiver ?', 'Le CO2 garde bien plus de pression. À 0 °C, une cartouche de CO2 est à 34,9 bar, et le propane, qui fait l\'essentiel du green gas, à 4,7 bar. Mais tous deux perdent une part voisine de leur pression à 20 °C (39 % pour le CO2, 43 % pour le propane) : chaque réplique est réglée pour son gaz, et toutes faiblissent au froid.'],
        ['Où jeter une cartouche de CO2 vide ?', 'Totalement vide, avec les emballages à trier, comme l\'ADEME le prévoit pour les petites cartouches de siphon. S\'il reste du gaz, en magasin ou en déchèterie, jamais dans le bac de tri. Et on ne la perce pas pour la vider : la fiche de sécurité l\'interdit, même après usage.'],
        ['Une réplique au CO2 est-elle une arme ?', 'Pas en dessous de 2 joules : le code de la sécurité intérieure ne tient pas pour armes les objets qui développent moins de 2 joules à la bouche. De 2 à 20 joules, c\'est la catégorie D, et dès 20 joules la catégorie C. Nos deux répliques au CO2 annoncent 1,6 et 1,9 joule.'],
    ];

    $sources = [
        ['label' => 'NIST Chemistry WebBook, dioxyde de carbone : propriétés à saturation de -20 à 30 °C', 'url' => 'https://webbook.nist.gov/cgi/fluid.cgi?Action=Load&ID=C124389&Type=SatP&Digits=5&PLow=&PHigh=&PInc=&TLow=-20&THigh=31&TInc=5&RefState=DEF&TUnit=C&PUnit=bar&DUnit=kg%2Fm3&HUnit=kJ%2Fkg&WUnit=m%2Fs&VisUnit=uPa*s&STUnit=N%2Fm', 'host' => 'webbook.nist.gov'],
        ['label' => 'NIST Chemistry WebBook, dioxyde de carbone : point critique et point triple', 'url' => 'https://webbook.nist.gov/cgi/cbook.cgi?ID=C124389&Mask=4', 'host' => 'webbook.nist.gov'],
        ['label' => 'NIST Chemistry WebBook, propane : pression à saturation de -20 à 30 °C', 'url' => 'https://webbook.nist.gov/cgi/fluid.cgi?Action=Load&ID=C74986&Type=SatP&Digits=5&PLow=&PHigh=&PInc=&TLow=-20&THigh=30&TInc=10&RefState=DEF&TUnit=C&PUnit=bar&DUnit=kg%2Fm3&HUnit=kJ%2Fkg&WUnit=m%2Fs&VisUnit=uPa*s&STUnit=N%2Fm', 'host' => 'webbook.nist.gov'],
        ['label' => 'Umarex, manuel du pistolet Walther CP99 au CO2 (températures, tir rapide, pression basse, huile, stockage)', 'url' => 'https://www.umarexusa.com/UMAREX/Product%20Manuals/Manual%20Walther%20CP99%20EN%2003R06.pdf', 'host' => 'umarexusa.com'],
        ['label' => 'Crosman, manuel du pistolet C11 au CO2 (stockage, joints, huile, retrait)', 'url' => 'https://www.pyramydair.com/airgun-resources/manuals/crosman-c11-bb-repeater-co2-air-pistol-manual.pdf', 'host' => 'pyramydair.com'],
        ['label' => 'Umarex USA, quelle huile pour une arme au CO2 (joints en silicone)', 'url' => 'https://www.umarexusa.com/what-kind-of-oil-should-i-use-', 'host' => 'umarexusa.com'],
        ['label' => 'Hard Air Magazine, combien de tirs pour un pistolet à billes au CO2', 'url' => 'https://hardairmagazine.com/reviews/how-many-shots-can-i-get-from-a-replica-bb-pistol/', 'host' => 'hardairmagazine.com'],
        ['label' => 'Pyramyd AIR, entretenir une arme au CO2', 'url' => 'https://www.pyramydair.com/getting-the-most-from-your-co2-airgun', 'host' => 'pyramydair.com'],
        ['label' => 'Redwolf Airsoft, qu\'est-ce que le green gas', 'url' => 'https://www.redwolfairsoft.com/blog/what-is-green-gas-airsoft', 'host' => 'redwolfairsoft.com'],
        ['label' => 'Fiche de données de sécurité d\'une cartouche de CO2 (B.V. Corporation, cartouche EZ-Seal)', 'url' => 'https://medias-norauto.fr/fds/273384_FDS_FR.pdf', 'host' => 'medias-norauto.fr'],
        ['label' => 'ADEME, Que faire de mes déchets : cartouche pour siphon', 'url' => 'https://quefairedemesdechets.ademe.fr/dechet/cartouche-pour-siphon-a-chantilly/', 'host' => 'quefairedemesdechets.ademe.fr'],
        ['label' => 'IATA, tableau 2.3.A (67e édition, 2026) : marchandises dangereuses transportées par les passagers', 'url' => 'https://www.iata.org/contentassets/6fea26dd84d24b26a7a1fd5788561d6e/dgr-67-fr-2.3.a.pdf', 'host' => 'iata.org'],
        ['label' => 'Code de la sécurité intérieure, article R311-1 (objets de moins de 2 joules)', 'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000052208381', 'host' => 'legifrance.gouv.fr'],
        ['label' => 'Code de la sécurité intérieure, article R311-2 (catégories C et D)', 'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000052208392', 'host' => 'legifrance.gouv.fr'],
    ];

    $description = 'Pourquoi une réplique CO2 faiblit au froid : la pression de la cartouche degré par degré, les tirs par 12 g, le stockage, le transport et le recyclage.';
@endphp

@section('title', 'Réplique CO2 et froid : pression et tirs - Armo Outdoor')
@section('meta_description', $description)
@section('og_type', 'article')
@section('canonical', route('guides.co2'))

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/categories.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('css/guides.css') }}">
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'Article',
            'headline' => 'Réplique CO2 : pourquoi elle faiblit au froid',
            'description' => $description,
            'mainEntityOfPage' => route('guides.co2'),
            'inLanguage' => 'fr-FR',
            'image' => versioned_asset(\App\Support\Guides::byRoute('guides.co2')['image'] ?? 'images/hero.webp'),
            'datePublished' => \App\Support\Guides::byRoute('guides.co2')['published'],
            'dateModified' => \App\Support\Guides::byRoute('guides.co2')['updated'],
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
                ['@@type' => 'ListItem', 'position' => 3, 'name' => 'CO2 et froid', 'item' => route('guides.co2')],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('content')
    <div class="container glab glab--co2">
        @include('guides.partials.hero', [
            'crumb' => 'CO2 et froid',
            'kicker' => 'Gaz',
            'title' => 'Réplique CO2 : pourquoi elle faiblit au froid',
            'lede' => 'Une réplique au CO2 qui tirait fort en septembre tire mou en janvier, sans que rien ne soit cassé. Tant qu\'elle contient du liquide, la pression d\'une cartouche ne dépend pas de la quantité de gaz, mais de la température : 57 bar à 20 °C, 35 bar à 0 °C. Ce guide montre la pression degré par degré, ce qu\'une cartouche de 12 g donne en tirs, et comment huiler, ranger, jeter et transporter ses cartouches.',
            'tags' => ['12 g et 88 g', '57 bar à 20 °C', '35 bar à 0 °C'],
        ])

        <nav class="glab-plan" aria-label="Plan du guide">
            <p class="glab-plan-kicker">Plan</p>
            <div class="glab-plan-links">
                <a href="#co2-froid-title">Pourquoi le froid</a>
                <a href="#co2-manometre-title">Le manomètre</a>
                <a href="#co2-tableau-title">Le tableau</a>
                <a href="#co2-rafale-title">La rafale</a>
                <a href="#co2-tirs-title">Combien de tirs</a>
                <a href="#co2-gaz-title">CO2 ou gaz</a>
                <a href="#co2-gestes-title">Les gestes</a>
                <a href="#co2-vide-title">Cartouche vide</a>
                <a href="#co2-transport-title">Transport</a>
                <a href="#co2-loi-title">La loi</a>
                <a href="#co2-faq-title">Questions</a>
                <a href="#co2-sources-title">Sources</a>
            </div>
        </nav>

        <p class="glab-warning">
            <strong class="glab-warning-label">Jamais au soleil, jamais dans la voiture</strong>
            Au-delà de 48,9 °C selon Crosman et de 50 °C selon Umarex, une cartouche de CO2 peut
            éclater, et le plein soleil suffit à atteindre 50 °C. Ni la cartouche ni la réplique
            chargée ne restent dans un véhicule fermé l'été.
        </p>

        <section class="glab-section" aria-labelledby="co2-froid-title">
            <h2 class="glab-title" id="co2-froid-title">Pourquoi une réplique CO2 <span class="glab-title-accent">faiblit-elle au froid ?</span></h2>
            <p class="glab-lede glab-lede--thesis">
                Dans une cartouche, la pression ne dit pas combien de gaz il reste. Tant qu'il y a
                du liquide, elle dit quelle température il fait.
            </p>

            <div class="glab-prose">
                <p>
                    Une cartouche de 12 g ne contient pas du gaz comprimé, mais du CO2 en grande
                    partie liquide, surmonté de sa vapeur. Tant qu'il reste du liquide, la pression
                    qui règne au-dessus est celle de cette vapeur, dite pression de vapeur, et elle
                    ne dépend que de la température : 57,3 bar à 20 °C, 34,9 bar à 0 °C, 26,5 bar à
                    -10 °C. Par -10 °C, la même cartouche pleine n'a donc qu'un peu moins de la
                    moitié de sa pression à 20 °C.
                </p>
                <p>
                    C'est aussi pourquoi, à température égale, une cartouche ne faiblit vraiment
                    qu'à la toute fin : tant que du liquide s'évapore pour remplacer le gaz parti
                    derrière la bille, la pression reste celle que fixe la température. Une fois le
                    liquide épuisé, elle baisse à chaque tir. Reste une limite : la table s'arrête à
                    31 °C, le point critique du CO2, où la pression atteint 73,8 bar. Au-delà,
                    liquide et vapeur ne se distinguent plus.
                </p>
            </div>

            <aside class="glab-joules-pair" aria-label="Même cartouche, deux températures">
                <p>
                    <span class="glab-joules-pair-kicker">Même cartouche pleine · 20 °C</span>
                    <strong>57,3 bar</strong>
                </p>
                <p>
                    <span class="glab-joules-pair-kicker">Même cartouche pleine · 0 °C</span>
                    <strong>34,9 bar</strong>
                </p>
                <p class="glab-joules-pair-note">
                    Rien n'a été tiré entre les deux. Il a seulement fait vingt degrés de moins, et
                    la cartouche a perdu 39 % de sa pression.
                </p>
            </aside>
        </section>

        {{-- The gauge. Hidden until the script has read the table: without
             JavaScript, the table and the bands below give the same answer,
             so nothing here promises a control that cannot move. --}}
        <section class="glab-section" data-glab-co2 data-co2-state="confort" hidden aria-labelledby="co2-manometre-title">
            <h2 class="glab-title" id="co2-manometre-title">Quelle pression <span class="glab-title-accent">à quelle température ?</span></h2>
            <p class="glab-lede">
                Faites glisser le curseur pour changer la température de la cartouche. Le manomètre
                lit la pression dans la table du NIST, l'institut américain de métrologie, et
                indique ce que change cette plage de température.
            </p>

            <div class="glab-calc-grid">
                <div class="glab-co2-dial">
                    <svg viewBox="-14 0 268 178" role="img" aria-label="Manomètre de 0 à 80 bar">
                        <path class="glab-co2-arc" d="M {{ $arcStartX }} {{ $arcStartY }} A 92 92 0 1 1 {{ $arcEndX }} {{ $arcEndY }}" />
                        <path class="glab-co2-fill" data-co2-fill pathLength="100" d="M {{ $arcStartX }} {{ $arcStartY }} A 92 92 0 1 1 {{ $arcEndX }} {{ $arcEndY }}" style="--co2-fill: {{ round($reference / 80 * 100, 1) }}" />
                        @for ($bar = 0; $bar <= 80; $bar += 10)
                            @php([$x1, $y1] = $polar(74, $angleOf($bar)))
                            @php([$x2, $y2] = $polar(82, $angleOf($bar)))
                            @php([$tx, $ty] = $polar(62, $angleOf($bar)))
                            <g class="glab-co2-tick">
                                <line x1="{{ $x1 }}" y1="{{ $y1 }}" x2="{{ $x2 }}" y2="{{ $y2 }}" />
                                @if ($bar % 20 === 0)
                                    <text x="{{ $tx }}" y="{{ $ty + 3 }}" text-anchor="middle">{{ $bar }}</text>
                                @endif
                            </g>
                        @endfor
                        {{-- Two readings worth marking on the face: the reference the
                             percentages are taken against, and the critical point. --}}
                        @foreach ([[$reference, '20 °C'], [73.825, '31 °C']] as [$markBar, $markLabel])
                            @php([$mx1, $my1] = $polar(84, $angleOf($markBar)))
                            @php([$mx2, $my2] = $polar(101, $angleOf($markBar)))
                            @php([$mlx, $mly] = $polar(113, $angleOf($markBar)))
                            <g class="glab-co2-mark">
                                <line x1="{{ $mx1 }}" y1="{{ $my1 }}" x2="{{ $mx2 }}" y2="{{ $my2 }}" />
                                <text x="{{ $mlx }}" y="{{ $mly + 3 }}" text-anchor="middle">{{ $markLabel }}</text>
                            </g>
                        @endforeach
                        <text class="glab-co2-unit" x="120" y="150" text-anchor="middle">bar</text>
                        <g class="glab-co2-needle" data-co2-needle style="--co2-angle: {{ round($angleOf($reference), 2) }}deg">
                            <line x1="120" y1="132" x2="120" y2="44" />
                            <circle cx="120" cy="120" r="6" />
                        </g>
                    </svg>

                    <label class="glab-co2-slider-label" for="co2-temperature">
                        Température de la cartouche
                        <output data-co2-temp for="co2-temperature">20 °C</output>
                    </label>
                    <input type="range" id="co2-temperature" class="glab-co2-slider" min="-20" max="50" step="1" value="20" data-co2-slider>
                    <p class="glab-co2-slider-ends" aria-hidden="true"><span>-20 °C</span><span>50 °C</span></p>

                    <div class="glab-chips glab-co2-presets" aria-label="Températures types">
                        @foreach ($presets as $value => $label)
                            <button type="button" data-co2-preset="{{ $value }}" @class(['is-active' => $value === 20])>{{ $value }} °C <small>{{ $label }}</small></button>
                        @endforeach
                    </div>
                </div>

                <output class="glab-calc-result" for="co2-temperature" aria-live="polite">
                    <span class="glab-calc-energy">
                        <strong data-co2-bar data-co2-off="Hors table">{{ $fr($reference) }}</strong>
                        <span data-co2-bar-unit>bar dans la cartouche</span>
                    </span>
                    <span class="glab-calc-equiv" data-co2-ratio data-co2-ratio-label="de la pression à 20 °C" data-co2-off-note="Au-delà de 31 °C, la table ne donne plus de pression">100 % de la pression à 20 °C</span>
                    <span class="glab-calc-band">
                        <span class="glab-co2-zone-title" data-co2-zone-title>{{ $zones[2]['title'] }}</span>
                        <span class="glab-co2-zone-body" data-co2-zone-body>{{ $zones[2]['body'] }}</span>
                    </span>
                </output>
            </div>

            <p class="glab-calc-note">
                La pression est celle d'une cartouche qui contient encore du liquide, à la
                température indiquée. Ce n'est pas une vitesse : une réplique ne réagit pas à la
                pression de façon proportionnelle, et aucun chiffre de vitesse n'est avancé ici. Entre deux
                lignes de la table, la valeur est interpolée en ligne droite.
            </p>
        </section>

        <section class="glab-section glab-co2-table" aria-labelledby="co2-tableau-title">
            <h2 class="glab-title" id="co2-tableau-title">Pression d'une cartouche de CO2 <span class="glab-title-accent">de -20 à 30 °C</span></h2>
            <p class="glab-lede">
                Onze valeurs du NIST, de 5 en 5 degrés, chacune rapportée à la pression à 20 °C.
                Au-delà de 30 °C, on approche du point critique et la série s'arrête.
            </p>

            <div class="glab-table-wrap">
                <table class="glab-table">
                    <caption>Pression de vapeur du CO2, en bar, et part de la pression à 20 °C.</caption>
                    <thead>
                        <tr>
                            <th scope="col">Température</th>
                            <th scope="col">Pression</th>
                            <th scope="col">Part à 20 °C</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pressures as $celsius => $bar)
                            <tr data-co2-row data-t="{{ $celsius }}" data-bar="{{ $bar }}" @class(['is-current' => $celsius === 20])>
                                <th scope="row">{{ $celsius }} °C</th>
                                <td>{{ $fr($bar) }} bar</td>
                                <td>{{ round($bar / $reference * 100) }} %</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <dl class="glab-specs glab-co2-zones">
                @foreach ($zones as $zone)
                    <div
                        class="glab-co2-zone glab-co2-zone--{{ $zone['key'] }}"
                        data-co2-zone="{{ $zone['key'] }}"
                        data-from="{{ $zone['from'] }}"
                        data-to="{{ $zone['to'] }}"
                    >
                        <dt>{{ $zone['title'] }} <em>{{ $zone['range'] }}</em></dt>
                        <dd>{{ $zone['body'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section class="glab-section" aria-labelledby="co2-rafale-title">
            <h2 class="glab-title" id="co2-rafale-title">Pourquoi faiblit-elle aussi <span class="glab-title-accent">en tir rapide ?</span></h2>
            <p class="glab-lede">
                Même par 25 °C, une rafale refroidit la cartouche. L'effet est celui du froid, mais
                c'est le tireur qui le provoque.
            </p>

            <div class="glab-prose">
                <p>
                    Pour remplacer le gaz parti derrière la bille, du liquide s'évapore, et
                    l'évaporation absorbe de la chaleur. Le NIST donne l'ordre de grandeur : il faut
                    152 joules pour vaporiser un gramme de CO2 à 20 °C, et 231 joules à 0 °C. Une
                    réplique qui tire 80 coups avec 12 g consomme 0,15 g par coup. Or le liquide
                    évaporé doit à la fois remplacer ce gaz et occuper la place qu'il laisse libre :
                    avec les densités du même tableau, cela fait environ 30 joules de chaleur pris à
                    la cartouche à chaque coup à 20 °C, quand la bille, elle, part avec moins de
                    2 joules.
                </p>
                <p>
                    Tant que les tirs sont espacés, l'air ambiant rend cette chaleur. En rafale, il
                    n'en a pas le temps : la cartouche refroidit, sa pression baisse comme dans le
                    tableau, et les billes sortent de moins en moins vite. Le manuel Umarex le
                    confirme : en tir rapide, la vitesse baisse à chaque coup. Pour Hard Air
                    Magazine, le CO2 est un gaz réfrigérant, et plus on tire vite, plus il se
                    refroidit. La parade est simple : laisser reposer la réplique entre deux
                    chargeurs.
                </p>
            </div>
        </section>

        <section class="glab-section" aria-labelledby="co2-tirs-title">
            <h2 class="glab-title" id="co2-tirs-title">Cartouche CO2 12 g : <span class="glab-title-accent">combien de tirs ?</span></h2>
            <p class="glab-lede">
                Un nombre de tirs n'a de sens qu'avec deux précisions : le type de culasse et la
                température de la mesure.
            </p>

            <dl class="glab-specs">
                <div>
                    <dt>Culasse fixe <em>83 tirs en moyenne</em></dt>
                    <dd>Mesure de Hard Air Magazine à 18 °C, pour 366 FPS (pieds par seconde) en
                        moyenne. La culasse, la partie supérieure du pistolet, ne bouge pas : tout le
                        gaz pousse la bille. C'est le cas du
                        <a href="{{ route('products.show', 'pistolet-ruger-p345-co2-culasse-fixe-6mm-airsoft') }}">Ruger P345 d'Umarex</a>, à 1,9 joule.
                        Nos fiches annoncent une cinquantaine de tirs, une estimation prudente.</dd>
                </div>
                <div>
                    <dt>Culasse mobile <em>66 tirs en moyenne</em></dt>
                    <dd>Même magazine, même température, 325 FPS en moyenne. Une partie du gaz sert à
                        faire reculer la culasse à chaque tir : le réalisme se paie en tirs et en
                        vitesse.</dd>
                </div>
                <div>
                    <dt>Cartouche de 88 g <em>plus de sept fois le gaz</em></dt>
                    <dd>88 grammes contre 12, pour les répliques qui l'acceptent directement ou par un
                        adaptateur. Le froid la fait faiblir tout autant : la pression ne dépend pas
                        de la taille de la cartouche.</dd>
                </div>
            </dl>

            <div class="glab-prose">
                <p>
                    Ce qui fait varier le compte, sur une même réplique : la température d'abord,
                    puisque chaque tir part avec moins de pression au froid ; le rythme ensuite,
                    puisqu'une rafale refroidit la cartouche ; et le moment où l'on s'arrête, car une
                    réplique au CO2 ne tombe pas en panne d'un coup, elle tire de plus en plus court.
                    Umarex rappelle qu'avec trop peu de pression, une bille peut rester dans le canon,
                    et indique le signe à guetter : un tir moins sonore qu'avec une cartouche pleine. Quand le son change, on
                    change de cartouche.
                </p>
            </div>

            <p class="glab-ctas">
                <a href="{{ route('categories.show', 'cartouches-de-co2-12g-et-88g') }}" class="btn btn-primary">Voir les cartouches 12 g et 88 g</a>
                <a href="{{ route('categories.show', 'repliques-de-poing') }}" class="btn btn-secondary">Voir les répliques de poing</a>
            </p>
        </section>

        <section class="glab-section" aria-labelledby="co2-gaz-title">
            <h2 class="glab-title" id="co2-gaz-title">CO2 ou gaz : <span class="glab-title-accent">lequel en hiver ?</span></h2>
            <p class="glab-lede">
                Le CO2 garde bien plus de pression au froid, mais il en perd une part à peine plus
                petite que le gaz.
            </p>

            <div class="glab-table-wrap">
                <table class="glab-table">
                    <caption>Pression de vapeur en bar, d'après le NIST. Le propane fait l'essentiel du green gas.</caption>
                    <thead>
                        <tr>
                            <th scope="col">Gaz</th>
                            <th scope="col">-10 °C</th>
                            <th scope="col">0 °C</th>
                            <th scope="col">20 °C</th>
                            <th scope="col">Perte de 20 à 0 °C</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th scope="row">CO2</th>
                            <td>26,5</td>
                            <td>34,9</td>
                            <td>57,3</td>
                            <td>39 %</td>
                        </tr>
                        <tr>
                            <th scope="row">Propane</th>
                            <td>3,5</td>
                            <td>4,7</td>
                            <td>8,4</td>
                            <td>43 %</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="glab-prose">
                <p>
                    Le green gas, le gaz des répliques à gaz, est du propane additionné d'huile de
                    silicone. Redwolf le dit sensible au froid et au tir rapide, comme le CO2. Les
                    deux gaz perdent une part voisine de leur pression entre 20 et 0 °C ; la
                    différence est qu'une réplique au CO2 part de 57 bar et garde encore 35 bar à
                    0 °C, là où le propane tombe sous 5 bar. Chaque réplique est conçue pour un seul
                    gaz : on ne choisit donc pas un gaz, on choisit une réplique.
                </p>
                <p>
                    Pour qui joue surtout l'hiver, la réplique électrique échappe à la question : nos
                    <a href="{{ route('categories.show', 'repliques-longues') }}">répliques longues</a>
                    sont des AEG, à moteur électrique, sans gaz. Côté munitions, notre fiche du Ruger P345 conseille des
                    <a href="{{ route('categories.show', 'billes-airsoft') }}">billes de 0,20 g</a>.
                </p>
            </div>
        </section>

        <section class="glab-section" aria-labelledby="co2-gestes-title">
            <h2 class="glab-title" id="co2-gestes-title">Comment huiler, vider et ranger <span class="glab-title-accent">une cartouche de CO2 ?</span></h2>
            <p class="glab-lede">
                Dans l'ordre d'une séance, de la cartouche neuve à la réplique rangée.
            </p>

            <ol class="glab-rules">
                <li>
                    <h3>Une goutte d'huile sur la pointe</h3>
                    <p>
                        Umarex et Crosman demandent une goutte d'huile sur la pointe de chaque
                        cartouche avant de l'insérer, sans en rajouter, et jamais d'huile ni de
                        solvant à base de distillats de pétrole. Les joints de valve sont en
                        silicone : Umarex USA explique qu'une huile classique les dégrade et
                        recommande une huile 100 % silicone.
                    </p>
                </li>
                <li>
                    <h3>Vider avant de retirer</h3>
                    <p>
                        On ne sort pas une cartouche encore sous pression. Crosman demande de
                        vérifier qu'elle est vide en dévissant lentement, jusqu'à ne plus entendre
                        de gaz ; Pyramyd AIR conseille de tirer à vide jusqu'à épuisement. Le gaz
                        qui s'échappe peut geler la peau, préviennent les notices de Crosman et
                        d'Umarex.
                    </p>
                </li>
                <li>
                    <h3>Ne pas la laisser percée dans l'arme</h3>
                    <p>
                        Crosman et Umarex sont clairs : on ne range pas l'arme avec une cartouche
                        dedans, et c'est la longévité des joints qui est en jeu. Pyramyd AIR fixe une
                        limite de 24 à 48 heures. Mieux vaut perdre la fin d'une cartouche que les
                        joints de la réplique.
                    </p>
                </li>
                <li>
                    <h3>Au frais, à l'ombre, hors de la voiture</h3>
                    <p>
                        Crosman place la limite à 48,9 °C et Umarex à 50 °C, une température que le
                        plein soleil suffit à atteindre, ajoute Umarex. Pyramyd AIR demande de ne
                        jamais ranger cartouches et armes dans une voiture. La fiche de sécurité le
                        résume : récipient sous pression, peut éclater sous l'effet de la chaleur,
                        protéger du rayonnement solaire, ne pas exposer à une température supérieure
                        à 50 °C.
                    </p>
                </li>
            </ol>

            <p class="glab-more-reading">
                Le reste de l'entretien, dont le nettoyage du canon, est dans notre guide
                <a href="{{ route('guides.entretien') }}">Entretenir son arme</a>.
            </p>
        </section>

        <section class="glab-section" aria-labelledby="co2-vide-title">
            <h2 class="glab-title" id="co2-vide-title">Que faire d'une <span class="glab-title-accent">cartouche CO2 vide ?</span></h2>
            <p class="glab-lede">
                Vide, elle rejoint les emballages. S'il reste du gaz, surtout pas le bac de tri.
            </p>

            <div class="glab-prose">
                <p>
                    Assurez-vous d'abord qu'elle est vraiment vide : plus un bruit de gaz au
                    dévissage. Et ne la percez pas pour en avoir le cœur net : la fiche de sécurité
                    d'une cartouche de CO2 dit « ne pas perforer, ni brûler, même après usage ».
                </p>
                <p>
                    L'ADEME, l'Agence de la transition écologique, n'a pas de page pour les
                    cartouches d'armes à air, mais celle qui s'en approche le plus, consacrée aux
                    petites cartouches de siphon, est claire. Totalement vide, la cartouche va dans
                    le bac, le sac ou le conteneur de tri des papiers et emballages. S'il reste du
                    gaz, elle part en magasin ou en déchèterie, jamais dans le bac de tri : elle y
                    présenterait un risque d'explosion pour la collecte. Si la déchèterie la refuse,
                    la collectivité indique où la déposer.
                </p>
            </div>
        </section>

        <section class="glab-section" aria-labelledby="co2-transport-title">
            <h2 class="glab-title" id="co2-transport-title">Peut-on transporter des cartouches de CO2 <span class="glab-title-accent">en voiture et en avion ?</span></h2>
            <p class="glab-lede">
                Pour la route, c'est la chaleur qui compte. Pour l'avion, c'est la compagnie qui
                décide.
            </p>

            <div class="glab-prose">
                <p>
                    En voiture, la cartouche voyage dans le coffre ou le sac, jamais au soleil
                    derrière une vitre, et ne reste pas dans le véhicule garé. Pour la réplique
                    elle-même et, au-delà de 2 joules, pour les armes de catégorie D, les règles de
                    trajet sont dans notre guide
                    <a href="{{ route('guides.transport') }}">Transporter son arme</a>.
                </p>
                <p>
                    En avion, une cartouche de CO2 est une marchandise dangereuse (UN 1013, classe
                    2.2). Les règles pour les passagers sont dans le tableau 2.3.A de l'IATA,
                    l'association internationale des compagnies aériennes (édition 2026). Hors
                    dispositifs autogonflables comme les gilets de sauvetage, les autres appareils
                    n'ont droit qu'à quatre petites cartouches d'une capacité en eau de 50 mL au
                    plus, en cabine ou en soute, et seulement avec l'approbation de la compagnie.
                    Demandez-la avant de faire vos bagages ; sinon, achetez vos cartouches sur place.
                </p>
            </div>
        </section>

        <section class="glab-section" aria-labelledby="co2-loi-title">
            <h2 class="glab-title" id="co2-loi-title">Réplique CO2 ou arme à air : <span class="glab-title-accent">que dit la loi ?</span></h2>
            <p class="glab-lede">
                Le gaz ne compte pas : seule l'énergie à la bouche, celle du projectile à la sortie
                du canon, classe l'objet.
            </p>

            <dl class="glab-specs">
                <div>
                    <dt>Sous 2 J <em>pas une arme</em></dt>
                    <dd>L'article R311-1 ne tient pas pour armes les objets tirant un projectile avec
                        moins de 2 joules à la bouche. Nos répliques au CO2 en font partie : le
                        <a href="{{ route('products.show', 'revolver-dan-wesson-715-nickele-4-pouces-6-mm-airsoft') }}">Dan Wesson 715 d'ASG</a>
                        annonce 1,6 joule, le Ruger P345 1,9 joule.</dd>
                </div>
                <div>
                    <dt>2 à 20 J <em>catégorie D</em></dt>
                    <dd>L'article R311-2 y range les armes dont le projectile est propulsé de manière
                        non pyrotechnique avec une énergie comprise entre 2 et 20 joules. Un pistolet
                        à plombs qui dépasse 2 joules y entre, qu'il fonctionne au CO2 ou non.</dd>
                </div>
                <div>
                    <dt>Dès 20 J <em>catégorie C</em></dt>
                    <dd>Même article : à une énergie supérieure ou égale à 20 joules, l'arme passe en
                        catégorie C, soumise à déclaration.</dd>
                </div>
            </dl>

            <p class="glab-more-reading">
                Une énergie annoncée n'a de sens qu'à une température donnée : Hard Air Magazine
                rappelle que la vitesse d'une arme au CO2 ne vaut rien sans la température de la
                mesure. La conversion
                est dans <a href="{{ route('guides.joules') }}">Joules et FPS</a>, les quatre régimes
                dans <a href="{{ route('guides.classification') }}">Classer son arme</a>, et les mots
                du rayon dans <a href="{{ route('guides.glossaire') }}">le glossaire</a>.
            </p>
        </section>

        <section class="glab-faq" aria-labelledby="co2-faq-title">
            <h2 class="glab-title" id="co2-faq-title">Questions <span class="glab-title-accent">fréquentes</span></h2>
            @foreach ($faq as $qa)
                <details>
                    <summary>{{ $qa[0] }}</summary>
                    <div>
                        <p>{{ $qa[1] }}</p>
                    </div>
                </details>
            @endforeach
        </section>

        <section class="glab-section" aria-labelledby="co2-sources-title">
            <h2 class="glab-title" id="co2-sources-title">Les <span class="glab-title-accent">sources</span></h2>
            <p class="glab-lede">
                Les tables du NIST, les manuels des fabricants, une fiche de sécurité, l'ADEME,
                l'IATA et le code de la sécurité intérieure. Vérifiez : cette page ne demande pas d'être crue sur parole.
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
            <a href="{{ route('categories.show', 'cartouches-de-co2-12g-et-88g') }}" class="btn btn-primary">Voir les cartouches de CO2</a>
            <a href="{{ route('guides.index') }}" class="btn btn-secondary">Tous les guides</a>
        </p>
    </div>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/guides.js') }}" defer></script>
@endpush
