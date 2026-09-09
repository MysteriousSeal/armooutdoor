@extends('layouts.app')

@section('title', 'Entretenir son arme : corde ou kit à tiges — Armo Outdoor')
@section('meta_description', 'Corde de nettoyage ou kit à tiges : quel matériel pour quel calibre, du 4,5 mm au calibre 12, dans quel sens nettoyer son canon et à quelle fréquence.')
@section('og_type', 'article')
@section('canonical', route('guides.entretien'))

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/categories.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('css/guides.css') }}">
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'Article',
            'headline' => 'Entretenir son arme : corde ou kit à tiges',
            'description' => 'Corde de nettoyage ou kit à tiges : quel matériel pour quel calibre, du 4,5 mm au calibre 12, dans quel sens nettoyer son canon et à quelle fréquence.',
            'mainEntityOfPage' => route('guides.entretien'),
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
                ['La corde remplace-t-elle le kit à tiges ?', 'Non, elle le complète. La corde fait l\'essentiel en deux minutes au stand ; les tiges, brosses et écouvillons du kit font le nettoyage complet à l\'établi, chambre comprise. Le kit couvre les calibres .22, 9 mm, .40 et .357 ; hors de ces calibres, la corde du calibre reste l\'outil principal.'],
                ['Quelle corde pour un airgun à plombs 4,5 mm ?', 'La corde .17 / .177 / 4,5 mm : c\'est le même diamètre de canon. Un airgun s\'encrasse moins qu\'une arme à feu, mais un canon propre reste plus régulier.'],
                ['Dans quel sens nettoyer le canon ?', 'De la chambre vers la bouche, dans le sens du projectile. On protège ainsi le couronnement, le dernier point d\'appui de la balle : abîmé, il coûte de la précision qu\'aucun nettoyage ne rendra.'],
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
                ['@@type' => 'ListItem', 'position' => 2, 'name' => 'Guides', 'item' => route('guides.index')],
                ['@@type' => 'ListItem', 'position' => 3, 'name' => 'Entretenir son arme', 'item' => route('guides.entretien')],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('content')
    <div class="container glab glab--entretien">
        @include('guides.partials.hero', [
            'crumb' => 'Entretenir son arme',
            'kicker' => 'Guide',
            'title' => 'Entretenir son arme',
            'lede' => 'Deux outils font tout l\'entretien courant : la corde de nettoyage, qui fait l\'essentiel en deux minutes au stand, et le kit à tiges, qui fait le nettoyage complet à l\'établi. Ce guide dit lequel prendre pour quel calibre, dans quel sens s\'en servir, et à quelle fréquence.',
        ])


        <nav class="glab-plan" aria-label="Plan du guide">
            <p class="glab-plan-kicker">Plan</p>
            <div class="glab-plan-links">
                <a href="#glab-selector-title">Sélecteur</a>
                <a href="#glab-table-title">Corde ou kit</a>
                <a href="#glab-calibres-title">Calibres</a>
                <a href="#glab-guide-title">Le geste</a>
                <a href="#glab-faq-title">Questions</a>
            </div>
        </nav>

        <p class="glab-warning">
            <strong class="glab-warning-label">Avant tout entretien</strong>
            Arme déchargée, chambre vérifiée vide, munitions à l'écart de la table. Un
            drapeau de chambre vide rend l'état de l'arme visible d'un coup d'œil.
        </p>

        {{-- The selector: calibre and place, a ranked kit. --}}
        <section class="glab-section" data-glab-selector="entretien" aria-labelledby="glab-selector-title">
            <h2 class="glab-title" id="glab-selector-title">Deux réponses, <span class="glab-title-accent">votre trousse</span></h2>
            <p class="glab-lede">
                Répondez et le rayon se réduit à l'essentiel.
            </p>

            <div class="glab-steps">
                <fieldset class="glab-step">
                    <legend>01 · Votre calibre</legend>
                    <div class="glab-chips glab-chips--range" data-glab-group="cal">
                        <button type="button" data-glab-value="45">.17 · 4,5 mm</button>
                        <button type="button" data-glab-value="22" class="is-active">.22 LR · 5,56 mm</button>
                        <button type="button" data-glab-value="25">.25 · 6,35 mm</button>
                        <button type="button" data-glab-value="9">9 mm · .38 · .357</button>
                        <button type="button" data-glab-value="308">.308 · 7,62 mm</button>
                        <button type="button" data-glab-value="12">Calibre 12</button>
                    </div>
                </fieldset>
                <fieldset class="glab-step">
                    <legend>02 · Où nettoyez-vous ?</legend>
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
                <p class="glab-reco-resume" data-glab-resume>Pour votre .22 LR · 5,56 mm, au stand :</p>
                {{-- The default answer (.22 LR, au stand) rendered server-side:
                     crawlable links, and a real block without JavaScript. The
                     script re-renders it on interaction. --}}
                <ol class="glab-reco-list" data-glab-results>
                    <li>
                        <span class="glab-reco-rank">01</span>
                        <div class="glab-reco-head">
                            <h3>Corde de nettoyage .22 · .223 · 5,56 mm</h3>
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

        {{-- Rope against rods, two cards instead of a table that spills. --}}
        <section class="glab-section" aria-labelledby="glab-table-title">
            <h2 class="glab-title" id="glab-table-title">Corde ou kit à tiges, <span class="glab-title-accent">deux outils</span></h2>
            <p class="glab-lede">
                L'un fait l'essentiel au stand. L'autre fait le complet à l'établi. Ils se complètent.
            </p>

            <div class="glab-families">
                <article class="glab-family">
                    <p class="glab-family-kicker">Au stand · deux minutes</p>
                    <h3>Corde de nettoyage</h3>
                    <p class="glab-family-read">Le canon : brosse laiton puis tissu, en un passage.</p>
                    <p>
                        Une <a href="{{ route('categories.show', 'entretien-arme') }}">corde de nettoyage</a>,
                        dite « bore rope », embarque une brosse en laiton puis une longueur de tissu
                        sur une cordelette lestée. On la laisse tomber côté chambre, on tire côté
                        bouche. Elle tient dans une poche : c'est l'outil du stand. Une corde par
                        calibre, du .17 au calibre 12. Lavable et réutilisable.
                    </p>
                    <a href="{{ route('categories.show', 'entretien-arme') }}">Voir le rayon entretien</a>
                </article>
                <article class="glab-family">
                    <p class="glab-family-kicker">À l'établi · complet</p>
                    <h3>Kit à tiges 16 pièces</h3>
                    <p class="glab-family-read">Canon, chambre et recoins, tranquillement.</p>
                    <p>
                        Le <a href="{{ route('products.show', 'kit-de-nettoyage-universel-pour-armes-16-pieces-tiges-en-laiton-calibres-22-9mm-40-et-357') }}">kit universel 16 pièces</a>
                        fait ce que la corde ne fait pas : tiges en laiton, brosses et écouvillons
                        pour les calibres .22, 9 mm, .40 et .357. C'est le nettoyage d'avant un
                        stockage prolongé. Hors de ces calibres, la corde du calibre reste l'outil
                        principal.
                    </p>
                    <a href="{{ route('products.show', 'kit-de-nettoyage-universel-pour-armes-16-pieces-tiges-en-laiton-calibres-22-9mm-40-et-357') }}">Voir le kit</a>
                </article>
            </div>
        </section>

        <section class="glab-section" aria-labelledby="glab-calibres-title">
            <h2 class="glab-title" id="glab-calibres-title">Quelle corde <span class="glab-title-accent">pour quel calibre</span></h2>
            <p class="glab-lede">
                Le diamètre de la corde doit être celui du canon. Chaque corde couvre une famille de calibres voisins.
            </p>

            <ul class="glab-calibres">
                <li>
                    <a href="{{ route('products.show', 'corde-nettoyage-canon-carabine-17-177-17hmr-17wmr-45mm-bore-rope') }}">
                        <span class="glab-calibre-size">.17 · 4,5 mm</span>
                        <span class="glab-calibre-use">Airguns à plombs et petits calibres à feu</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('products.show', 'corde-nettoyage-canon-22-223-5-56mm-bore-rope') }}">
                        <span class="glab-calibre-size">.22 · 5,56 mm</span>
                        <span class="glab-calibre-use">Le 22 LR du stand et le 5,56 mm</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('products.show', 'corde-nettoyage-canon-carabine-25-264-635mm-bore-rope') }}">
                        <span class="glab-calibre-size">.25 · 6,35 mm</span>
                        <span class="glab-calibre-use">Airguns 6,35 mm et calibres intermédiaires</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('products.show', 'corde-nettoyage-canon-38-357-380-9mm-bore-rope') }}">
                        <span class="glab-calibre-size">9 mm · .38 · .357</span>
                        <span class="glab-calibre-use">Le 9 mm et les revolvers</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('products.show', 'corde-nettoyage-canon-carabine-30-308-30-06-300-303-7-62mm-bore-rope') }}">
                        <span class="glab-calibre-size">.308 · 7,62 mm</span>
                        <span class="glab-calibre-use">Carabines de stand à longue distance</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('products.show', 'corde-nettoyage-canon-calibre-12-bore-rope') }}">
                        <span class="glab-calibre-size">Calibre 12</span>
                        <span class="glab-calibre-use">Les fusils lisses</span>
                    </a>
                </li>
            </ul>
        </section>

        <section class="glab-section" aria-labelledby="glab-guide-title">
            <h2 class="glab-title" id="glab-guide-title">Le geste <span class="glab-title-accent">et la cadence</span></h2>
            <p class="glab-lede">
                Le sens, la fréquence, et ce qui complète la trousse.
            </p>

            <ol class="glab-rules">
                <li>
                    <h3>De la chambre vers la bouche</h3>
                    <p>
                        Corde ou tige, le mouvement va dans le sens du projectile. La raison tient
                        en un mot : le couronnement, le dernier point d'appui de la balle à la
                        sortie du canon. Nettoyer à rebours, c'est y frotter l'outil à chaque
                        passage ; un couronnement abîmé coûte de la précision qu'aucun nettoyage ne
                        rendra.
                    </p>
                </li>
                <li>
                    <h3>Après chaque séance, et avant de ranger</h3>
                    <p>
                        Un passage de corde après chaque séance suffit pour l'entretien courant :
                        deux minutes pendant que la ligne est froide. Le nettoyage complet à
                        l'établi se fait de temps en temps, et toujours avant de ranger l'arme pour
                        longtemps. La corde elle-même se lave à l'eau savonneuse, sèche à l'air et
                        repart pour des dizaines de passages.
                    </p>
                </li>
            </ol>

            <ul class="glab-takeaways">
                <li>
                    <span class="glab-takeaway-label">Tapis</span>
                    <span>Un <a href="{{ route('categories.show', 'kit-stand-tir') }}">tapis de tir</a> protège l'établi et la crosse pendant le démontage.</span>
                </li>
                <li>
                    <span class="glab-takeaway-label">Chambre vide</span>
                    <span>Les <a href="{{ route('products.show', 'lot-2-etiquettes-chambre-vide-brodees-rouges-porte-cles-securite-fusil-pistolet-universel') }}">étiquettes chambre vide</a> rendent l'arme visiblement sûre.</span>
                </li>
                <li>
                    <span class="glab-takeaway-label">Ligne</span>
                    <span>Un <a href="{{ route('categories.show', 'recuperateurs-de-douilles') }}">récupérateur de douilles</a> garde la ligne propre.</span>
                </li>
                <li>
                    <span class="glab-takeaway-label">Rangement</span>
                    <span>Une <a href="{{ route('categories.show', 'boites-munitions') }}">boîte de munitions</a> range ce qui attend la prochaine séance.</span>
                </li>
            </ul>

            <p class="glab-more-reading">
                Pour aller plus loin, notre article
                <a href="{{ route('blog.show', 'nettoyer-son-canon-quelle-corde-pour-quel-calibre') }}">Nettoyer son canon : quelle corde pour quel calibre</a>
                détaille corde par corde.
            </p>
        </section>

        {{-- The questions people actually ask. --}}
        <section class="glab-faq" aria-labelledby="glab-faq-title">
            <h2 class="glab-title" id="glab-faq-title">Questions <span class="glab-title-accent">fréquentes</span></h2>
            <details>
                <summary>La corde remplace-t-elle le kit à tiges ?</summary>
                <div>
                    <p>Non, elle le complète. La corde fait l'essentiel en deux minutes au stand ; les tiges, brosses et écouvillons du kit font le nettoyage complet à l'établi, chambre comprise. Le kit couvre les calibres .22, 9 mm, .40 et .357 ; hors de ces calibres, la corde du calibre reste l'outil principal.</p>
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
                    <p>De la chambre vers la bouche, dans le sens du projectile. On protège ainsi le couronnement, le dernier point d'appui de la balle : abîmé, il coûte de la précision qu'aucun nettoyage ne rendra.</p>
                </div>
            </details>
            <details>
                <summary>À quelle fréquence nettoyer ?</summary>
                <div>
                    <p>Un passage de corde après chaque séance suffit pour l'entretien courant ; un nettoyage complet à l'établi de temps en temps, et toujours avant un stockage prolongé. La corde elle-même se lave à l'eau savonneuse et se réutilise.</p>
                </div>
            </details>

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
    <script src="{{ versioned_asset('js/guides.js') }}" defer></script>
@endpush
