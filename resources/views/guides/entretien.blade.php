@extends('layouts.app')

@section('title', 'Entretenir son arme : corde ou kit à tiges — Armo Outdoor')
@section('meta_description', 'Corde de nettoyage ou kit à tiges : quel matériel pour quel calibre, du 4,5 mm au calibre 12, dans quel sens nettoyer son canon et à quelle fréquence.')
@section('og_type', 'article')
@section('canonical', route('guides.entretien'))

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/guides/guides.css') }}">
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'Article',
            'headline' => 'Entretenir son arme : corde ou kit à tiges',
            'description' => 'Corde de nettoyage ou kit à tiges : quel matériel pour quel calibre, du 4,5 mm au calibre 12, dans quel sens nettoyer son canon et à quelle fréquence.',
            'mainEntityOfPage' => route('guides.entretien'),
            'inLanguage' => 'fr-FR',
            'datePublished' => '2026-09-04',
            'dateModified' => '2026-09-04',
            'author' => \App\Support\OrganizationSchema::reference(),
            'publisher' => \App\Support\OrganizationSchema::reference(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'FAQPage',
            'mainEntity' => collect([
                ['La corde remplace-t-elle le kit à tiges ?', 'Non, elle le complète. La corde fait l\'essentiel en deux minutes au stand ; les tiges, brosses et écouvillons du kit font le nettoyage complet à l\'établi, chambre comprise. Le kit couvre les calibres .22, 9 mm, .40 et .357 ; au-delà, la corde du calibre reste l\'outil principal.'],
                ['Quelle corde pour un airgun à plombs 4,5 mm ?', 'La corde .17 / .177 / 4,5 mm : c\'est le même diamètre de canon. Un airgun s\'encrasse moins qu\'une arme à feu, mais un canon propre reste plus régulier.'],
                ['Dans quel sens nettoyer le canon ?', 'De la chambre vers la bouche, dans le sens du projectile. On protège ainsi le couronnement, le dernier point d\'appui de la balle : abîmé, il coûte de la précision qu\'aucun nettoyage ne rend.'],
                ['À quelle fréquence nettoyer ?', 'Un passage de corde après chaque séance suffit pour l\'entretien courant ; un nettoyage complet à l\'établi de temps en temps, et toujours avant un stockage prolongé. La corde elle-même se lave à l\'eau savonneuse et se réutilise.'],
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
                ['@@type' => 'ListItem', 'position' => 3, 'name' => 'Entretenir son arme', 'item' => route('guides.entretien')],
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
            <span>Entretenir son arme</span>
        </nav>

        <header class="glab-head">
            <p class="glab-head-kicker">Guide d'achat</p>
            <h1 class="glab-head-title">Entretenir <span class="glab-title-accent">son arme</span></h1>
            <p class="glab-head-lede">
                Deux outils font tout l'entretien courant : la corde de nettoyage, qui fait
                l'essentiel en deux minutes au stand, et le kit à tiges, qui fait le nettoyage
                complet à l'établi. Ce guide dit lequel prendre pour quel calibre, dans quel sens
                s'en servir, et à quelle fréquence.
            </p>
        </header>

        <p class="glab-warning">
            Avant tout entretien : arme déchargée, chambre vérifiée vide, munitions à l'écart de
            la table. Un drapeau de chambre vide rend l'état de l'arme visible d'un coup d'œil.
        </p>

        {{-- The selector: calibre and place, a ranked kit. --}}
        <section class="glab-panel" data-glab-selector aria-labelledby="glab-selector-title">
            <h2 class="glab-title" id="glab-selector-title">Deux réponses, <span class="glab-title-accent">votre trousse</span></h2>
            <p class="glab-lede">
                Répondez et le rayon se réduit à l'essentiel.
            </p>

            <div class="glab-steps">
                <fieldset class="glab-step">
                    <legend>01 · Votre calibre</legend>
                    <div class="glab-chips" data-glab-group="cal">
                        <button type="button" data-glab-value="45">.17 · 4,5 mm</button>
                        <button type="button" data-glab-value="22" class="is-active">.22 LR · 5,56</button>
                        <button type="button" data-glab-value="25">.25 · 6,35 mm</button>
                        <button type="button" data-glab-value="9">9 mm · .38 · .357</button>
                        <button type="button" data-glab-value="308">.308 · 7,62</button>
                        <button type="button" data-glab-value="12">Calibre 12</button>
                    </div>
                </fieldset>
                <fieldset class="glab-step">
                    <legend>02 · Où nettoyez-vous</legend>
                    <div class="glab-chips glab-chips--stacked" data-glab-group="lieu">
                        <button type="button" data-glab-value="stand" class="is-active">
                            Au stand, en deux minutes
                            <small>un passage entre deux séries</small>
                        </button>
                        <button type="button" data-glab-value="etabli">
                            À l'établi, complet
                            <small>chambre et recoins compris</small>
                        </button>
                    </div>
                </fieldset>
            </div>

            <div class="glab-reco">
                <p class="glab-reco-label">Notre recommandation</p>
                <p class="glab-reco-resume" data-glab-resume>Pour votre .22 LR, au stand :</p>
                {{-- The default answer (.22 LR, au stand) rendered server-side:
                     crawlable links, and a real block without JavaScript. The
                     script re-renders it on interaction. --}}
                <ol class="glab-reco-list" data-glab-results>
                    <li>
                        <span class="glab-reco-rank">01</span>
                        <div class="glab-reco-head">
                            <h3>Corde de nettoyage .22 · .223 · 5,56</h3>
                            <span class="glab-reco-meta">Bore rope · lavable</span>
                        </div>
                        <p>Brosse laiton et tissu en un seul passage, de la chambre vers la bouche. Tient dans une poche de sac de stand.</p>
                        <a href="{{ route('products.show', 'corde-nettoyage-canon-22-223-5-56mm-bore-rope') }}">Voir la corde</a>
                    </li>
                    <li>
                        <span class="glab-reco-rank">02</span>
                        <div class="glab-reco-head">
                            <h3>Étiquettes chambre vide</h3>
                            <span class="glab-reco-meta">Lot de 2 · universelles</span>
                        </div>
                        <p>Le drapeau qui rend l'arme visiblement sûre, avant l'entretien comme sur la ligne.</p>
                        <a href="{{ route('products.show', 'lot-2-etiquettes-chambre-vide-brodees-rouges-porte-cles-securite-fusil-pistolet-universel') }}">Voir les étiquettes</a>
                    </li>
                    <li>
                        <span class="glab-reco-rank">03</span>
                        <div class="glab-reco-head">
                            <h3>Récupérateur de douilles</h3>
                            <span class="glab-reco-meta">Filet rail ou sac</span>
                        </div>
                        <p>La ligne reste propre et le laiton rentre à la maison au lieu de finir au sol.</p>
                        <a href="{{ route('categories.show', 'recuperateurs-de-douilles') }}">Voir les récupérateurs</a>
                    </li>
                </ol>
            </div>
        </section>

        {{-- The overview table: rope against rods. --}}
        <section class="glab-panel" aria-labelledby="glab-table-title">
            <h2 class="glab-title" id="glab-table-title">Corde ou kit à tiges, <span class="glab-title-accent">en un tableau</span></h2>

            <div class="glab-table-wrap">
                <table class="glab-table">
                    <thead>
                        <tr>
                            <th>Outil</th>
                            <th>Où</th>
                            <th>Ce que ça nettoie</th>
                            <th>Calibres couverts</th>
                            <th>Entretien</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Corde de nettoyage</td>
                            <td>Au stand, en deux minutes</td>
                            <td>Le canon : brosse laiton puis tissu, en un passage</td>
                            <td>Une corde par calibre, du .17 au calibre 12</td>
                            <td>Lavable et réutilisable</td>
                        </tr>
                        <tr>
                            <td>Kit à tiges 16 pièces</td>
                            <td>À l'établi</td>
                            <td>Le nettoyage complet : canon, chambre, recoins</td>
                            <td>.22, 9 mm, .40 et .357</td>
                            <td>Tiges laiton, brosses et écouvillons</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        {{-- The guide proper. --}}
        <section class="glab-panel" aria-labelledby="glab-guide-title">
            <h2 class="glab-title" id="glab-guide-title">L'entretien <span class="glab-title-accent">en détail</span></h2>

            <div class="glab-prose">
                <h3>La corde : le canon propre en deux minutes</h3>
                <p>
                    Une <a href="{{ route('categories.show', 'entretien-arme') }}">corde de nettoyage</a>,
                    dite « bore rope », embarque une zone de brosse en laiton puis une longueur de
                    tissu sur une seule cordelette lestée. On la laisse tomber côté chambre, on tire
                    côté bouche : la brosse décolle les résidus, le tissu les emporte, en un seul
                    passage. Elle tient dans une poche, ne se démonte pas, et se range aussi vite
                    qu'elle sert : c'est l'outil du stand.
                </p>

                <h3>Quelle corde pour quel calibre</h3>
                <p>
                    Le diamètre de la corde doit être celui du canon. Chaque corde couvre une
                    famille de calibres voisins :
                </p>
                <ul>
                    <li><a href="{{ route('products.show', 'corde-nettoyage-canon-carabine-17-177-17hmr-17wmr-45mm-bore-rope') }}">.17 · .177 · .17 HMR · .17 WMR · 4,5 mm</a> : les airguns à plombs et les petits calibres à feu.</li>
                    <li><a href="{{ route('products.show', 'corde-nettoyage-canon-22-223-5-56mm-bore-rope') }}">.22 · .223 · 5,56 mm</a> : le 22 LR du stand et les calibres AR.</li>
                    <li><a href="{{ route('products.show', 'corde-nettoyage-canon-carabine-25-264-635mm-bore-rope') }}">.25 · .264 · 6,35 mm</a> : les airguns 6,35 et calibres intermédiaires.</li>
                    <li><a href="{{ route('products.show', 'corde-nettoyage-canon-38-357-380-9mm-bore-rope') }}">.38 · .357 · .380 · 9 mm</a> : le traditionnel 9 mm et les revolvers.</li>
                    <li><a href="{{ route('products.show', 'corde-nettoyage-canon-carabine-30-308-30-06-300-303-7-62mm-bore-rope') }}">.30 · .308 · 30-06 · .300 · .303 · 7,62 mm</a> : les carabines de stand longue distance.</li>
                    <li><a href="{{ route('products.show', 'corde-nettoyage-canon-calibre-12-bore-rope') }}">Calibre 12</a> : les fusils lisses.</li>
                </ul>

                <h3>Le kit à tiges : le nettoyage d'établi</h3>
                <p>
                    Le <a href="{{ route('products.show', 'kit-de-nettoyage-universel-pour-armes-16-pieces-tiges-en-laiton-calibres-22-9mm-40-et-357') }}">kit universel 16 pièces</a>
                    fait ce que la corde ne fait pas : tiges en laiton, brosses et écouvillons pour
                    les calibres .22, 9 mm, .40 et .357, à passer tranquillement à l'établi. C'est
                    le nettoyage complet, chambre et recoins compris, celui qu'on fait de temps en
                    temps et avant un stockage prolongé. Pour les calibres qu'il ne couvre pas, la
                    corde du calibre reste l'outil principal.
                </p>

                <h3>Le sens du geste : de la chambre vers la bouche</h3>
                <p>
                    Corde ou tige, le mouvement va de la chambre vers la bouche, dans le sens du
                    projectile. La raison tient en un mot : le couronnement, le dernier appui de la
                    balle à la sortie du canon. Nettoyer à rebours, c'est y frotter l'outil à chaque
                    passage ; un couronnement marqué coûte de la précision qu'aucun nettoyage ne
                    rendra.
                </p>

                <h3>À quelle fréquence</h3>
                <p>
                    Un passage de corde après chaque séance suffit pour l'entretien courant : deux
                    minutes pendant que la ligne est froide. Le nettoyage complet à l'établi se fait
                    de temps en temps, et toujours avant de ranger l'arme pour longtemps. La corde
                    elle-même se lave à l'eau savonneuse, sèche à l'air et repart pour des dizaines
                    de passages.
                </p>

                <h3>Ce qui complète la trousse</h3>
                <p>
                    Un <a href="{{ route('categories.show', 'kit-stand-tir') }}">tapis de tir</a>
                    protège l'établi et la crosse pendant le démontage, les
                    <a href="{{ route('products.show', 'lot-2-etiquettes-chambre-vide-brodees-rouges-porte-cles-securite-fusil-pistolet-universel') }}">étiquettes chambre vide</a>
                    rendent l'arme visiblement sûre, un
                    <a href="{{ route('categories.show', 'recuperateurs-de-douilles') }}">récupérateur de douilles</a>
                    garde la ligne propre, et une
                    <a href="{{ route('categories.show', 'boites-munitions') }}">boîte de munitions</a>
                    range ce qui attend la prochaine séance.
                </p>
            </div>

            <p class="glab-more-reading">
                Pour aller plus loin, notre article
                <a href="{{ route('blog.show', 'nettoyer-son-canon-quelle-corde-pour-quel-calibre') }}">Nettoyer son canon : quelle corde pour quel calibre</a>
                détaille corde par corde.
            </p>
        </section>

        {{-- The questions people actually ask. --}}
        <section class="glab-panel" aria-labelledby="glab-faq-title">
            <h2 class="glab-title" id="glab-faq-title">Questions <span class="glab-title-accent">fréquentes</span></h2>

            <div class="glab-faq">
                <details>
                    <summary>La corde remplace-t-elle le kit à tiges ?</summary>
                    <div>
                        <p>Non, elle le complète. La corde fait l'essentiel en deux minutes au stand ; les tiges, brosses et écouvillons du kit font le nettoyage complet à l'établi, chambre comprise. Le kit couvre les calibres .22, 9 mm, .40 et .357 ; au-delà, la corde du calibre reste l'outil principal.</p>
                    </div>
                </details>
                <details>
                    <summary>Quelle corde pour un airgun à plombs 4,5 mm ?</summary>
                    <div>
                        <p>La corde .17 / .177 / 4,5 mm : c'est le même diamètre de canon. Un airgun s'encrasse moins qu'une arme à feu, mais un canon propre reste plus régulier.</p>
                    </div>
                </details>
                <details>
                    <summary>Dans quel sens nettoyer le canon ?</summary>
                    <div>
                        <p>De la chambre vers la bouche, dans le sens du projectile. On protège ainsi le couronnement, le dernier point d'appui de la balle : abîmé, il coûte de la précision qu'aucun nettoyage ne rend.</p>
                    </div>
                </details>
                <details>
                    <summary>À quelle fréquence nettoyer ?</summary>
                    <div>
                        <p>Un passage de corde après chaque séance pour l'entretien courant ; un nettoyage complet à l'établi de temps en temps, et toujours avant un stockage prolongé. La corde elle-même se lave à l'eau savonneuse et se réutilise.</p>
                    </div>
                </details>
            </div>

            <p class="glab-more-reading">
                Et pour la prochaine séance, de quoi occuper la ligne :
                <a href="{{ route('guides.cibles') }}">Bien choisir sa cible</a>.
            </p>

            <p class="glab-ctas">
                <a href="{{ route('categories.show', 'entretien-arme') }}" class="btn btn-primary">Voir le rayon entretien</a>
                <a href="{{ route('categories.show', 'stand-de-tir') }}" class="btn btn-secondary">Tout le stand de tir</a>
            </p>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/guides/entretien.js') }}" defer></script>
@endpush
