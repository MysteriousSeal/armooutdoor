@extends('layouts.app')

@php
    // The model behind the calculator and the table, written once: the
    // buttons carry these numbers for the script, and the table below is
    // what a reader without JavaScript gets instead. Every range is a
    // measured or published one (see the sources); the ranges are kept as
    // ranges, because a single decibel figure would claim a precision the
    // measurements do not have.
    $arms = [
        ['key' => '22lr', 'label' => 'Carabine .22 LR', 'hint' => '144 dB de crête', 'peak' => [144, 144]],
        ['key' => 'courante', 'label' => 'Arme de chasse ou de tir courante', 'hint' => '150 à 165 dB de crête', 'peak' => [150, 165]],
        ['key' => 'forte', 'label' => 'Les plus bruyantes', 'hint' => '165 à 175 dB de crête', 'peak' => [165, 175]],
    ];

    // Peak reduction at the shooter's ear, and reduction of the energy
    // received over eight hours (NIOSH, Murphy et al. 2018).
    $moderators = [
        ['key' => 'sans', 'label' => 'Sans modérateur', 'hint' => 'Le coup tel quel', 'peak' => [0, 0], 'energy' => [0, 0]],
        ['key' => 'avec', 'label' => 'Avec modérateur', 'hint' => '17 à 24 dB de moins', 'peak' => [17, 24], 'energy' => [9, 21]],
    ];

    // A second protector adds 5 to 10 dB to the better one, never the sum.
    $protections = [
        ['key' => 'aucune', 'label' => 'Aucune', 'hint' => 'Oreilles nues', 'extra' => null],
        ['key' => 'simple', 'label' => 'Bouchons ou casque', 'hint' => 'Le SNR du protecteur', 'extra' => [0, 0]],
        ['key' => 'double', 'label' => 'Bouchons et casque', 'hint' => '5 à 10 dB de plus', 'extra' => [5, 10]],
    ];

    $snrs = [20, 25, 30, 33];

    // The quietest and the loudest a shot can plausibly reach the ear.
    $atEar = function (array $arm, array $moderator, ?array $extra, int $snr): array {
        $protection = $extra === null ? [0, 0] : [$snr + $extra[0], $snr + $extra[1]];

        return [
            $arm['peak'][0] - $moderator['peak'][1] - $protection[1],
            $arm['peak'][1] - $moderator['peak'][0] - $protection[0],
        ];
    };

    $range = fn (array $db): string => $db[0] === $db[1] ? $db[0].' dB' : $db[0].' à '.$db[1].' dB';

    // Shaded as the joules table is: towards the peak thresholds, full once over.
    $band = fn (array $db): string => match (true) {
        $db[0] >= 140 => 'is-over',
        $db[1] >= 135 => 'is-near',
        default => '',
    };

    // The axis runs from 80 to 180 dB, so a level's position is its excess over 80.
    $axis = fn (int $db): int => max(0, min(100, $db - 80));

    $columns = [
        ['label' => 'Oreilles nues', 'moderator' => 0, 'protection' => 0],
        ['label' => 'Modérateur seul', 'moderator' => 1, 'protection' => 0],
        ['label' => 'Protection SNR 30', 'moderator' => 0, 'protection' => 1],
        ['label' => 'Modérateur et SNR 30', 'moderator' => 1, 'protection' => 1],
        ['label' => 'Modérateur et double protection', 'moderator' => 1, 'protection' => 2],
    ];

    $default = $atEar($arms[1], $moderators[1], null, 30);

    $faq = [
        ['Un modérateur de son est-il légal en France ?', 'Oui. Le Code de la sécurité intérieure ne le range dans aucune catégorie : son article R311-1 dit que les réducteurs de son constituant des pièces additionnelles ne modifiant pas le fonctionnement de l\'arme ne sont pas des armes. L\'achat reste encadré par l\'article R312-45-2, et l\'emploi à la chasse est autorisé depuis l\'arrêté du 2 janvier 2018.'],
        ['Faut-il un permis pour acheter un modérateur de son ?', 'Oui, et pas seulement un permis. L\'article R312-45-2 demande deux pièces : d\'une part un permis de chasser validé, une licence d\'une fédération de tir, de ball-trap ou de biathlon, ou une carte de collectionneur ; d\'autre part le titre de détention de l\'arme sur laquelle le modérateur sera monté. Ce n\'est pas un achat libre pour tout majeur.'],
        ['Combien de décibels retire un modérateur de son ?', 'Sur les armes mesurées par le NIOSH, l\'institut américain de santé et de sécurité au travail, entre 17 et 24 dB de crête à l\'oreille du tireur, et entre 9 et 21 dB sur l\'énergie reçue en huit heures. Le gain est plus grand avec des munitions lentes : une balle supersonique produit sa propre onde de choc, que le modérateur n\'atténue pas.'],
        ['Peut-on chasser avec un modérateur de son ?', 'Oui. L\'arrêté du 1er août 1986 interdisait à la chasse l\'emploi sur les armes à feu de « tout dispositif silencieux destiné à atténuer le bruit au départ du coup ». L\'arrêté du 2 janvier 2018, publié au Journal officiel du 23 janvier 2018, a supprimé cette interdiction.'],
        ['Avec un modérateur, faut-il encore des bouchons d\'oreille ?', 'Oui. Une arme courante produit 150 à 165 dB de crête ; retirez 17 à 24 dB et le coup reste autour des seuils du Code du travail, de 135 à 140 dB(C), et peut les dépasser. Les chercheurs du NIOSH concluent que les expositions répétées restent un risque réel avec une arme équipée, et qu\'il faut porter une protection auditive à chaque séance de tir ou de chasse.'],
        ['Mes oreilles sifflent après le tir : que faire ?', 'Consultez un médecin généraliste ou un ORL dans les 24 heures, recommande la Fondation pour l\'audition : les chances de récupération en dépendent. Un sifflement ou une sensation d\'oreille pleine suit souvent un tir sans protection ou mal protégé ; pour l\'OMS, des acouphènes qui persistent peuvent être le signe d\'une lésion.'],
        ['Quel filetage pour un modérateur de .22 LR ?', 'Le 1/2 UNF, aussi écrit 1/2x20, est le filetage standard en Europe sur les armes de calibre .22 LR. Un modérateur fileté en M14x1 se monte sur un canon en 1/2 UNF au moyen d\'un adaptateur vissé en bout de canon. Pour un autre filetage, comme le M18x1, vérifiez qu\'un adaptateur existe pour votre canon avant d\'acheter.'],
    ];

    $sources = [
        ['label' => 'Code de la sécurité intérieure, article R311-1 (ce qui n\'est pas une arme)', 'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000052208381', 'host' => 'legifrance.gouv.fr'],
        ['label' => 'Code de la sécurité intérieure, articles R312-45 à R312-49 (acquisition des réducteurs de son)', 'url' => 'https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000025503132/LEGISCTA000029655185/', 'host' => 'legifrance.gouv.fr'],
        ['label' => 'Code de la sécurité intérieure, article R312-53 (permis, licence, carte de collectionneur)', 'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000045148032', 'host' => 'legifrance.gouv.fr'],
        ['label' => 'Arrêté du 2 janvier 2018 modifiant l\'arrêté du 1er août 1986 relatif à divers procédés de chasse', 'url' => 'https://www.legifrance.gouv.fr/jorf/id/JORFTEXT000036526143', 'host' => 'legifrance.gouv.fr'],
        ['label' => 'Fédération des chasseurs de Haute-Garonne, modérateur de son : la loi a changé', 'url' => 'https://www.chasse-nature-occitanie.fr/haute-garonne/actualites/a11434/moderateur-de-son,-la-loi-a-change', 'host' => 'chasse-nature-occitanie.fr'],
        ['label' => 'Murphy et al., NIOSH, réduction du bruit des tirs par les modérateurs et les munitions lentes (International Journal of Audiology, 2018)', 'url' => 'https://stacks.cdc.gov/view/cdc/111710', 'host' => 'stacks.cdc.gov'],
        ['label' => 'Finan et al., prévention des pertes auditives dues aux armes de loisir (Seminars in Hearing, 2017)', 'url' => 'https://stacks.cdc.gov/view/cdc/210834', 'host' => 'stacks.cdc.gov'],
        ['label' => 'Murphy, Byrne et Franks, NIOSH, Firearms and Hearing Protection (The Hearing Review, 2007)', 'url' => 'https://hearingreview.com/hearing-loss/patient-care/evaluation/firearms-and-hearing-protection', 'host' => 'hearingreview.com'],
        ['label' => 'Code du travail, article R4431-2 (valeurs limites d\'exposition au bruit)', 'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000018530386', 'host' => 'legifrance.gouv.fr'],
        ['label' => 'INRS, bruit : réglementation (valeurs d\'action et valeur limite, prise en compte des protecteurs)', 'url' => 'https://www.inrs.fr/risques/bruit/reglementation.html', 'host' => 'inrs.fr'],
        ['label' => 'INRS, bruit : exposition au risque', 'url' => 'https://www.inrs.fr/risques/bruit/exposition-risque.html', 'host' => 'inrs.fr'],
        ['label' => 'OMS, surdité et déficience auditive : écoute sans risque', 'url' => 'https://www.who.int/fr/news-room/questions-and-answers/item/deafness-and-hearing-loss-safe-listening', 'host' => 'who.int'],
        ['label' => 'Officiel Prévention, les protecteurs individuels contre le bruit', 'url' => 'https://www.officiel-prevention.com/dossier/protections-individuelles/l-audition/les-protecteurs-individuels-contre-le-bruit-picb', 'host' => 'officiel-prevention.com'],
        ['label' => 'Fondation pour l\'audition, le traumatisme sonore', 'url' => 'https://www.fondationpourlaudition.org/le-traumatisme-sonore-635', 'host' => 'fondationpourlaudition.org'],
        ['label' => 'Adaptateur & Silencieux, adaptateur 1/2 UNF vers M14x1', 'url' => 'https://www.adaptateur-silencieux.fr/produit/adaptateur-silencieux-1-2-unf-vers-m14x1/', 'host' => 'adaptateur-silencieux.fr'],
    ];

    $headline = 'Modérateur de son : ce qu\'il retire vraiment et comment protéger son audition';
    $description = 'Modérateur de son en France : ce que dit la loi, les décibels qu\'il retire vraiment, les seuils de l\'audition et un calcul du niveau à l\'oreille.';
    $guide = \App\Support\Guides::byRoute('guides.moderateur');
@endphp

@section('title', 'Modérateur de son : décibels, loi, audition - Armo Outdoor')
@section('meta_description', $description)
@section('og_type', 'article')
@section('canonical', route('guides.moderateur'))

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/categories.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('css/guides.css') }}">
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'Article',
            'headline' => $headline,
            'description' => $description,
            'mainEntityOfPage' => route('guides.moderateur'),
            'inLanguage' => 'fr-FR',
            'image' => versioned_asset($guide['image'] ?? 'images/hero.webp'),
            'datePublished' => $guide['published'],
            'dateModified' => $guide['updated'],
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
                ['@@type' => 'ListItem', 'position' => 3, 'name' => 'Modérateur de son', 'item' => route('guides.moderateur')],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('content')
    <div class="container glab glab--moderateur">
        @include('guides.partials.hero', [
            'crumb' => 'Modérateur de son',
            'kicker' => 'Audition',
            'title' => $headline,
            'lede' => 'Le mot « silencieux » promet ce que l\'objet ne tient pas. Un modérateur de son retire de l\'ordre de 20 décibels à un coup de feu qui en fait 150 à 165 : assez pour réduire nettement le risque, pas assez pour vous passer de protection. Ce guide dit ce que la loi française permet, ce que l\'appareil retire vraiment, où commencent les dégâts, et estime le niveau à l\'oreille selon votre arme et votre protection.',
            'tags' => ['R312-45-2', '140 dB(C)', 'SNR'],
        ])

        <nav class="glab-plan" aria-label="Plan du guide">
            <p class="glab-plan-kicker">Plan</p>
            <div class="glab-plan-links">
                <a href="#mod-quoi-title">Modérateur ou silencieux</a>
                <a href="#mod-calc-title">Le niveau à l'oreille</a>
                <a href="#mod-db-title">Les décibels retirés</a>
                <a href="#mod-table-title">Arme par arme</a>
                <a href="#mod-loi-title">La loi</a>
                <a href="#mod-seuils-title">Les seuils</a>
                <a href="#mod-protection-title">Se protéger</a>
                <a href="#mod-filetage-title">Filetages</a>
                <a href="#mod-idees-title">Idées reçues</a>
                <a href="#mod-faq-title">Questions</a>
                <a href="#mod-sources-title">Sources</a>
            </div>
        </nav>

        <section class="glab-section" aria-labelledby="mod-quoi-title">
            <h2 class="glab-title" id="mod-quoi-title">Modérateur ou silencieux : <span class="glab-title-accent">quelle différence ?</span></h2>
            <p class="glab-lede glab-lede--thesis">
                Un modérateur ne rend pas un tir silencieux. Il le rend moins dangereux pour les
                oreilles, sans le rendre inoffensif.
            </p>

            <div class="glab-units">
                <article class="glab-unit">
                    <p class="glab-unit-kicker">Au cinéma</p>
                    <h3>Silencieux</h3>
                    <p class="glab-unit-read">Un mot trompeur</p>
                    <p>
                        Un « pfft » discret et plus rien. Dans la réalité, le coup reste très sonore :
                        la Fédération des chasseurs de Haute-Garonne compare le résultat au bruit d'un
                        marteau-piqueur en marche.
                    </p>
                </article>
                <article class="glab-unit is-key">
                    <p class="glab-unit-kicker">Dans les textes</p>
                    <h3>Réducteur de son</h3>
                    <p class="glab-unit-read">Un appareil qui atténue</p>
                    <p>
                        Le Code de la sécurité intérieure parle de « réducteur de son », les chasseurs
                        de « modérateur ». Vissé en bout de canon, il atténue la détonation, pas le claquement d'une balle supersonique,
                        qui naît plus loin sur sa trajectoire.
                    </p>
                </article>
            </div>
        </section>

        {{-- The calculator. Hidden until the script runs: the table in the
             next parts gives the same model's answers to a reader without
             JavaScript, so nothing here promises a control that cannot work. --}}
        <section class="glab-section" data-glab-moderateur hidden aria-labelledby="mod-calc-title">
            <h2 class="glab-title" id="mod-calc-title">Combien de décibels <span class="glab-title-accent">à l'oreille ?</span></h2>
            <p class="glab-lede">
                Votre arme, le modérateur, votre protection. La page estime le niveau de crête qui
                atteint l'oreille, en ordre de grandeur, et le compare aux seuils du Code du travail.
            </p>

            <div class="glab-calc-grid">
                <div class="glab-calc-fields">
                    @foreach ([['name' => 'arme', 'legend' => 'Votre arme', 'items' => $arms, 'active' => 'courante'], ['name' => 'moderateur', 'legend' => 'Le modérateur', 'items' => $moderators, 'active' => 'avec'], ['name' => 'protection', 'legend' => 'Votre protection', 'items' => $protections, 'active' => 'aucune']] as $group)
                        <fieldset class="glab-calc-field">
                            <legend>{{ $group['legend'] }}</legend>
                            <div class="glab-chips" data-glab-db-group="{{ $group['name'] }}">
                                @foreach ($group['items'] as $item)
                                    <button
                                        type="button"
                                        class="{{ $item['key'] === $group['active'] ? 'is-active' : '' }}"
                                        aria-pressed="{{ $item['key'] === $group['active'] ? 'true' : 'false' }}"
                                        data-glab-db-value="{{ $item['key'] }}"
                                        @isset($item['peak']) data-peak="{{ implode(' ', $item['peak']) }}" @endisset
                                        @isset($item['energy']) data-energy="{{ implode(' ', $item['energy']) }}" @endisset
                                        @if (array_key_exists('extra', $item)) data-extra="{{ $item['extra'] === null ? 'none' : implode(' ', $item['extra']) }}" @endif
                                    >
                                        {{ $item['label'] }}
                                        <small>{{ $item['hint'] }}</small>
                                    </button>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach

                    <div class="glab-calc-field" data-glab-db-snr-field hidden>
                        <label for="mod-snr">SNR indiqué sur le protecteur</label>
                        <select id="mod-snr" data-glab-db-snr>
                            @foreach ($snrs as $snr)
                                <option value="{{ $snr }}" @selected($snr === 30)>SNR {{ $snr }} dB</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <output
                    class="glab-calc-result"
                    data-glab-db-result
                    data-under="Sous 135 dB(C), la plus basse des valeurs d'action du Code du travail."
                    data-straddle="À cheval sur les seuils de 135 à 140 dB(C) : en dessous ou au-dessus selon l'arme et la munition."
                    data-over="Au-dessus de 140 dB(C), la valeur limite de crête du Code du travail, à chaque coup."
                    data-reference="Référence : oreilles nues, sans modérateur."
                >
                    <span class="glab-calc-energy"><strong data-glab-db-peak>{{ $default[0] }} à {{ $default[1] }}</strong> dB de crête à l'oreille</span>
                    <span class="glab-calc-equiv" data-glab-db-dose>À dose sonore égale : ×8 à ×130 coups par rapport aux oreilles nues sans modérateur.</span>
                    <span class="glab-calc-band" data-glab-db-verdict>À cheval sur les seuils de 135 à 140 dB(C) : en dessous ou au-dessus selon l'arme et la munition.</span>
                </output>
            </div>

            {{-- The signature of the page: the estimate is a band, not a
                 needle, because the measurements give a range. The three
                 peak thresholds sit within five decibels of each other, and
                 one brace names them, as the joules scale names its caps. --}}
            <figure class="glab-scale glab-db" data-glab-db-scale style="--glab-db-from: {{ $axis($default[0]) }}%; --glab-db-to: {{ $axis($default[1]) }}%">
                <div class="glab-scale-marks" aria-hidden="true">
                    @foreach ([80, 100, 120, 140, 160, 180] as $mark)
                        <span style="--glab-mark-at: {{ $axis($mark) }}%">{{ $mark }} dB</span>
                    @endforeach
                </div>
                <div class="glab-scale-track">
                    <span class="glab-scale-zone is-free" style="--glab-zone-width: 55%"></span>
                    <span class="glab-scale-zone is-d" style="--glab-zone-width: 5%"></span>
                    <span class="glab-scale-zone is-c" style="--glab-zone-width: 40%"></span>
                    <span class="glab-db-band" data-glab-db-band></span>
                </div>
                <div class="glab-scale-caps" style="--glab-caps-from: 55%; --glab-caps-to: 60%; --glab-caps-mid: 57.5%">
                    <span class="glab-scale-caps-brace" aria-hidden="true"></span>
                    <span class="glab-scale-caps-label">Seuils de crête, 135 à 140 dB(C)</span>
                </div>
                <figcaption class="glab-db-refs">Repères : 80 dB, 40 heures par semaine sans risque selon l'OMS ; 144 dB, une carabine .22 mesurée par le NIOSH ; 172 dB, un revolver .357.</figcaption>
            </figure>

            <div class="glab-calc-limits">
                <h3>Ce que le calcul fait, et ce qu'il ne fait pas</h3>
                <p class="glab-calc-note">
                    Il part du niveau de crête publié pour l'arme, retire la réduction mesurée à
                    l'oreille par le NIOSH, puis le SNR, l'atténuation indiquée sur le protecteur. La ligne
                    « à dose sonore égale » applique
                    la réduction d'énergie mesurée sur huit heures et la règle des 3 dB : 3 dB de moins,
                    c'est deux fois plus de coups pour la même dose. « Les plus bruyantes » couvre le
                    haut de la fourchette publiée, de 165 à 175 dB. Ce sont des ordres de grandeur,
                    plutôt prudents : à l'oreille du tireur, en plein air, le NIOSH a mesuré 131 à 137 dB
                    avec modérateur sur des carabines .223 et .308, et 118 à 128 dB sur des armes .22 LR.
                    Dans un stand couvert ou un poste fermé, les murs renvoient le bruit et le risque
                    augmente ; sur une arme semi-automatique, les gaz de l'éjection réduisent le gain.
                    Le SNR ne donne qu'une estimation rapide : sur des coups de feu, des bouchons n'ont
                    retiré que 10 à 30 dB de crête, et un protecteur mal mis en place protège moins.
                    La dose ne dit pas tout : chaque coup au-delà de 140 dB reste à éviter, quel que
                    soit leur nombre. Les seuils du Code du travail sont écrits pour les
                    salariés : ils servent ici de repère, pas de permis.
                </p>
            </div>
        </section>

        <section class="glab-section" aria-labelledby="mod-db-title">
            <h2 class="glab-title" id="mod-db-title">Combien de décibels <span class="glab-title-accent">retire un modérateur de son ?</span></h2>
            <p class="glab-lede">
                Le NIOSH, l'institut américain de santé et de sécurité au travail, a mesuré trois
                modérateurs sur quatre armes, à l'oreille du tireur et à la place d'un moniteur, derrière lui.
            </p>

            <dl class="glab-specs">
                <div>
                    <dt>17 à 24 dB <em>à l'oreille</em></dt>
                    <dd>La baisse du niveau de crête pour le tireur, selon l'arme et la munition.</dd>
                </div>
                <div>
                    <dt>9 à 21 dB <em>sur la journée</em></dt>
                    <dd>La baisse de l'énergie sonore reçue, ramenée à huit heures : c'est elle qui fait la dose.</dd>
                </div>
                <div>
                    <dt>20 à 28 dB <em>derrière</em></dt>
                    <dd>La baisse de crête à environ un mètre derrière le tireur, là où se tient un moniteur.</dd>
                </div>
            </dl>

            <div class="glab-square">
                <article>
                    <p class="glab-square-kicker">3 dB de plus</p>
                    <p class="glab-square-result">× 2 l'énergie</p>
                </article>
                <article class="is-key">
                    <p class="glab-square-kicker">20 dB de moins</p>
                    <p class="glab-square-result">÷ 100 l'énergie</p>
                </article>
            </div>
            <p class="glab-square-note">
                L'échelle des décibels est logarithmique. Vingt décibels paraissent peu sur 160 ; ils
                représentent pourtant cent fois moins d'énergie dans l'oreille. Et cent fois moins
                qu'un coup de feu, c'est encore beaucoup.
            </p>

            <div class="glab-prose">
                <h3>Pourquoi un tir avec modérateur reste bruyant</h3>
                <p>
                    Les armes de loisir produisent de 140 à 175 dB de crête, et la plupart, hors
                    petites carabines .17 et .22 et armes à air, entre 150 et 165 dB. Le NIOSH a
                    mesuré 144 dB pour une carabine .22 et 172 dB pour un revolver .357. Retirez 17 à
                    24 dB : le coup arrive encore autour des seuils de 135 à 140 dB(C), en dessous ou
                    au-dessus selon l'arme. À l'oreille, le NIOSH a relevé jusqu'à 137 dB avec
                    modérateur sur une carabine .308.
                </p>
                <p>
                    Le modérateur agit sur la détonation à la bouche. Une balle supersonique produit
                    en plus sa propre onde de choc tout au long de sa course, que rien au bout du canon
                    ne peut retenir : les mesures du NIOSH montrent un gain plus grand avec des munitions
                    lentes qu'avec les mêmes armes en munitions rapides. Les munitions de chasse, elles,
                    restent supersoniques.
                </p>
            </div>
        </section>

        <section class="glab-section" aria-labelledby="mod-table-title">
            <h2 class="glab-title" id="mod-table-title">Les ordres de grandeur, <span class="glab-title-accent">arme par arme</span></h2>
            <p class="glab-lede">
                Le même modèle que le calcul, sans rien à régler. Les cases teintées atteignent
                135 dB(C) dans le pire des cas ; les cases pleines atteignent 140 dB(C) même dans le meilleur.
            </p>

            <ul class="glab-table-key">
                <li><i class="is-near"></i> atteint 135 dB(C)</li>
                <li><i class="is-over"></i> 140 dB(C) ou plus</li>
            </ul>

            <div class="glab-table-wrap">
                <table class="glab-table">
                    <caption>Niveau de crête estimé à l'oreille du tireur, en dB. Modérateur : 17 à 24 dB de moins ; double protection : 5 à 10 dB de plus que le meilleur protecteur seul.</caption>
                    <thead>
                        <tr>
                            <th scope="col">Arme</th>
                            @foreach ($columns as $column)
                                <th scope="col">{{ $column['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($arms as $arm)
                            <tr>
                                <th scope="row">{{ $arm['label'] }}</th>
                                @foreach ($columns as $column)
                                    @php($cell = $atEar($arm, $moderators[$column['moderator']], $protections[$column['protection']]['extra'], 30))
                                    <td class="{{ $band($cell) }}">{{ $range($cell) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="glab-section" aria-labelledby="mod-loi-title">
            <h2 class="glab-title" id="mod-loi-title">Le modérateur de son est-il <span class="glab-title-accent">légal en France ?</span></h2>
            <p class="glab-lede glab-lede--thesis">
                Oui, mais pas en vente libre : l'objet n'est pas une arme, et pourtant son achat
                est réservé aux chasseurs, aux tireurs licenciés et aux collectionneurs.
            </p>

            <dl class="glab-specs glab-place-cards">
                <div>
                    <dt>Pas une arme <span class="glab-spec-kicker">Article R311-1, IV</span></dt>
                    <dd>« Ne sont pas des armes » au sens du Code « les réducteurs de son constituant des
                        pièces additionnelles ne modifiant pas le fonctionnement de l'arme ». L'article
                        R311-2, qui dresse les catégories A à D, ne les cite nulle part.</dd>
                </div>
                <div>
                    <dt>Un achat sous conditions <span class="glab-spec-kicker">Article R312-45-2, depuis le 1er août 2018</span></dt>
                    <dd>« Nul ne peut acquérir un réducteur de son sans présentation d'un des titres
                        mentionnés à l'article R. 312-53 ainsi que du titre de détention de l'arme
                        correspondante » : permis de chasser validé, licence de tir, de ball-trap ou de
                        biathlon, ou carte de collectionneur.</dd>
                </div>
                <div>
                    <dt>Autorisé à la chasse <span class="glab-spec-kicker">Arrêté du 2 janvier 2018</span></dt>
                    <dd>Publié au Journal officiel du 23 janvier 2018, il retire de l'arrêté du 1er août
                        1986 l'interdiction d'employer à la chasse « tout dispositif silencieux destiné à
                        atténuer le bruit au départ du coup ».</dd>
                </div>
            </dl>

            <div class="glab-prose">
                <h3>Et sur une carabine à plombs ?</h3>
                <p>
                    C'est le point où nous préférons dire ce que nous ne savons pas. L'article
                    R312-45-2 ne prévoit pas de cas à part pour les armes de catégorie D, qui se
                    détiennent sans titre, alors qu'il demande le titre de détention de l'arme. Nous
                    n'avons pas trouvé de texte officiel qui tranche. Demandez au vendeur ce qu'il
                    exigera, et présentez votre permis ou votre licence si vous en avez.
                </p>
            </div>

            <p class="glab-more-reading">
                Les catégories elles-mêmes sont détaillées dans <a href="{{ route('guides.classification') }}">Classer son arme</a>,
                et le trajet jusqu'au stand dans <a href="{{ route('guides.transport') }}">Transporter son arme</a>.
            </p>
        </section>

        <section class="glab-section" aria-labelledby="mod-seuils-title">
            <h2 class="glab-title" id="mod-seuils-title">À partir de combien de décibels <span class="glab-title-accent">l'audition est-elle en danger ?</span></h2>
            <p class="glab-lede">
                Deux mesures, deux dangers : l'énergie reçue sur la journée, en dB(A), et le pic d'un
                seul bruit, en dB(C). Un coup de feu est d'abord un pic.
            </p>

            <dl class="glab-specs">
                <div>
                    <dt>80 dB(A) · 135 dB(C) <em>premier seuil</em></dt>
                    <dd>La valeur d'action inférieure de l'article R4431-2 du Code du travail, sur huit heures ou en crête.</dd>
                </div>
                <div>
                    <dt>85 dB(A) · 137 dB(C) <em>second seuil</em></dt>
                    <dd>La valeur d'action supérieure. Ni l'une ni l'autre ne tient compte des protecteurs portés.</dd>
                </div>
                <div>
                    <dt>87 dB(A) · 140 dB(C) <em>valeur limite</em></dt>
                    <dd>La seule qui se calcule en tenant compte des protecteurs. Le NIOSH retient le même plafond de 140 dB pour un bruit impulsionnel.</dd>
                </div>
            </dl>

            <ul class="glab-takeaways">
                <li><span class="glab-takeaway-label">8 heures</span><span>80 dB(A)</span></li>
                <li><span class="glab-takeaway-label">4 heures</span><span>83 dB(A)</span></li>
                <li><span class="glab-takeaway-label">2 heures</span><span>86 dB(A)</span></li>
                <li><span class="glab-takeaway-label">1 heure</span><span>89 dB(A), la même dose que 80 dB(A) pendant 8 heures, selon l'INRS</span></li>
            </ul>

            <div class="glab-prose">
                <p>
                    C'est la règle des 3 dB : chaque fois que le niveau monte de 3 dB, le temps
                    d'exposition doit être divisé par deux pour garder la même dose. L'OMS la décline
                    pour l'écoute de loisir : 80 dB sont sans risque 40 heures par semaine, 90 dB
                    seulement 4 heures par semaine. Les lésions des cellules sensorielles de l'oreille sont
                    irréversibles, et la perte auditive passe souvent inaperçue.
                </p>
            </div>
        </section>

        <section class="glab-section" aria-labelledby="mod-protection-title">
            <h2 class="glab-title" id="mod-protection-title">Bouchons, casque ou les deux : <span class="glab-title-accent">quelle protection auditive pour le tir ?</span></h2>
            <p class="glab-lede">
                Le NIOSH recommande aux tireurs et aux chasseurs une double protection, bouchons et
                casque, à chaque tir. Voici ce que valent les chiffres des emballages.
            </p>

            <ol class="glab-rules">
                <li>
                    <h3>Lire le SNR sans le prendre au pied de la lettre</h3>
                    <p>
                        Le SNR est un indice global d'atténuation, en décibels, conçu pour une estimation
                        rapide. Dans le commerce, il plafonne vers 32 à 35 dB. Sur des coups de feu, le
                        NIOSH a mesuré une baisse de crête de 10 à 30 dB avec des bouchons, de 20 à 38 dB
                        avec un casque. Sur le terrain, la protection dépend d'abord de la mise en place
                        et du temps de port : un protecteur confortable est un protecteur qu'on garde.
                    </p>
                </li>
                <li>
                    <h3>Ne pas l'enlever entre deux séries</h3>
                    <p>
                        Retirer ses protecteurs trente minutes sur huit heures fait chuter leur efficacité
                        réelle de plus de moitié. Au stand, les coups des voisins ne s'arrêtent pas
                        pendant que vous rechargez.
                    </p>
                </li>
                <li>
                    <h3>Doubler, sans additionner</h3>
                    <p>
                        Des bouchons SNR 30 sous un casque SNR 30 ne font pas 60. Le second protecteur
                        ajoute de l'ordre de 5 à 10 dB au meilleur des deux. C'est peu sur le papier, mais
                        c'est trois à dix fois moins d'énergie. Pour les armes puissantes en tir sur
                        cible, les chercheurs du NIOSH ont jugé en 2018 la double protection justifiée
                        sans modérateur, et une protection simple encore nécessaire avec un modérateur.
                    </p>
                </li>
                <li>
                    <h3>Écouter ses oreilles</h3>
                    <p>
                        Un sifflement ou une sensation d'oreille pleine suit souvent un tir sans
                        protection ou mal protégé, note le NIOSH. Prenez-le comme un avertissement : la
                        prochaine fois, protégez-vous mieux.
                    </p>
                </li>
            </ol>

            <p class="glab-warning">
                <strong class="glab-warning-label">Acouphènes ou baisse d'audition après un tir</strong>
                Consultez un médecin généraliste ou un ORL dans les 24 heures : un traumatisme sonore
                aigu se soigne d'autant mieux qu'il est pris tôt.
            </p>

            <p class="glab-more-reading">
                Ce qu'on vous prête et ce qu'on vous demande à la première visite :
                <a href="{{ route('guides.premiere-seance') }}">Votre première séance au stand</a>.
            </p>
        </section>

        <section class="glab-section" aria-labelledby="mod-filetage-title">
            <h2 class="glab-title" id="mod-filetage-title">Quel filetage <span class="glab-title-accent">pour un modérateur ?</span></h2>
            <p class="glab-lede">
                Un modérateur se visse sur le canon. Encore faut-il que les deux filetages parlent la
                même langue.
            </p>

            <dl class="glab-specs">
                <div>
                    <dt>1/2 UNF <em>1/2x20</em></dt>
                    <dd>Le filetage standard en Europe sur les armes de calibre .22 LR, qu'on retrouve aussi sur des carabines à percussion centrale.</dd>
                </div>
                <div>
                    <dt>M14x1 <em>M14x100</em></dt>
                    <dd>Un filetage métrique au pas de 1 mm. Avec un adaptateur, il se monte sur un canon fileté en 1/2 UNF.</dd>
                </div>
                <div>
                    <dt>M18x1 <em>métrique</em></dt>
                    <dd>Un autre filetage métrique au pas de 1 mm, plus large. Vérifiez qu'un adaptateur existe pour votre canon avant d'acheter.</dd>
                </div>
            </dl>

            <div class="glab-prose">
                <p>
                    L'adaptateur se visse en bout de canon par son filetage femelle et offre un
                    filetage mâle à l'accessoire. Vérifiez avant tout que son alésage laisse passer le
                    projectile de votre calibre. Vissez et dévissez toujours arme déchargée, chambre
                    ouverte : un <a href="{{ route('categories.show', 'temoin-de-chambre-vide') }}">témoin de chambre vide</a>
                    le montre à tout le monde.
                </p>
                <p>
                    La boutique ne vend pas de modérateur de son. Elle propose des housses en nylon à
                    cordons élastiques, en noir, vert, tan et camouflage, qui protègent le modérateur
                    des frottements et le fondent dans l'équipement.
                </p>
            </div>
        </section>

        <section class="glab-section" aria-labelledby="mod-idees-title">
            <h2 class="glab-title" id="mod-idees-title">Quatre idées <span class="glab-title-accent">qui coûtent des décibels</span></h2>
            <p class="glab-lede">Elles se répètent au stand et en battue. Aucune ne résiste aux mesures ni aux textes.</p>

            <div class="glab-myths">
                @foreach ([
                    ['« Avec un silencieux, on n\'entend plus le coup. »', 'De l\'ordre de 20 dB en moins sur 150 à 165 : le coup reste très sonore, d\'autant que les munitions de chasse sont supersoniques.'],
                    ['« Avec un modérateur, les bouchons ne servent plus. »', 'Les chercheurs du NIOSH concluent l\'inverse : les expositions répétées restent un risque, et il faut une protection auditive à chaque tir.'],
                    ['« Bouchons SNR 30 et casque SNR 30, ça fait 60. »', 'Le second protecteur ajoute de l\'ordre de 5 à 10 dB au meilleur des deux, jamais la somme.'],
                    ['« Le silencieux est interdit en France. »', 'Ce n\'est pas une arme. Il est autorisé à la chasse depuis 2018 et s\'achète sur présentation d\'un permis, d\'une licence ou d\'une carte de collectionneur, ainsi que du titre de détention de l\'arme.'],
                ] as [$claim, $truth])
                    <article class="glab-myth">
                        <p class="glab-myth-flag">On entend</p>
                        <p class="glab-myth-claim">{{ $claim }}</p>
                        <p class="glab-myth-flag glab-myth-flag--truth">En réalité</p>
                        <p class="glab-myth-truth">{{ $truth }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="glab-faq" aria-labelledby="mod-faq-title">
            <h2 class="glab-title" id="mod-faq-title">Questions <span class="glab-title-accent">fréquentes</span></h2>
            @foreach ($faq as $qa)
                <details>
                    <summary>{{ $qa[0] }}</summary>
                    <div>
                        <p>{{ $qa[1] }}</p>
                    </div>
                </details>
            @endforeach
        </section>

        <section class="glab-section" aria-labelledby="mod-sources-title">
            <h2 class="glab-title" id="mod-sources-title">Les <span class="glab-title-accent">sources</span></h2>
            <p class="glab-lede">
                Les textes sur Légifrance, les mesures publiées par les chercheurs du NIOSH, et les
                repères de santé de l'INRS et de l'OMS. Vérifiez : cette page ne demande pas d'être
                crue sur parole.
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
            <a href="{{ route('categories.show', 'accessoires-silencieux-moderateurs') }}" class="btn btn-primary">Voir les housses de modérateur</a>
            <a href="{{ route('guides.index') }}" class="btn btn-secondary">Tous les guides</a>
        </p>
    </div>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/guides.js') }}" defer></script>
@endpush
