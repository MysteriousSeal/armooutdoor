@extends('layouts.app')

@section('title', 'À propos — '.config('app.name'))
@section('meta_description', 'Armo Outdoor, boutique française à taille humaine : cibles, matériel de stand, vêtements et kit terrain. Qui nous sommes, et pourquoi la boutique existe.')
@section('canonical', route('about'))

@push('head')
    <link rel="stylesheet" href="{{ versioned_asset('css/help.css') }}">
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'AboutPage',
            'name' => 'À propos d\'Armo Outdoor',
            'description' => 'Qui est Armo Outdoor et ce que la boutique vend.',
            'url' => route('about'),
            'inLanguage' => 'fr-FR',
            'about' => \App\Support\OrganizationSchema::reference(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@@type' => 'ListItem', 'position' => 1, 'name' => __('store.breadcrumb_home'), 'item' => localized_route('home')],
                ['@@type' => 'ListItem', 'position' => 2, 'name' => 'Aide', 'item' => route('faq')],
                ['@@type' => 'ListItem', 'position' => 3, 'name' => 'À propos', 'item' => route('about')],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('content')
    <div class="container help-wrap">
        @include('help.partials.chrome', [
            'title' => "À propos d'Armo Outdoor",
            'lede' => 'Une boutique française à taille humaine, pour le stand et le terrain.',
        ])

        <div class="help-layout">
            @include('help.partials.nav')

            <div class="help-main">
            <article>
            <section class="help-card" id="la-boutique" aria-labelledby="about-shop-title">
                <h2 id="about-shop-title">La boutique</h2>
                <p>
                    Armo Outdoor est une boutique en ligne française de matériel pour le stand de tir et
                    l'outdoor. Le catalogue reste volontairement court : chaque pièce est choisie pour servir
                    sur la ligne ou sur le chemin, puis rentrer dans le sac. Rien ici n'est de la mode de
                    saison, et les prix sont affichés TTC : on sait ce que l'on paie avant d'ouvrir le panier.
                </p>
                <p>
                    <a href="{{ route('categories.index') }}">Les rayons</a> : cibles réactives et planches, entretien et accessoires de stand, vêtements de
                    terrain, kit outdoor, petit matériel du quotidien, munitions et consommables, optiques
                    d'observation.
                </p>
            </section>

            <section class="help-card" id="pourquoi" aria-labelledby="about-why-title">
                <h2 id="about-why-title">Pourquoi avoir créé Armo Outdoor</h2>
                <p>
                    Armo Outdoor est née d'une petite équipe passionnée de tir sportif depuis plus de
                    quinze ans. Le parcours classique, fait dans l'ordre : les répliques d'airsoft
                    d'abord, puis les airguns à billes d'acier et à plombs diabolo, puis les armes à feu
                    au stand, du .22LR au .308, sans oublier le traditionnel 9mm. Quinze ans de pas de tir, c'est aussi quinze ans de petit matériel : des
                    cibles qui se décollent, des pastilles introuvables au bon format, des accessoires
                    commandés sur des places de marché sans visage et reçus sans garantie qu'ils servent.
                </p>
                <p>
                    La boutique est la réponse : réunir au même endroit le petit matériel qui fait la
                    différence au stand comme sur le terrain. Les cibles bien sûr, mais aussi l'entretien
                    des armes (cordes, kits et accessoires de nettoyage) et tout ce qui gravite autour de
                    l'arme et du pas de tir : étuis, boîtes de rangement, accessoires de stand. Le tout
                    choisi par des gens qui pratiquent, testé avant d'entrer au catalogue, et vendu par une
                    équipe en France qui lit chaque message. Ce qui ne sert pas sur la ligne ou sur le
                    chemin n'entre pas au catalogue.
                </p>
                <p>
                    Sans oublier nos amis chasseurs : casquettes, bonnets et chapeaux boonie, cagoules et
                    cache-cou dans tous les camouflages (forêt, désert, multicam), gants, ceintures et
                    t-shirts assortis, ruban camo pour habiller l'équipement, et le nécessaire de l'affût
                    au chemin : sac à dos, boussole, allume-feu, jusqu'aux télémètres et longues-vues pour
                    lire le terrain à longue distance.
                </p>
            </section>

            <section class="help-card" id="engagements" aria-labelledby="about-values-title">
                <h2 id="about-values-title">Nos engagements</h2>
                <ul class="help-points">
                    @foreach ([
                        ['Des produits utiles', 'Du matériel choisi pour sa qualité et son utilité sur le terrain.'],
                        ['Des prix justes toute l\'année', 'Pas de fausses promotions.'],
                        ['Une expédition rapide et suivie', 'Colis préparé avec soin, puis suivi jusqu\'à la porte ou au relais.'],
                        ['Un support réactif', 'Une équipe basée en France, à votre écoute.'],
                    ] as [$pointTitle, $pointText])
                        <li class="help-point">
                            <span class="help-point-mark" aria-hidden="true">
                                @include('partials.icon', ['name' => 'circle-check', 'size' => 16])
                            </span>
                            <span class="help-point-copy">
                                <strong>{{ $pointTitle }}</strong>
                                <span>{{ $pointText }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="help-card" id="livraison-paiement" aria-labelledby="about-practical-title">
                <h2 id="about-practical-title">Livraison &amp; paiement</h2>
                <p>
                    Les commandes partent en France métropolitaine, à domicile ou en point relais, avec
                    Colissimo, Chronopost, Mondial Relay, Chronopost Shop2Shop et, pour les articles petits et légers, Lettre suivie. Le paiement se règle par
                    carte bancaire, traité par un prestataire sécurisé.
                </p>
                <p>
                    Le détail vit sur ses propres pages :
                    <a href="{{ route('help.shipping-returns') }}">Livraison &amp; Retours</a> et
                    <a href="{{ route('help.secure-payment') }}">Paiement sécurisé</a>.
                </p>
            </section>

            <section class="help-card" id="qui-sommes-nous" aria-labelledby="about-company-title">
                <h2 id="about-company-title">Qui sommes-nous</h2>
                <p>
                    La boutique est éditée par {{ $company->value('company_name') }}. L'identité complète de
                    l'entreprise (adresse, immatriculation, contact) vit dans les
                    <a href="{{ route('legal.notice') }}">mentions légales</a>.
                </p>
                <p>
                    Une question ? <a href="{{ localized_route('contact.show') }}">Écrivez-nous</a> : on vous
                    répond au plus vite.
                </p>
            </section>
            </article>
            </div>
        </div>
    </div>
@endsection
