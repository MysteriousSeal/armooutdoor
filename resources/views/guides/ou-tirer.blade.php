@extends('layouts.app')

@php
    $faq = [
        ['Peut-on tirer à la carabine à plomb dans son jardin ?', 'Aucun texte ne l\'autorise et aucun ne l\'interdit expressément. Ce qui décide, c\'est le droit commun : le projectile ne doit menacer personne, le tir ne doit pas être dirigé vers une habitation, une route ou un chemin, le bruit ne doit pas nuire au voisinage, et aucun arrêté municipal ou préfectoral ne doit s\'y opposer. Réunies, ces conditions sont exigeantes ; un jardin de ville les réunit rarement.'],
        ['L\'article R. 312-40 autorise-t-il le tir à domicile ?', 'Non, et c\'est la confusion la plus répandue. Cet article du code de la sécurité intérieure traite du tir sportif : qui peut être autorisé à acquérir des armes de catégorie B pour la compétition, les clubs, les tireurs licenciés, les mineurs dès douze ans pour le pistolet à un coup. Il précise même que ces armes ne peuvent être utilisées que dans les stands des associations concernées. Il ne dit rien du jardin.'],
        ['Faut-il se tenir à 150 mètres des habitations ?', 'Ces 150 mètres ne sont pas une distance de sécurité. Ils viennent de l\'article L. 422-10 du code de l\'environnement, qui exclut du territoire d\'une association communale de chasse agréée les terrains situés dans un rayon de 150 mètres autour d\'une habitation. C\'est une règle sur les terrains où la chasse peut s\'exercer, pas sur la distance à laquelle on peut tirer. La règle de sécurité, elle, porte sur la direction du tir.'],
        ['Faut-il déclarer un terrain d\'airsoft en mairie ?', 'La Fédération française d\'airsoft ne mentionne que l\'autorisation du propriétaire et un contrat écrit. D\'autres sources évoquent une démarche en préfecture pour organiser une partie. Les pratiques varient d\'un département à l\'autre : la mairie et la préfecture du lieu sont les seules à pouvoir répondre pour un terrain donné.'],
        ['Un mineur peut-il tirer ?', 'En stand, oui : le tir sportif encadré accueille les mineurs, et la réglementation prévoit même l\'accès au pistolet à un coup de calibre 22 dès douze ans, dans le cadre d\'un club. Ailleurs, la vente d\'une réplique est interdite aux mineurs dès 0,08 joule, et la surveillance d\'un adulte ne remplace pas les conditions de sécurité du lieu.'],
    ];

    $sources = [
        ['label' => 'Code de la sécurité intérieure, articles R. 312-40 à R. 312-43-1 (tir sportif)', 'url' => 'https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000025503132/LEGISCTA000029655171/2023-03-29', 'host' => 'legifrance.gouv.fr'],
        ['label' => 'Code de la santé publique, article R. 1336-5 (bruits de voisinage)', 'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000035425967', 'host' => 'legifrance.gouv.fr'],
        ['label' => 'Code général des collectivités territoriales, article L. 2212-2 (pouvoirs de police du maire)', 'url' => 'https://www.legifrance.gouv.fr/codes/id/LEGISCTA000006164555', 'host' => 'legifrance.gouv.fr'],
        ['label' => 'La chasse à proximité des habitations, et ce que valent les 150 mètres', 'url' => 'http://www.oncfs.gouv.fr/Fiches-juridiques-chasse-ru377/La-chasse-a-proximite-des-habitations-ar1035', 'host' => 'oncfs.gouv.fr'],
        ['label' => 'À quelle distance des habitations doivent se trouver les chasseurs ?', 'url' => 'https://faq.gendarmerie.interieur.gouv.fr/fr-FR/Post/2453', 'host' => 'gendarmerie.interieur.gouv.fr'],
        ['label' => 'Comment légaliser l\'utilisation d\'un terrain', 'url' => 'https://ffairsoft.org/ufaqs/comment-legaliser-lutilisation-dun-terrain/', 'host' => 'ffairsoft.org'],
    ];
@endphp

@section('title', 'Où tirer légalement en France : jardin, terrain, stand — Armo Outdoor')
@section('meta_description', 'Chez soi, sur un terrain d\'airsoft ou en stand homologué : ce qui décide vraiment du lieu où l\'on peut tirer, la direction plutôt que la distance, et les deux textes que tout le monde cite de travers.')
@section('og_type', 'article')
@section('canonical', route('guides.ou-tirer'))

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/categories.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('css/guides/guides.css') }}">
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'Article',
            'headline' => 'Où tirer légalement en France : jardin, terrain, stand',
            'description' => 'Chez soi, sur un terrain d\'airsoft ou en stand homologué : ce qui décide vraiment du lieu où l\'on peut tirer, la direction plutôt que la distance, et les deux textes que tout le monde cite de travers.',
            'mainEntityOfPage' => route('guides.ou-tirer'),
            'inLanguage' => 'fr-FR',
            'datePublished' => '2026-09-07',
            'dateModified' => '2026-09-07',
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
                ['@@type' => 'ListItem', 'position' => 3, 'name' => 'Où tirer légalement', 'item' => route('guides.ou-tirer')],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('content')
    <div class="container glab">
        @include('guides.partials.hero', [
            'crumb' => 'Où tirer légalement',
            'kicker' => 'Lieux',
            'title' => 'Où tirer légalement',
            'lede' => 'Trois lieux, et une seule question derrière les trois : qu\'y a-t-il derrière la cible. Ce guide dit ce qui décide vraiment, et corrige les deux textes que le web recopie de travers.',
            'tags' => ['Chez soi', 'Terrain', 'Stand'],
        ])

        <p class="glab-warning">
            Ceci décrit l'état du droit à la date de publication et n'est pas un conseil
            juridique. Pour un lieu précis, la mairie et la préfecture du département sont
            les interlocutrices compétentes : les arrêtés locaux priment sur ce que dit une
            page générale.
        </p>

        <section class="glab-section" aria-labelledby="ou-question-title">
            <h2 class="glab-title" id="ou-question-title">La seule question <span class="glab-title-accent">qui compte</span></h2>
            <p class="glab-lede">
                Ce n'est pas « à quelle distance », c'est « dans quelle direction, et qu'y a-t-il
                derrière ».
            </p>

            @include('guides.partials.danger-zone')

            <div class="glab-prose">
                <p>
                    Un projectile ne s'arrête pas parce qu'il a touché la cible. Il la traverse,
                    il la manque, il ricoche. La zone qui compte n'est donc pas le trajet jusqu'à
                    la cible mais le cône qui continue derrière elle, et ce cône ne connaît ni les
                    clôtures ni les limites cadastrales.
                </p>
                <p>
                    De là découle tout le reste. Un point d'arrêt referme le cône : une butte de
                    terre, un caisson garni, un piège à plombs derrière la cible. Sans lui, la
                    question n'est plus « ai-je le droit » mais « où finit ce que je tire », et
                    aucune réponse rassurante n'existe.
                </p>
            </div>
        </section>

        <section class="glab-panel" aria-labelledby="ou-places-title">
            <h2 class="glab-title" id="ou-places-title">Trois lieux, <span class="glab-title-accent">trois régimes</span></h2>
            <p class="glab-lede">
                Ce qui change d'un lieu à l'autre, ce n'est pas la physique : c'est qui répond de
                la sécurité, et devant qui.
            </p>

            <dl class="glab-specs">
                <div>
                    <dt>Chez soi <em>rien ne l'autorise, rien ne l'interdit</em></dt>
                    <dd>Aucun texte ne vise le tir de loisir sur un terrain privé. C'est le droit commun qui décide : sécurité, direction du tir, bruit, arrêtés locaux. Vous répondez de tout, seul.</dd>
                </div>
                <div>
                    <dt>Sur un terrain <em>l'accord du propriétaire</em></dt>
                    <dd>Un terrain d'airsoft se prête ou se loue, et l'association qui l'exploite porte le cadre et l'assurance des joueurs. Le propriétaire donne son accord, de préférence écrit.</dd>
                </div>
                <div>
                    <dt>En stand <em>le seul lieu prévu pour ça</em></dt>
                    <dd>Un stand homologué est construit autour du point d'arrêt et tenu par une association agréée. C'est le seul endroit où les armes de catégorie B acquises pour le tir sportif peuvent servir.</dd>
                </div>
            </dl>
        </section>

        <section class="glab-section" aria-labelledby="ou-home-title">
            <h2 class="glab-title" id="ou-home-title">Chez soi : <span class="glab-title-accent">quatre conditions, pas une permission</span></h2>
            <p class="glab-lede">
                Le silence des textes n'est pas une autorisation. Il renvoie à quatre règles
                générales, et il suffit qu'une seule manque.
            </p>

            <div class="glab-prose">
                <h3>La direction, et le point d'arrêt</h3>
                <p>
                    Les arrêtés préfectoraux types interdisent de tirer <em>en direction</em> des
                    habitations, des routes, des chemins, des lieux et installations publics. La
                    formulation vise la direction, pas une distance : un tir dirigé vers une maison
                    reste fautif à trois cents mètres, et un tir dirigé vers une butte de terre ne
                    l'est pas à dix. Ces arrêtés se consultent en mairie.
                </p>

                <h3>Le bruit</h3>
                <p>
                    L'article R. 1336-5 du code de la santé publique interdit qu'un bruit nuise,
                    par sa durée, sa répétition ou son intensité, à la tranquillité du voisinage,
                    <em>en lieu public comme en lieu privé</em>. Un seul voisin gêné suffit à
                    caractériser la nuisance. Une séance de plinking un dimanche après-midi coche
                    la durée et la répétition sans effort.
                </p>

                <h3>L'arrêté municipal</h3>
                <p>
                    Le maire tient de l'article L. 2212-2 du code général des collectivités
                    territoriales une police générale de la sûreté et de la tranquillité. Il peut,
                    lorsqu'un risque particulier le justifie, restreindre ou interdire le tir sur
                    tout ou partie de la commune. L'interdiction générale et absolue, elle, lui est
                    fermée : il faut des circonstances. C'est encore en mairie que cela se vérifie.
                </p>

                <h3>Ce que vous engagez</h3>
                <p>
                    Votre responsabilité civile couvre les dommages que vous causez, à condition
                    que votre contrat ne l'exclue pas : le tir à domicile fait partie des activités
                    que certains assureurs traitent à part, et cela se demande avant, pas après. Un
                    blessé fait basculer l'affaire sur le terrain pénal, où l'absence de point
                    d'arrêt se lit comme une imprudence caractérisée.
                </p>
            </div>
        </section>

        <section class="glab-panel" aria-labelledby="ou-myths-title">
            <h2 class="glab-title" id="ou-myths-title">Trois choses <span class="glab-title-accent">que le web répète</span></h2>
            <p class="glab-lede">
                Elles sont fausses, et elles sont partout, y compris sous la plume d'armureries.
            </p>

            <div class="glab-myths">
                <article class="glab-myth">
                    <p class="glab-myth-claim">« L'article R. 312-40 autorise le tir chez soi. »</p>
                    <p class="glab-myth-truth">
                        Il traite du tir sportif : qui peut être autorisé à acquérir des armes de
                        catégorie B, les clubs, les compétiteurs, les mineurs dès douze ans pour le
                        pistolet à un coup. Il ajoute que ces armes ne servent que dans les stands
                        des associations. Rien sur le jardin.
                    </p>
                </article>
                <article class="glab-myth">
                    <p class="glab-myth-claim">« Il faut être à 150 mètres des habitations. »</p>
                    <p class="glab-myth-truth">
                        Ces 150 mètres sortent de l'article L. 422-10 du code de l'environnement,
                        qui retire du territoire d'une chasse communale agréée les terrains situés
                        dans ce rayon autour d'une habitation. C'est une règle de territoire, pas
                        une distance de sécurité.
                    </p>
                </article>
                <article class="glab-myth">
                    <p class="glab-myth-claim">« C'est chez moi, donc je fais ce que je veux. »</p>
                    <p class="glab-myth-truth">
                        La propriété ne suspend ni le code de la santé publique, ni la police du
                        maire, ni votre responsabilité. Elle règle une seule question, celle de
                        l'accord du propriétaire, et c'est la plus facile des quatre.
                    </p>
                </article>
            </div>
        </section>

        <section class="glab-section" aria-labelledby="ou-terrain-title">
            <h2 class="glab-title" id="ou-terrain-title">Sur un terrain <span class="glab-title-accent">d'airsoft</span></h2>
            <p class="glab-lede">
                Le terrain ne s'improvise pas dans un bois qui appartient à quelqu'un d'autre.
            </p>

            <div class="glab-prose">
                <p>
                    Le point de départ est l'accord du propriétaire ou de son mandataire. Un accord
                    oral vaut juridiquement, mais la Fédération française d'airsoft recommande
                    l'écrit et publie pour cela trois modèles : le prêt de terrain, le bail à un
                    euro symbolique, et le bail de location ordinaire. L'écrit protège le club
                    autant que le propriétaire, et c'est lui qu'on présente quand on vous demande
                    ce que vous faites là.
                </p>
                <p>
                    L'association loi 1901 porte le reste : elle donne un cadre à la pratique et
                    assure ses membres. Aucune obligation nationale n'impose cette assurance, mais
                    la quasi-totalité des terrains l'exigent, et une partie sans assurance est une
                    partie où chacun répond de lui-même. Sur place, la fédération recommande de
                    retirer les dangers du terrain et de signaler par panneau qu'une partie est en
                    cours : un promeneur qui traverse un terrain d'airsoft sans le savoir est le
                    scénario que tout le monde redoute.
                </p>
                <p>
                    Reste la question administrative, sur laquelle les sources divergent : la
                    fédération n'évoque que l'accord du propriétaire, quand d'autres décrivent une
                    démarche en préfecture pour organiser une partie. Les usages varient d'un
                    département à l'autre, et seule la préfecture concernée tranche pour un terrain
                    donné. Les friches et bâtiments abandonnés, eux, ne sont jamais une réponse :
                    ils appartiennent à quelqu'un.
                </p>
            </div>
        </section>

        <section class="glab-section" aria-labelledby="ou-stand-title">
            <h2 class="glab-title" id="ou-stand-title">En stand <span class="glab-title-accent">homologué</span></h2>
            <p class="glab-lede">
                Le seul lieu construit autour de la question posée en haut de cette page.
            </p>

            <div class="glab-prose">
                <p>
                    Un stand agréé règle d'avance ce qu'un jardin ne règle jamais : le point d'arrêt
                    existe, les directions de tir sont matérialisées, quelqu'un commande le pas de
                    tir et le cessez-le-feu, et l'assurance du club couvre la séance. C'est aussi
                    le seul endroit où les armes de catégorie B acquises pour le tir sportif ont le
                    droit de servir, la réglementation le disant expressément.
                </p>
                <p>
                    Pour une pratique régulière, il faut la licence et le carnet de tir, dont les
                    visas nourrissent les autorisations. Pour essayer, la plupart des clubs
                    proposent des séances de découverte encadrées, armes et munitions fournies :
                    c'est la façon la moins coûteuse de savoir si l'on aime cela avant d'acheter
                    quoi que ce soit.
                </p>
            </div>

            <p class="glab-more-reading">
                Le régime de votre arme, catégorie par catégorie, est dans notre guide
                <a href="{{ route('guides.classification') }}">Classer son arme</a> ; le vocabulaire
                du pas de tir est au <a href="{{ route('guides.glossaire') }}">glossaire</a>.
            </p>
        </section>

        <section class="glab-panel" aria-labelledby="ou-faq-title">
            <h2 class="glab-title" id="ou-faq-title">Questions <span class="glab-title-accent">fréquentes</span></h2>

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

            <p class="glab-ctas">
                <a href="{{ route('categories.show', 'cibles-carton-metal') }}" class="btn btn-primary">Cibles et points d'arrêt</a>
                <a href="{{ route('guides.index') }}" class="btn btn-secondary">Tous les guides</a>
            </p>
        </section>

        <section class="glab-section" aria-labelledby="ou-sources-title">
            <h2 class="glab-title" id="ou-sources-title">Les <span class="glab-title-accent">sources</span></h2>
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
    </div>
@endsection
