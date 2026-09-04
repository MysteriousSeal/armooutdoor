@extends('layouts.app')

@section('title', 'Bien choisir sa cible de tir — Armo Outdoor')
@section('meta_description', 'Réactives autocollantes, planches, carton ou métal basculant : quel format pour quelle distance, ce qu\'on lit après le tir, et combien de feuilles prévoir.')
@section('canonical', route('guides.cibles'))

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/guide-cibles.css') }}">
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'Article',
            'headline' => 'Bien choisir sa cible de tir',
            'description' => 'Réactives autocollantes, planches, carton ou métal basculant : quel format pour quelle distance, ce qu\'on lit après le tir, et combien de feuilles prévoir.',
            'mainEntityOfPage' => route('guides.cibles'),
            'inLanguage' => 'fr-FR',
            'author' => \App\Support\OrganizationSchema::reference(),
            'publisher' => \App\Support\OrganizationSchema::reference(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'FAQPage',
            'mainEntity' => collect([
                ['Quel diamètre pour quelle distance ?', '76 mm : le format d\'entraînement de référence, à 10 ou 25 mètres, en lots de 100 à 250. 10 cm : distances plus longues, calibres plus remuants, débutants. Carrées à grille : pour régler une optique, la correction se lit en clics.'],
                ['Sur quoi coller une cible réactive ?', 'Sur n\'importe quel support qui tient : un carton usé, une vieille planche, le dos d\'une cible finie. On recharge la ligne sans racheter de porte-cible.'],
                ['Le métal convient-il à mon calibre ?', 'Notre cible basculante est prévue pour les airguns et le 22 LR : vérifiez les calibres admis par la plaque, respectez la distance minimale du fabricant et portez une protection oculaire.'],
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
                ['@@type' => 'ListItem', 'position' => 2, 'name' => 'Guides d\'achat', 'item' => route('guides.index')],
                ['@@type' => 'ListItem', 'position' => 3, 'name' => 'Bien choisir sa cible', 'item' => route('guides.cibles')],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('content')
    <div class="container glab">
        <nav class="breadcrumbs" aria-label="breadcrumb">
            <a href="{{ localized_route('home') }}">{{ __('store.breadcrumb_home') }}</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <a href="{{ route('guides.index') }}">Guides d'achat</a>
            <span class="breadcrumbs-sep" aria-hidden="true">/</span>
            <span>Bien choisir sa cible</span>
        </nav>

        <header class="glab-head">
            <p class="glab-head-kicker">Guide d'achat</p>
            <h1 class="glab-head-title">Bien choisir <span class="glab-title-accent">sa cible</span></h1>
            <p class="glab-head-lede">
                Une bonne cible se choisit d'après trois questions : à quelle distance tirez-vous,
                que voulez-vous lire après le tir, et combien de feuilles partent à chaque séance.
                Le rayon couvre les trois réponses : cibles autocollantes réactives, planches
                complètes, carton classique et métal basculant.
            </p>
        </header>

        {{-- The selector: two answers, a ranked recommendation. --}}
        <section class="glab-panel" data-glab-selector aria-labelledby="glab-selector-title">
            <h2 class="glab-title" id="glab-selector-title">Deux réponses, <span class="glab-title-accent">votre cible</span></h2>
            <p class="glab-lede">
                Répondez et le rayon se réduit à l'essentiel.
            </p>

            <div class="glab-steps">
                <fieldset class="glab-step">
                    <legend>01 · Votre distance</legend>
                    <div class="glab-chips" data-glab-group="dist">
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
                            <h4>Cibles réactives autocollantes</h4>
                            <span class="glab-reco-meta">Ø 76 mm · lots de 100 à 250</span>
                        </div>
                        <p>Chaque impact fait éclater un anneau fluo, visible à la lunette comme à l'œil nu. Se collent sur un carton usé ou une vieille planche.</p>
                        <a href="{{ route('categories.show', 'cibles-rondes') }}">Voir les rondes</a>
                    </li>
                    <li>
                        <span class="glab-reco-rank">02</span>
                        <div class="glab-reco-head">
                            <h4>Réactives Ø 10 cm</h4>
                            <span class="glab-reco-meta">Ø 100 mm · lots de 100</span>
                        </div>
                        <p>Plus tolérantes : distances longues, calibres remuants, ou premiers tirs d'un débutant qui a besoin de voir ses réussites.</p>
                        <a href="{{ route('categories.show', 'cibles-rondes') }}">Voir les rondes</a>
                    </li>
                    <li>
                        <span class="glab-reco-rank">03</span>
                        <div class="glab-reco-head">
                            <h4>Planches multi-cibles</h4>
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

        {{-- The overview table: the whole shelf at a glance. --}}
        <section class="glab-panel" aria-labelledby="glab-table-title">
            <h2 class="glab-title" id="glab-table-title">Quatre familles, <span class="glab-title-accent">un seul tableau</span></h2>

            <div class="glab-table-wrap">
                <table class="glab-table">
                    <thead>
                        <tr>
                            <th>Famille</th>
                            <th>Ce qu'on y lit</th>
                            <th>Distance</th>
                            <th>Consommable</th>
                            <th>Pour qui</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Réactives autocollantes</td>
                            <td>Un anneau fluo à chaque impact, visible à la lunette</td>
                            <td>10 à 50 m</td>
                            <td>Oui, à la feuille</td>
                            <td>Réglage rapide, séance seule</td>
                        </tr>
                        <tr>
                            <td>Planches multi-cibles</td>
                            <td>Des dizaines de pastilles neuves sur une seule feuille</td>
                            <td>10 à 25 m</td>
                            <td>Oui, à la feuille</td>
                            <td>Séance structurée</td>
                        </tr>
                        <tr>
                            <td>Carton, blasons et score</td>
                            <td>Un score chiffré, comparable d'une séance à l'autre</td>
                            <td>10 à 25 m</td>
                            <td>Oui, en lot de 20</td>
                            <td>Tir compté</td>
                        </tr>
                        <tr>
                            <td>Métal basculant</td>
                            <td>Rien à lire : le son et la chute, réarmement automatique</td>
                            <td>Selon fabricant</td>
                            <td>Aucun</td>
                            <td>Tir ludique, airguns et 22 LR</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        {{-- The guide proper. --}}
        <section class="glab-panel" aria-labelledby="glab-guide-title">
            <h2 class="glab-title" id="glab-guide-title">Le rayon <span class="glab-title-accent">en détail</span></h2>

            <div class="glab-prose">
                <h3>Réactives autocollantes : lire ses impacts sans quitter la ligne</h3>
                <p>
                    Sur une <a href="{{ route('categories.show', 'cibles-rondes') }}">cible réactive</a> dite « splatter », chaque impact fait éclater un anneau
                    fluorescent, jaune, orange, vert ou rose, visible à la lunette comme à l'œil nu.
                    On corrige son groupement sans faire d'aller-retour ni attendre un cessez-le-feu.
                    Elles se collent sur n'importe quel support : un carton usé, une vieille planche,
                    le dos d'une cible finie.
                </p>

                <h3>Quel diamètre pour quelle distance</h3>
                <dl class="glab-specs">
                    <div>
                        <dt>76 mm <em>rondes</em></dt>
                        <dd>Le format d'entraînement de référence : à 10 ou 25 mètres, elles obligent à un vrai travail de précision, et les lots de 100 à 250 pièces suivent le rythme des séances.</dd>
                    </div>
                    <div>
                        <dt>10 cm <em>rondes</em></dt>
                        <dd>Elles pardonnent davantage : distances plus longues, calibres plus remuants, ou premiers tirs d'un débutant qui a besoin de voir ses réussites.</dd>
                    </div>
                    <div>
                        <dt>Grille <em>carrées</em></dt>
                        <dd>Elles servent à régler une optique : la grille donne la correction en clics, ligne par ligne, colonne par colonne. C'est <a href="{{ route('categories.show', 'cibles-carrees') }}">la cible du zérotage</a>, pas celle du score.</dd>
                    </div>
                </dl>

                <h3>Les planches : la séance complète sur une feuille</h3>
                <p>
                    Une planche regroupe plusieurs dizaines de pastilles sur une seule feuille,
                    jusqu'à 42 cibles en 20 x 20 cm : un seul agrafage pour toute la séance, et un
                    point neuf à chaque série. Certaines embarquent une grille de réglage en clics
                    avec rapporteur, pour zéroter proprement avant de passer au tir compté.
                </p>

                <h3>Carton : blasons et zones de score</h3>
                <p>
                    Le carton reste le support du tir compté : <a href="{{ route('categories.show', 'cibles-carton-metal') }}">huit blasons ou zones de score</a> sur une
                    feuille d'environ 23 x 18 cm, vendue par lots de 20. On note, on archive, on
                    compare d'une séance à l'autre, et la feuille s'agrafe sur n'importe quel
                    porte-cible.
                </p>

                <h3>Métal basculant : le retour immédiat</h3>
                <p>
                    Le métal ne se lit pas, il s'entend : notre <a href="{{ route('products.show', 'cible-basculantes-rearmement-automatique-5-plaques') }}">cible basculante à réarmement
                    automatique</a> sonne à chaque plaque touchée et se relève seule, cinq plaques
                    d'affilée. Aucun consommable, un retour instantané, idéale pour le tir ludique
                    aux airguns et au 22 LR.
                </p>
                <p class="glab-warning">
                    Le métal se tire uniquement avec une protection oculaire, à la distance minimale
                    indiquée par le fabricant, et dans les calibres que la plaque admet.
                </p>
            </div>
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
                        <p><strong>Carrées à grille</strong> : pour régler une optique. La grille donne la correction en clics, ligne par ligne, colonne par colonne. C'est la cible du zérotage, pas celle du score.</p>
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
                        <p>Notre cible basculante est prévue pour les airguns et le 22 LR : vérifiez les calibres admis par la plaque et respectez la distance minimale du fabricant. Protection oculaire obligatoire, un acier sollicité au-delà de sa classe peut renvoyer des fragments.</p>
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

            <p class="glab-ctas">
                <a href="{{ route('categories.show', 'cibles') }}" class="btn btn-primary">Voir toutes les cibles</a>
                <a href="{{ route('categories.show', 'planches-cibles') }}" class="btn btn-secondary">Voir les planches</a>
            </p>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/guide-cibles.js') }}" defer></script>
@endpush
