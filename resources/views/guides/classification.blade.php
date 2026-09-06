@extends('layouts.app')

@section('title', 'Classer son arme : catégories D, C et A — Armo Outdoor')
@section('meta_description', 'Sous 2 joules, de 2 à 20, dès 20 : ce que la loi française range en catégories D, C et A, ce qu\'il faut pour acheter, transporter, et le piège du chargeur.')
@section('og_type', 'article')
@section('canonical', route('guides.classification'))

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/guides/guides.css') }}">
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'Article',
            'headline' => 'Classer son arme : catégories D, C et A',
            'description' => 'Sous 2 joules, de 2 à 20, dès 20 : ce que la loi française range en catégories D, C et A, ce qu\'il faut pour acheter, transporter, et le piège du chargeur.',
            'mainEntityOfPage' => route('guides.classification'),
            'inLanguage' => 'fr-FR',
            'datePublished' => '2026-09-06',
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
                ['À 20 joules exactement, catégorie D ou C ?', 'Catégorie C. Le texte dit « supérieure ou égale à 20 joules ». Un modèle annoncé 20 J n\'est plus en vente libre : c\'est la première arme soumise à déclaration. Les fabricants qui visent le marché français calibrent à 19,9 J.'],
                ['Une réplique d\'airsoft, c\'est quelle catégorie ?', 'Aucune. Sous 2 joules, l\'objet n\'est pas juridiquement une arme. Les répliques du commerce français sont conçues pour rester sous cette barre. La vente aux mineurs est interdite dès 0,08 J.'],
                ['Vente libre, donc transport libre ?', 'Non. En catégorie D, l\'achat est libre pour un majeur ; le port et le transport exigent un motif légitime. Sans ce motif : un an et 15 000 euros. En catégorie C : deux ans et 30 000 euros, sans amende forfaitaire.'],
                ['Un chargeur peut-il changer la catégorie ?', 'Oui. Une carabine semi-automatique à percussion centrale est en B avec un chargeur de 10 cartouches, en A1 dès qu\'un chargeur de plus de 10 y est inséré. Le chargeur lui-même peut déjà être classé en A1.'],
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
                ['@@type' => 'ListItem', 'position' => 3, 'name' => 'Classer son arme', 'item' => route('guides.classification')],
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
            <span>Classer son arme</span>
        </nav>

        <header class="glab-head">
            <p class="glab-head-kicker">Réglementation</p>
            <h1 class="glab-head-title">Classer <span class="glab-title-accent">son arme</span></h1>
            <p class="glab-head-lede">
                Sous 2 joules, de 2 à 20, dès 20 : trois seuils, trois régimes. Ce guide relie
                ce que la boutique a écrit sur les catégories D, C et A, pour savoir où se
                situe la vôtre avant d'ouvrir le panier.
            </p>
        </header>

        <p class="glab-warning">
            Ceci décrit l'état du droit à la date de publication et n'est pas un conseil
            juridique. Pour une arme, un chargeur ou une situation précise, le service des
            armes de votre préfecture est l'interlocuteur compétent.
        </p>

        <section class="glab-panel" data-glab-selector aria-labelledby="glab-selector-title">
            <h2 class="glab-title" id="glab-selector-title">Deux réponses, <span class="glab-title-accent">votre régime</span></h2>
            <p class="glab-lede">
                Répondez et les trois articles se réduisent à l'essentiel.
            </p>

            <div class="glab-steps">
                <fieldset class="glab-step">
                    <legend>01 · Quelle énergie, quel objet</legend>
                    <div class="glab-chips glab-chips--stacked" data-glab-group="arme">
                        <button type="button" data-glab-value="as">
                            Sous 2 J
                            <small>airsoft, hors catégorie</small>
                        </button>
                        <button type="button" data-glab-value="d" class="is-active">
                            2 à 20 J
                            <small>air comprimé, paintball</small>
                        </button>
                        <button type="button" data-glab-value="c">
                            20 J et plus
                            <small>déclaration, fusil de chasse</small>
                        </button>
                        <button type="button" data-glab-value="b">
                            Semi-auto, poing, chargeur
                            <small>catégories B et A</small>
                        </button>
                    </div>
                </fieldset>
                <fieldset class="glab-step">
                    <legend>02 · Ce que vous voulez savoir</legend>
                    <div class="glab-chips glab-chips--stacked" data-glab-group="but">
                        <button type="button" data-glab-value="acheter" class="is-active">
                            L'acheter
                            <small>titre, pièce d'identité, SIA</small>
                        </button>
                        <button type="button" data-glab-value="transporter">
                            La transporter
                            <small>port, motif légitime</small>
                        </button>
                        <button type="button" data-glab-value="risque">
                            Ce que je risque
                            <small>peines, chargeur, héritage</small>
                        </button>
                    </div>
                </fieldset>
            </div>

            <div class="glab-reco">
                <p class="glab-reco-label">Notre recommandation</p>
                <p class="glab-reco-resume" data-glab-resume>Pour une arme de 2 à 20 J, à l'achat :</p>
                <ol class="glab-reco-list" data-glab-results>
                    <li>
                        <span class="glab-reco-rank">01</span>
                        <div class="glab-reco-head">
                            <h3>Catégorie D : 2 à 20 joules</h3>
                            <span class="glab-reco-meta">Majeur · pièce d'identité</span>
                        </div>
                        <p>Carabines et pistolets à plombs, billes acier, paintball : l'achat est libre pour un majeur. Le port et le transport, eux, exigent un motif légitime.</p>
                        <a href="{{ route('blog.show', 'categorie-d-ce-que-la-loi-francaise-range-vraiment-dedans-et-ce-que-ca-change-pour-vous') }}">Lire la catégorie D</a>
                    </li>
                    <li>
                        <span class="glab-reco-rank">02</span>
                        <div class="glab-reco-head">
                            <h3>Catégorie C : dès 20 joules</h3>
                            <span class="glab-reco-meta">Licence ou permis · compte SIA</span>
                        </div>
                        <p>À 20 J exactement on change de monde : déclaration, titre de chasse ou de tir, rangement encadré. Une étiquette « 20 joules » n'est plus de la vente libre.</p>
                        <a href="{{ route('blog.show', 'categorie-c-les-armes-soumises-a-declaration-et-tout-ce-qui-va-avec') }}">Lire la catégorie C</a>
                    </li>
                    <li>
                        <span class="glab-reco-rank">03</span>
                        <div class="glab-reco-head">
                            <h3>Hors catégorie : sous 2 joules</h3>
                            <span class="glab-reco-meta">Airsoft · pas une arme</span>
                        </div>
                        <p>Les répliques du commerce français sont conçues pour rester sous 2 J. Ce n'est juridiquement pas une arme. La vente aux mineurs est interdite dès 0,08 J.</p>
                        <a href="{{ route('blog.show', 'airsoft-en-france-ce-que-dit-la-loi-avant-dacheter') }}">Lire l'article</a>
                    </li>
                </ol>
            </div>
        </section>

        <section class="glab-panel" aria-labelledby="glab-table-title">
            <h2 class="glab-title" id="glab-table-title">Cinq régimes, <span class="glab-title-accent">un seul tableau</span></h2>

            <div class="glab-table-wrap">
                <table class="glab-table">
                    <thead>
                        <tr>
                            <th>Catégorie</th>
                            <th>Seuil</th>
                            <th>Pour l'acheter</th>
                            <th>Port et transport</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Hors catégorie</td>
                            <td>Sous 2 J</td>
                            <td>Majeur dès 0,08 J</td>
                            <td>Objet ayant l'apparence d'une arme à feu</td>
                        </tr>
                        <tr>
                            <td>D</td>
                            <td>2 à 20 J</td>
                            <td>Majeur, pièce d'identité</td>
                            <td>Motif légitime ; 1 an / 15 000 €</td>
                        </tr>
                        <tr>
                            <td>C</td>
                            <td>Dès 20 J, chasse, alarme</td>
                            <td>Permis ou licence, compte SIA</td>
                            <td>Motif légitime ; 2 ans / 30 000 €</td>
                        </tr>
                        <tr>
                            <td>B</td>
                            <td>Autorisation (poing, certains semi-auto)</td>
                            <td>Autorisation préfectorale</td>
                            <td>Un chargeur de trop la fait basculer en A</td>
                        </tr>
                        <tr>
                            <td>A</td>
                            <td>Interdit par principe</td>
                            <td>Exception (tireur, État)</td>
                            <td>5 ans / 75 000 € sans titre</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="glab-panel" aria-labelledby="glab-guide-title">
            <h2 class="glab-title" id="glab-guide-title">Les seuils <span class="glab-title-accent">en détail</span></h2>

            <div class="glab-prose">
                <h3>Le classement suit l'énergie mesurée, pas l'étiquette</h3>
                <p>
                    Le droit français ne range pas les armes par « dangerosité ressentie ». Il les
                    range par listes, à l'article R311-2 du code de la sécurité intérieure. Pour
                    l'air comprimé, le paintball et l'airsoft, trois chiffres suffisent : 0,08 J,
                    2 J, 20 J. Le classement suit l'énergie réellement mesurée à la bouche, pas
                    ce qui est imprimé sur le carton.
                </p>

                <dl class="glab-specs">
                    <div>
                        <dt>Sous 2 J <em>hors catégorie</em></dt>
                        <dd>Pas une arme. C'est le régime de l'airsoft, et de quelques pistolets à billes très faibles. La vente aux mineurs s'arrête dès 0,08 J.</dd>
                    </div>
                    <div>
                        <dt>2 à 20 J <em>catégorie D</em></dt>
                        <dd>Carabines et pistolets à plombs, billes acier, lanceurs de paintball, et leurs projectiles. Achat libre pour un majeur.</dd>
                    </div>
                    <div>
                        <dt>Dès 20 J <em>catégorie C</em></dt>
                        <dd>« Supérieure ou égale » : 20 J pile, c'est déjà la C. Licence de tir ou permis de chasser validé, compte SIA, déclaration.</dd>
                    </div>
                </dl>

                <h3>Catégorie D : libre à l'achat, jamais dans la rue</h3>
                <p>
                    Une pièce d'identité prouvant la majorité, et c'est terminé. Pas de licence,
                    pas de compte SIA, pas de déclaration. Un mineur d'au moins 9 ans peut
                    <em>détenir</em> (pas acheter) sous autorisation parentale et licence de club :
                    acheter une carabine « pour le jardin » ne rentre pas dans ce cadre.
                </p>
                <p>
                    Le piège est dehors. Le port (arme immédiatement utilisable) et le transport
                    (arme qui ne l'est pas) exigent un motif légitime, les deux. Une carabine
                    démontée, chargeur vide, plombs à part, housse fermée au fond du coffre :
                    transport. La même, armée sur la banquette : port. Sans motif, un an et
                    15 000 euros ; une amende forfaitaire de 500 euros peut éteindre l'action
                    publique si l'on remet l'objet, sauf s'il s'agit d'une arme à feu.
                </p>
                <p>
                    <a href="{{ route('blog.show', 'categorie-d-ce-que-la-loi-francaise-range-vraiment-dedans-et-ce-que-ca-change-pour-vous') }}">Catégorie D : ce que la loi range vraiment dedans</a>
                    déroule la liste des douze entrées, le lieu de tir, et les trois idées reçues
                    à jeter.
                </p>

                <h3>Catégorie C : cinq joules d'écart, un autre monde</h3>
                <p>
                    Deux carabines 4,5 mm sur le même râtelier : 19 J contre pièce d'identité,
                    24 J contre permis, numéro SIA, déclaration et rangement. Il n'y a pas de
                    troisième voie à l'achat. Tout passe par un compte détenteur sur le portail
                    du ministère, via FranceConnect. Le rangement n'est pas facultatif : coffre
                    ou armoire forte adaptés, ou un élément démonté conservé à part ; les
                    munitions, séparément.
                </p>
                <p>
                    Hériter ou trouver un fusil ouvre une voie propre : déclarer sans délai sur
                    un compte SIA « héritier », puis un certificat médical sous trois mois. On
                    peut conserver l'arme, pas acheter de munitions. La laisser au grenier est
                    un délit : deux ans et 30 000 euros, comme toute détention de catégorie C
                    sans déclaration.
                </p>
                <p>
                    <a href="{{ route('blog.show', 'categorie-c-les-armes-soumises-a-declaration-et-tout-ce-qui-va-avec') }}">Catégorie C : les armes soumises à déclaration</a>
                    dit les douze entrées, le râtelier numérique, et ce que change le 5 janvier 2026
                    pour les anciens licenciés sans compte.
                </p>

                <h3>Catégorie A : l'interdiction, et le chargeur qui bascule</h3>
                <p>
                    L'A2 (matériels de guerre, armes automatiques, munitions perforantes,
                    explosives ou incendiaires) est réservée à l'État. L'A1 est une liste
                    d'armes interdites par leurs caractéristiques, dont certaines ne le sont
                    que dans un état donné. Une carabine semi-automatique à percussion centrale
                    est en B avec un chargeur de 10, en A1 dès qu'un chargeur de plus de 10 y
                    est inséré. Le chargeur seul, au-delà de 10 cartouches pour cette arme, est
                    déjà classé A1.
                </p>
                <p>
                    Le tireur licencié à la FFT a une porte : autorisation, avis fédéral,
                    coffre-fort, usage au stand. Hors de ce cadre, acquérir ou détenir du A ou
                    du B sans titre : cinq ans et 75 000 euros. Le décret du 5 septembre 2025
                    a fait passer certains couteaux à lame fixe et les coups de poing américains
                    postérieurs à 1900 de la D à l'A1, avec trois mois pour s'en défaire.
                </p>
                <p>
                    <a href="{{ route('blog.show', 'categorie-a-ce-qui-est-interdit-a-qui-et-comment-une-arme-b-y-bascule-dun-chargeur') }}">Catégorie A : ce qui est interdit, et comment une arme B y bascule</a>
                    détaille A1 et A2, les seuils de chargeur, et les exceptions.
                </p>

                <h3>Ce que vend la boutique</h3>
                <p>
                    Les <a href="{{ route('categories.show', 'repliques-airsoft') }}">répliques airsoft</a>
                    du rayon se situent sous 2 J, donc hors catégorie. Une réplique modifiée
                    pour franchir 2 J bascule en D, avec toutes les obligations décrites plus
                    haut, souvent sans que son propriétaire le sache. Pour le reste du stand,
                    <a href="{{ route('guides.cibles') }}">bien choisir sa cible</a> et
                    <a href="{{ route('guides.entretien') }}">entretenir son arme</a>.
                </p>
            </div>
        </section>

        <section class="glab-panel" aria-labelledby="glab-faq-title">
            <h2 class="glab-title" id="glab-faq-title">Questions <span class="glab-title-accent">fréquentes</span></h2>

            <div class="glab-faq">
                <details>
                    <summary>À 20 joules exactement, catégorie D ou C ?</summary>
                    <div>
                        <p>Catégorie C. Le texte dit « supérieure ou égale à 20 joules ». Un modèle annoncé 20 J n'est plus en vente libre : c'est la première arme soumise à déclaration. Les fabricants qui visent le marché français calibrent à 19,9 J.</p>
                    </div>
                </details>
                <details>
                    <summary>Une réplique d'airsoft, c'est quelle catégorie ?</summary>
                    <div>
                        <p>Aucune. Sous 2 joules, l'objet n'est pas juridiquement une arme. Les répliques du commerce français sont conçues pour rester sous cette barre. La vente aux mineurs est interdite dès 0,08 J.</p>
                    </div>
                </details>
                <details>
                    <summary>Vente libre, donc transport libre ?</summary>
                    <div>
                        <p>Non. En catégorie D, l'achat est libre pour un majeur ; le port et le transport exigent un motif légitime. Sans ce motif : un an et 15 000 euros. En catégorie C : deux ans et 30 000 euros, sans amende forfaitaire.</p>
                    </div>
                </details>
                <details>
                    <summary>Un chargeur peut-il changer la catégorie ?</summary>
                    <div>
                        <p>Oui. Une carabine semi-automatique à percussion centrale est en B avec un chargeur de 10 cartouches, en A1 dès qu'un chargeur de plus de 10 y est inséré. Le chargeur lui-même peut déjà être classé en A1.</p>
                    </div>
                </details>
            </div>

            <p class="glab-more-reading">
                Les trois articles, dans l'ordre des seuils :
                <a href="{{ route('blog.show', 'categorie-d-ce-que-la-loi-francaise-range-vraiment-dedans-et-ce-que-ca-change-pour-vous') }}">Catégorie D</a>,
                <a href="{{ route('blog.show', 'categorie-c-les-armes-soumises-a-declaration-et-tout-ce-qui-va-avec') }}">Catégorie C</a>,
                <a href="{{ route('blog.show', 'categorie-a-ce-qui-est-interdit-a-qui-et-comment-une-arme-b-y-bascule-dun-chargeur') }}">Catégorie A</a>.
            </p>

            <p class="glab-ctas">
                <a href="{{ route('categories.show', 'repliques-airsoft') }}" class="btn btn-primary">Voir les répliques</a>
                <a href="{{ route('blog.index') }}" class="btn btn-secondary">Tout le blog</a>
            </p>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/guides/classification.js') }}" defer></script>
@endpush
