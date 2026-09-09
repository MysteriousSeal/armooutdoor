@extends('layouts.app')

@section('title', 'Bien choisir sa cible de tir — Armo Outdoor')
@section('meta_description', 'Réactives autocollantes, planches, carton ou métal basculant : quel format pour quelle distance, ce qu\'on lit après le tir, et combien de feuilles prévoir.')
@section('og_type', 'article')
@section('canonical', route('guides.cibles'))

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/categories.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('css/guides/guides.css') }}">
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'Article',
            'headline' => 'Bien choisir sa cible de tir',
            'description' => 'Réactives autocollantes, planches, carton ou métal basculant : quel format pour quelle distance, ce qu\'on lit après le tir, et combien de feuilles prévoir.',
            'mainEntityOfPage' => route('guides.cibles'),
            'inLanguage' => 'fr-FR',
            'datePublished' => '2026-09-04',
            'dateModified' => '2026-09-06',
            'author' => \App\Support\OrganizationSchema::reference(),
            'publisher' => \App\Support\OrganizationSchema::reference(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'FAQPage',
            'mainEntity' => collect([
                ['Quel diamètre pour quelle distance ?', '76 mm : le format d\'entraînement de référence, à 10 ou 25 mètres, en lots de 100 à 250. 10 cm : distances plus longues, calibres plus remuants, débutants. Carrées à grille : pour régler une optique, la correction se lit en clics, en 51 mm, 76 mm ou 10 cm.'],
                ['Sur quoi coller une cible réactive ?', 'Sur n\'importe quel support qui tient : un carton usé, une vieille planche, le dos d\'une cible finie. On recharge la ligne sans racheter de porte-cible.'],
                ['Le métal convient-il à mon calibre ?', 'Notre cible basculante est réservée aux armes à air comprimé de 4,5 et 5,5 mm : pas d\'arme à feu, les plaques de 2,5 mm ne l\'encaisseraient pas. Distance 10 à 25 m, protection oculaire obligatoire.'],
                ['Combien de feuilles prévoir par séance ?', 'Une feuille par série de 10 à 20 impacts pour garder un score lisible. Un lot de 100 couvre une saison hebdomadaire ; les lots de 200 à 250 baissent le prix à l\'unité.'],
            ])->map(fn (array $qa): array => [
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
                ['@@type' => 'ListItem', 'position' => 3, 'name' => 'Bien choisir sa cible', 'item' => route('guides.cibles')],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('content')
    <div class="container glab glab--cibles">
        @include('guides.partials.hero', [
            'crumb' => 'Bien choisir sa cible',
            'kicker' => 'Guide',
            'title' => 'Bien choisir sa cible',
            'lede' => 'Une bonne cible se choisit d\'après trois questions : à quelle distance tirez-vous, que voulez-vous lire après le tir, et combien de feuilles partent à chaque séance. Le rayon couvre les trois réponses : cibles autocollantes réactives, planches complètes, carton classique et métal basculant.',
        ])

        <nav class="glab-plan" aria-label="Plan du guide">
            <p class="glab-plan-kicker">Plan</p>
            <div class="glab-plan-links">
                <a href="#glab-selector-title">Sélecteur</a>
                <a href="#glab-table-title">Familles</a>
                <a href="#glab-diameters-title">Diamètres</a>
                <a href="#glab-faq-title">Questions</a>
            </div>
        </nav>

        {{-- The selector: two answers, a ranked recommendation. --}}
        <section class="glab-panel" data-glab-selector aria-labelledby="glab-selector-title">
            <h2 class="glab-title" id="glab-selector-title">Deux réponses, <span class="glab-title-accent">votre cible</span></h2>
            <p class="glab-lede">
                Répondez et le rayon se réduit à l'essentiel.
            </p>

            <div class="glab-steps">
                <fieldset class="glab-step">
                    <legend>01 · Votre distance</legend>
                    <div class="glab-chips glab-chips--range" data-glab-group="dist">
                        <button type="button" data-glab-value="court">10 m</button>
                        <button type="button" data-glab-value="moyen" class="is-active">25 m</button>
                        <button type="button" data-glab-value="long">50 m et +</button>
                    </div>
                </fieldset>
                <fieldset class="glab-step">
                    <legend>02 · Ce que vous voulez lire</legend>
                    <div class="glab-chips glab-chips--stacked" data-glab-group="but">
                        <button type="button" data-glab-value="impact" class="is-active">
                            Voir mes impacts tout de suite
                            <small>sans quitter la ligne de tir</small>
                        </button>
                        <button type="button" data-glab-value="score">
                            Un score comparable
                            <small>d'une séance à l'autre</small>
                        </button>
                        <button type="button" data-glab-value="optique">
                            Régler une optique
                            <small>correction en clics</small>
                        </button>
                        <button type="button" data-glab-value="rythme">
                            Du tir ludique
                            <small>retour immédiat, sans papier</small>
                        </button>
                    </div>
                </fieldset>
            </div>

            <div class="glab-reco">
                <p class="glab-reco-label">Notre recommandation</p>
                <p class="glab-reco-resume" data-glab-resume>Pour lire vos impacts à 25 mètres :</p>
                {{-- The default answer (25 m, voir mes impacts) rendered
                     server-side: crawlable links, and a real block without
                     JavaScript. The script re-renders it on interaction. --}}
                <ol class="glab-reco-list" data-glab-results>
                    <li>
                        <span class="glab-reco-rank">01</span>
                        <div class="glab-reco-head">
                            <h3>Cibles réactives autocollantes</h3>
                            <span class="glab-reco-meta">Ø 76 mm · lots de 100 à 250</span>
                        </div>
                        <p>Chaque impact fait éclater un anneau fluo, visible à la lunette comme à l'œil nu. Se collent sur un carton usé ou une vieille planche.</p>
                        <a href="{{ route('categories.show', 'cibles-rondes') }}">Voir les rondes</a>
                    </li>
                    <li>
                        <span class="glab-reco-rank">02</span>
                        <div class="glab-reco-head">
                            <h3>Réactives Ø 10 cm</h3>
                            <span class="glab-reco-meta">Ø 100 mm · lots de 100</span>
                        </div>
                        <p>Plus tolérantes : distances longues, calibres remuants, ou premiers tirs d'un débutant qui a besoin de voir ses réussites.</p>
                        <a href="{{ route('categories.show', 'cibles-rondes') }}">Voir les rondes</a>
                    </li>
                    <li>
                        <span class="glab-reco-rank">03</span>
                        <div class="glab-reco-head">
                            <h3>Planches multi-cibles</h3>
                            <span class="glab-reco-meta">Jusqu'à 42 cibles · 20 x 20 cm</span>
                        </div>
                        <p>Des dizaines de pastilles neuves sur une feuille, certaines avec grille de réglage en clics : un agrafage couvre la séance entière.</p>
                        <a href="{{ route('categories.show', 'planches-cibles') }}">Voir les planches</a>
                    </li>
                </ol>
                <p class="glab-warning">
                    Le métal ne se tire qu'avec protection oculaire, à la distance minimale du
                    fabricant et dans les calibres admis par la plaque.
                </p>
            </div>
        </section>

        {{-- The four families at a glance: a card each, not a table that
             scrolls sideways the moment the page is a phone. --}}
        <section class="glab-section" aria-labelledby="glab-table-title">
            <h2 class="glab-title" id="glab-table-title">Quatre familles, <span class="glab-title-accent">quatre lectures</span></h2>
            <p class="glab-lede">
                Ce qu'on y lit, à quelle distance, et si ça se consomme.
            </p>

            <div class="glab-families">
                <article class="glab-family">
                    <p class="glab-family-kicker">10 à 50 m · au lot</p>
                    <h3>Réactives autocollantes</h3>
                    <p class="glab-family-read">Un anneau fluo à chaque impact, visible à la lunette.</p>
                    <p>
                        Sur une <a href="{{ route('categories.show', 'cibles-rondes') }}">cible réactive</a> dite « splatter », l'impact éclate en jaune, orange, vert ou rouge. On corrige le groupement sans quitter la ligne. Elles se collent sur un carton usé, une vieille planche, le dos d'une cible finie.
                    </p>
                    <a href="{{ route('categories.show', 'cibles-rondes') }}">Voir les rondes</a>
                </article>
                <article class="glab-family">
                    <p class="glab-family-kicker">10 à 25 m · à la feuille</p>
                    <h3>Planches multi-cibles</h3>
                    <p class="glab-family-read">Des dizaines de pastilles neuves sur une seule feuille.</p>
                    <p>
                        Jusqu'à 42 cibles en 20 × 20 cm : un agrafage pour la séance, un point neuf à chaque série. Certaines embarquent une grille de réglage en clics, pour zéroter avant le tir compté.
                    </p>
                    <a href="{{ route('categories.show', 'planches-cibles') }}">Voir les planches</a>
                </article>
                <article class="glab-family">
                    <p class="glab-family-kicker">10 à 25 m · lots de 20</p>
                    <h3>Carton, blasons et score</h3>
                    <p class="glab-family-read">Un score chiffré, comparable d'une séance à l'autre.</p>
                    <p>
                        <a href="{{ route('categories.show', 'cibles-carton-metal') }}">Huit blasons ou zones de score</a> sur une feuille de 22,86 × 17,78 cm. On note, on archive, on compare, et la feuille s'agrafe sur n'importe quel porte-cible.
                    </p>
                    <a href="{{ route('categories.show', 'cibles-carton-metal') }}">Voir carton et métal</a>
                </article>
                <article class="glab-family">
                    <p class="glab-family-kicker">10 à 25 m · aucun consommable</p>
                    <h3>Métal basculant</h3>
                    <p class="glab-family-read">Rien à lire : le son, la chute, le réarmement automatique.</p>
                    <p>
                        Notre <a href="{{ route('products.show', 'cible-basculantes-rearmement-automatique-5-plaques') }}">cible basculante à réarmement automatique</a> sonne à chaque plaque ; un tir sur la cinquième relance les quatre autres. Réservée aux airguns 4,5 et 5,5 mm.
                    </p>
                    <p class="glab-warning">
                        Protection oculaire, distance minimale du fabricant, calibres admis par la plaque.
                    </p>
                    <a href="{{ route('categories.show', 'cibles-carton-metal') }}">Voir carton et métal</a>
                </article>
            </div>
        </section>

        <section class="glab-section" aria-labelledby="glab-diameters-title">
            <h2 class="glab-title" id="glab-diameters-title">Quel diamètre <span class="glab-title-accent">pour quelle distance</span></h2>
            <p class="glab-lede">
                Trois formats, et ce n'est pas une question de goût : c'est la distance et ce que vous voulez lire.
            </p>

            <dl class="glab-specs glab-place-cards glab-place-cards--figures">
                <div>
                    <dt>
                        76 mm
                        <span class="glab-spec-kicker">Rondes</span>
                    </dt>
                    <dd>Le format d'entraînement de référence : à 10 ou 25 mètres, elles obligent à un vrai travail de précision. Lots de 100 à 250.</dd>
                </div>
                <div>
                    <dt>
                        10 cm
                        <span class="glab-spec-kicker">Rondes</span>
                    </dt>
                    <dd>Elles pardonnent davantage : distances plus longues, calibres plus remuants, ou premiers tirs d'un débutant.</dd>
                </div>
                <div>
                    <dt>
                        Grille
                        <span class="glab-spec-kicker">Carrées</span>
                    </dt>
                    <dd>Pour régler une optique : la correction se lit en clics, en 51 mm, 76 mm ou 10 cm. C'est <a href="{{ route('categories.show', 'cibles-carrees') }}">la cible du zérotage</a>, pas celle du score.</dd>
                </div>
            </dl>
        </section>

        {{-- The questions people actually ask. --}}
        <section class="glab-panel" aria-labelledby="glab-faq-title">
            <h2 class="glab-title" id="glab-faq-title">Questions <span class="glab-title-accent">fréquentes</span></h2>

            <div class="glab-faq">
                <details>
                    <summary>Quel diamètre pour quelle distance ?</summary>
                    <div>
                        <p><strong>76 mm</strong> : le format d'entraînement de référence. À 10 ou 25 mètres, il oblige à un vrai travail de précision, et les lots de 100 à 250 pièces suivent le rythme des séances.</p>
                        <p><strong>10 cm</strong> : pardonne davantage. Distances plus longues, calibres plus remuants, ou premiers tirs d'un débutant qui a besoin de voir ses réussites.</p>
                        <p><strong>Carrées à grille</strong> : pour régler une optique. La grille donne la correction en clics, ligne par ligne, colonne par colonne, en 51 mm, 76 mm ou 10 cm. C'est la cible du zérotage, pas celle du score.</p>
                    </div>
                </details>
                <details>
                    <summary>Sur quoi coller une cible réactive ?</summary>
                    <div>
                        <p>Sur n'importe quel support qui tient : un carton usé, une vieille planche, le dos d'une cible finie. C'est tout l'intérêt : on recharge la ligne sans racheter de porte-cible.</p>
                    </div>
                </details>
                <details>
                    <summary>Le métal convient-il à mon calibre ?</summary>
                    <div>
                        <p>Notre cible basculante est réservée aux armes à air comprimé de 4,5 et 5,5 mm : pas d'arme à feu, les plaques de 2,5 mm ne l'encaisseraient pas. Distance 10 à 25 m, protection oculaire obligatoire. Un acier sollicité au-delà de sa classe peut renvoyer des fragments.</p>
                    </div>
                </details>
                <details>
                    <summary>Combien de feuilles prévoir par séance ?</summary>
                    <div>
                        <p>Comptez une feuille par série de 10 à 20 impacts si vous voulez garder un score lisible. Un lot de 100 couvre une saison d'entraînement hebdomadaire ; au-delà, les lots de 200 à 250 baissent nettement le prix à l'unité, et les pastilles de réparation prolongent chaque feuille.</p>
                    </div>
                </details>
            </div>

            <p class="glab-more-reading">
                Pour aller plus loin, notre article
                <a href="{{ route('blog.show', 'bien-choisir-ses-cibles-carton-autocollantes-ou-metal') }}">Bien choisir ses cibles : carton, autocollantes ou métal</a>
                compare les trois familles en détail.
            </p>

            <p class="glab-more-reading">
                Et après la séance, votre canon mérite le même soin :
                <a href="{{ route('guides.entretien') }}">Entretenir son arme</a>.
            </p>

            <p class="glab-ctas">
                <a href="{{ route('categories.show', 'cibles') }}" class="btn btn-primary">Voir toutes les cibles</a>
                <a href="{{ route('categories.show', 'planches-cibles') }}" class="btn btn-secondary">Voir les planches</a>
            </p>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/guides/guides.js') }}" defer></script>
@endpush
