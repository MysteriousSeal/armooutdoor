<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * The shop's own vocabulary, and where each word is met.
 *
 * Not a dictionary: a legend for the catalogue. Every entry points back at
 * the filter, the rayon or the guide the word actually appears in, so a
 * reader who has just met « AEG » on a product page can go straight to the
 * répliques that answer to it. That link is the thing no general airsoft
 * glossary can write, because it is made of this catalogue.
 */
class Glossary
{
    /**
     * Where a word is met. `filter` entries carry the catalogue filter they
     * belong to and are counted against live stock; the rest simply link.
     *
     * @return list<array<string, mixed>>
     */
    public static function entries(): array
    {
        return [
            [
                'term' => 'AEG',
                'aliases' => ['Automatic Electric Gun', 'électrique'],
                'definition' => 'Automatic Electric Gun : une réplique dont un moteur électrique arme le ressort à chaque coup, alimentée par une batterie. C\'est la propulsion la plus courante en partie, parce qu\'elle tire en rafale et se moque du froid.',
                'filter' => ['category' => 'repliques-airsoft', 'label' => 'Propulsion', 'value' => 'Électrique (AEG)', 'see' => 'les répliques électriques'],
            ],
            [
                'term' => 'Allume-feu à magnésium',
                'aliases' => ['pierre à feu', 'firesteel', 'bushcraft'],
                'definition' => 'Barreau de magnésium et tige d\'alliage ferreux : on gratte le premier pour faire des copeaux, on frappe le second pour les enflammer. Il fonctionne mouillé, ne s\'épuise pas comme un briquet, et demande de l\'entraînement avant d\'en dépendre.',
                'link' => ['kind' => 'Rayon', 'label' => 'Allumes feu', 'category' => 'allumes-feu'],
            ],
            [
                'term' => 'Auto-agrippant',
                'aliases' => ['velcro', 'scratch', 'hook and loop'],
                'definition' => 'La bande à crochets et sa contrepartie bouclée, qui se collent l\'une à l\'autre. C\'est ce qui tient un patch sur une casquette, un étui sur un gilet, et ce qui distingue un ruban camo réutilisable d\'un adhésif jetable.',
                'link' => ['kind' => 'Rayon', 'label' => 'Patches', 'category' => 'patches'],
            ],
            [
                'term' => 'Bungee',
                'aliases' => ['élastique', 'amortisseur'],
                'definition' => 'Section élastique intégrée à une sangle, qui absorbe le débattement quand on épaule ou qu\'on court. Elle rend la sangle plus confortable en mouvement, au prix d\'un maintien moins ferme à l\'arrêt.',
                'link' => ['kind' => 'Rayon', 'label' => 'Sangles', 'category' => 'sangles'],
            ],
            [
                'term' => 'Camouflage CP',
                'aliases' => ['ACU', 'multicam', 'motif', 'désert', 'jungle'],
                'definition' => 'Les motifs se désignent par des sigles : CP pour le camouflage à taches multi-terrains, ACU pour le motif pixellisé gris-vert. À côté d\'eux vivent des familles nommées par leur milieu, désert, jungle, forêt, neige, et des unis comme le coyote ou le tan.',
                'link' => ['kind' => 'Rayon', 'label' => 'Rubans camo', 'category' => 'ruban-camo'],
            ],
            [
                'term' => 'Cartouchière de crosse',
                'aliases' => ['porte-cartouches', 'buttstock'],
                'definition' => 'Bande à passants qui se sangle sur la crosse et porte quelques cartouches à portée de main. Elle évite de fouiller une poche entre deux séries, et se monte sans outil.',
                'link' => ['kind' => 'Rayon', 'label' => 'Cartouchières de crosse', 'category' => 'cartouchieres-de-crosse'],
            ],
            [
                'term' => 'Holster',
                'aliases' => ['étui de ceinture', 'de hanche'],
                'definition' => 'L\'étui qui porte une arme de poing sur la ceinture ou la cuisse, et qui la retient. Se choisit sur le modèle qu\'il doit accueillir : un holster trop lâche ne retient rien, un holster trop serré ne rend pas l\'arme.',
                'link' => ['kind' => 'Rayon', 'label' => 'Holsters', 'category' => 'holsters'],
            ],
            [
                'term' => 'Keffieh',
                'aliases' => ['chèche', 'shemagh', 'foulard'],
                'definition' => 'Grand foulard de coton à franges, porté au cou ou sur la tête. Il coupe le vent, le sable et le soleil, sèche vite, et sert accessoirement de serviette ou de sangle de fortune.',
                'link' => ['kind' => 'Rayon', 'label' => 'Écharpes et foulards', 'category' => 'echarpes-et-foulards'],
            ],
            [
                'term' => 'Longue-vue',
                'aliases' => ['spotting scope', '25-75x60'],
                'definition' => 'Lunette d\'observation à fort grossissement, montée sur trépied, avec laquelle on lit les impacts sans quitter le pas de tir. La notation 25-75x60 se lit : grossissement réglable de 25 à 75 fois, objectif de 60 mm de diamètre.',
                'link' => ['kind' => 'Rayon', 'label' => 'Longues-vues', 'category' => 'longues-vues'],
            ],
            [
                'term' => 'M-LOK',
                'aliases' => ['rail', 'interface'],
                'definition' => 'Interface de fixation à fentes oblongues, ouverte par Magpul, que portent beaucoup de garde-mains et de crosses. Un accessoire M-LOK se boulonne directement dans la fente, sans rail intermédiaire.',
                'link' => ['kind' => 'Rayon', 'label' => 'Accessoires de l\'arme', 'category' => 'accessoires-de-l-arme'],
            ],
            [
                'term' => 'MOLLE',
                'aliases' => ['PALS', 'sanglage'],
                'definition' => 'Le quadrillage de sangles cousues sur un sac ou un gilet, dans lequel on tisse une poche pour la fixer. Rien ne se visse : tout se tresse, et se retire de la même façon.',
                'link' => ['kind' => 'Rayon', 'label' => 'Poches et étuis', 'category' => 'poches-etuis'],
            ],
            [
                'term' => 'Paracorde',
                'aliases' => ['550', 'brins', 'cordage'],
                'definition' => 'Cordage tressé de quelques millimètres, d\'abord fait pour les suspentes de parachute, dont la gaine abrite plusieurs brins internes. On la tresse en bracelet ou en sangle, et on la détresse le jour où on a besoin de la corde.',
                'link' => ['kind' => 'Rayon', 'label' => 'Survie', 'category' => 'sacs'],
            ],
            [
                'term' => 'QD',
                'aliases' => ['quick detach', 'attache rapide'],
                'definition' => 'Quick Detach : l\'attache à bouton-poussoir qui libère une sangle d\'une main, sans la déboucler. Elle demande un logement QD sur l\'arme, ou un adaptateur qui en fournit un.',
                'link' => ['kind' => 'Rayon', 'label' => 'Sangles', 'category' => 'sangles'],
            ],
            [
                'term' => 'Sangle 1, 2 ou 3 points',
                'aliases' => ['bretelle', 'points d\'attache'],
                'definition' => 'Le nombre d\'endroits par lesquels la sangle tient l\'arme. Un point laisse l\'arme pendre et pivoter librement, deux la plaquent au corps et la stabilisent, trois combinent les deux au prix d\'un réglage plus long.',
                'link' => ['kind' => 'Rayon', 'label' => 'Sangles', 'category' => 'sangles'],
            ],
            [
                'term' => 'Télémètre',
                'aliases' => ['laser', 'distance'],
                'definition' => 'Appareil qui mesure la distance d\'une cible en chronométrant un éclair laser aller-retour. Il évite d\'estimer une portée à l\'œil, ce que personne ne fait bien au-delà de cent mètres.',
                'link' => ['kind' => 'Rayon', 'label' => 'Télémètres', 'category' => 'telemetres'],
            ],
            [
                'term' => 'Bille acier',
                'aliases' => ['BB acier', '4,5 mm'],
                'definition' => 'Projectile sphérique en acier de 4,5 mm, tiré par les pistolets et carabines à CO2. À ne pas confondre avec la bille d\'airsoft, en plastique et de 6 mm : ni le calibre ni l\'énergie ne se ressemblent.',
                'link' => ['kind' => 'Rayon', 'label' => 'Munitions', 'category' => 'plombs-et-billes-d-acier'],
            ],
            [
                'term' => 'Bille bio',
                'aliases' => ['biodégradable', 'PLA'],
                'definition' => 'Bille d\'airsoft en acide polylactique, qui se dégrade en extérieur au lieu de rester au sol. Obligatoire sur la plupart des terrains boisés, et à stocker au sec : l\'humidité l\'attaque avant le terrain.',
                'filter' => ['category' => 'billes-airsoft', 'label' => 'Bio', 'value' => 'Oui', 'see' => 'les billes bio'],
            ],
            [
                'term' => 'Blowback',
                'aliases' => ['GBB', 'gas blowback'],
                'definition' => 'Mécanisme où une partie du gaz recule la culasse à chaque tir, pour le bruit et la secousse. On y gagne la sensation, on y perd en autonomie et en constance : la culasse consomme du gaz qui ne pousse pas la bille.',
                'link' => ['kind' => 'Rayon', 'label' => 'Répliques de poing', 'category' => 'repliques-de-poing'],
            ],
            [
                'term' => 'Calibre',
                'aliases' => ['6 mm', '4,5 mm'],
                'definition' => 'Le diamètre du projectile, et donc celui du canon qui l\'accepte. L\'airsoft tire du 6 mm plastique, les armes à plombs du 4,5 mm. Une corde de nettoyage se choisit sur ce chiffre et sur rien d\'autre.',
                'filter' => ['category' => 'billes-airsoft', 'label' => 'Calibre', 'value' => '6mm', 'see' => 'les billes de 6 mm'],
            ],
            [
                'term' => 'Catégorie D',
                'aliases' => ['vente libre', 'classement'],
                'definition' => 'Le régime des armes développant de 2 à 20 joules : achat libre pour un majeur, mais port et transport soumis à motif légitime. Sous 2 joules, l\'objet n\'est juridiquement pas une arme ; à 20 joules, il passe en catégorie C.',
                'link' => ['kind' => 'Guide', 'label' => 'Classer son arme', 'route' => 'guides.classification'],
            ],
            [
                'term' => 'Chronographe',
                'aliases' => ['chrony', 'chroner'],
                'definition' => 'L\'appareil qui mesure la vitesse de sortie de la bille, en FPS ou en m/s. C\'est lui qui fait foi à l\'entrée d\'un terrain. Une mesure isolée ne vaut rien : on tire cinq à dix billes et on lit la moyenne.',
                'link' => ['kind' => 'Guide', 'label' => 'Joules et FPS', 'route' => 'guides.joules'],
            ],
            [
                'term' => 'CO2',
                'aliases' => ['capsule', 'cartouche 12 g'],
                'definition' => 'Dioxyde de carbone comprimé, vendu en capsules de 12 g pour les répliques de poing et en bouteilles de 88 g pour les carabines. Il pousse fort et régulièrement, mais perd de la puissance quand il fait froid.',
                'filter' => ['category' => 'repliques-airsoft', 'label' => 'Propulsion', 'value' => 'CO2', 'see' => 'les répliques au CO2'],
            ],
            [
                'term' => 'Corde de nettoyage',
                'aliases' => ['bore rope', 'bore snake'],
                'definition' => 'Cordelette lestée portant une brosse en laiton puis une longueur de tissu : elle nettoie le canon en un seul passage, sans démontage. L\'outil du stand, quand le kit à tiges est celui de l\'établi.',
                'link' => ['kind' => 'Guide', 'label' => 'Entretenir son arme', 'route' => 'guides.entretien'],
            ],
            [
                'term' => 'Diabolo',
                'aliases' => ['plomb'],
                'definition' => 'Le plomb en forme de sablier des carabines et pistolets à air comprimé, dont la jupe se dilate dans le canon pour épouser les rayures. C\'est sa forme, pas son poids, qui le distingue de la bille acier.',
                'link' => ['kind' => 'Rayon', 'label' => 'Plombs et billes d\'acier', 'category' => 'plombs-et-billes-d-acier'],
            ],
            [
                'term' => 'FPS',
                'aliases' => ['feet per second', 'pieds par seconde'],
                'definition' => 'Feet per second : la vitesse de la bille en pieds par seconde, ce que lit le chronographe et ce qu\'annoncent les fiches produit. Une vitesse ne dit rien sans le poids de bille qui va avec.',
                'link' => ['kind' => 'Guide', 'label' => 'Joules et FPS', 'route' => 'guides.joules'],
            ],
            [
                'term' => 'Grille graduée',
                'aliases' => ['quadrillage', 'pouces'],
                'definition' => 'Quadrillage imprimé sur la cible, qui permet de mesurer un groupement et de corriger la hausse sans règle. Compté en pouces sur les cibles d\'origine américaine, en centimètres ailleurs.',
                'filter' => ['category' => 'cibles', 'label' => 'Grille', 'value' => 'Graduée', 'see' => 'les cibles à grille graduée'],
            ],
            [
                'term' => 'Hop-up',
                'aliases' => ['rétro-rotation'],
                'definition' => 'Le dispositif qui imprime à la bille une rotation arrière, laquelle la porte plus loin en la faisant planer. Trop serré, il freine la bille : un contrôle au chronographe se fait hop-up relâché.',
                'link' => ['kind' => 'Guide', 'label' => 'Joules et FPS', 'route' => 'guides.joules'],
            ],
            [
                'term' => 'Joule',
                'aliases' => ['énergie', 'puissance'],
                'definition' => 'L\'énergie emportée par le projectile, moitié de la masse fois le carré de la vitesse. C\'est la grandeur avec laquelle la loi française et les règlements de terrain sont écrits, parce qu\'elle tient quelle que soit la bille chargée.',
                'link' => ['kind' => 'Guide', 'label' => 'Joules et FPS', 'route' => 'guides.joules'],
            ],
            [
                'term' => 'MED',
                'aliases' => ['distance minimale d\'engagement'],
                'definition' => 'Distance minimale d\'engagement : le nombre de mètres en deçà duquel une réplique puissante n\'a pas le droit de tirer sur un joueur. Une règle de terrain, jamais une règle de loi, et elle change d\'un terrain à l\'autre.',
                'link' => ['kind' => 'Guide', 'label' => 'Joules et FPS', 'route' => 'guides.joules'],
            ],
            [
                'term' => 'Modérateur de son',
                'aliases' => ['silencieux', 'réducteur'],
                'definition' => 'Tube monté en bout de canon qui étale la détente du gaz et abaisse le bruit. En airsoft il sert aussi de logement à un canon long. Il ne change ni l\'énergie ni le classement de l\'arme.',
                'link' => ['kind' => 'Rayon', 'label' => 'Silencieux et modérateurs', 'category' => 'accessoires-silencieux-moderateurs'],
            ],
            [
                'term' => 'MOA',
                'aliases' => ['minute d\'angle'],
                'definition' => 'Minute d\'angle : le soixantième de degré, soit près de 2,9 cm à 100 mètres. L\'unité dans laquelle se règlent les lunettes et se décrit la précision d\'un groupement.',
                'link' => ['kind' => 'Rayon', 'label' => 'Optiques', 'category' => 'optiques'],
            ],
            [
                'term' => 'Pastille de réparation',
                'aliases' => ['pastille autocollante', 'patch'],
                'definition' => 'Petite gommette opaque que l\'on colle sur un impact pour repartir d\'une cible vierge. Elle multiplie par plusieurs séances la vie d\'une planche de tir.',
                'link' => ['kind' => 'Rayon', 'label' => 'Pastilles autocollantes', 'category' => 'pastilles-autocollantes'],
            ],
            [
                'term' => 'Planche de tir',
                'aliases' => ['carton', 'support'],
                'definition' => 'Feuille cartonnée portant une ou plusieurs cibles, à agrafer sur un porte-cible. Le nombre de cibles par planche décide du nombre de séries que l\'on tire avant d\'en changer.',
                'filter' => ['category' => 'cibles', 'label' => 'Type', 'value' => 'Planche de tir', 'see' => 'les planches de tir'],
            ],
            [
                'term' => 'Point d\'arrêt',
                'aliases' => ['backstop', 'pare-balles'],
                'definition' => 'Ce qui arrête le projectile derrière la cible. Sans lui, il n\'y a pas de tir sûr : c\'est la première chose qu\'un stand installe, et la première qu\'un tir chez soi doit prévoir.',
                'link' => ['kind' => 'Rayon', 'label' => 'Cibles carton & métal', 'category' => 'cibles-carton-metal'],
            ],
            [
                'term' => 'Récupérateur de douilles',
                'aliases' => ['filet à douilles'],
                'definition' => 'Filet ou boîtier fixé près de la fenêtre d\'éjection, qui retient les douilles au lieu de les laisser au sol. Utile pour qui recharge, et pour qui tire ailleurs que sur un stand balayé.',
                'link' => ['kind' => 'Article', 'label' => 'Récupérateur de douilles', 'post' => 'recuperateur-de-douilles-a-quoi-ca-sert-vraiment'],
            ],
            [
                'term' => 'Réplique',
                'aliases' => ['airsoft'],
                'definition' => 'Une arme d\'airsoft, tirant des billes de 6 mm sous 2 joules. Le mot n\'est pas une pudeur : sous ce seuil l\'objet n\'est juridiquement pas une arme, et il ne se transporte pas pour autant n\'importe comment.',
                'link' => ['kind' => 'Rayon', 'label' => 'Répliques airsoft', 'category' => 'repliques-airsoft'],
            ],
            [
                'term' => 'Ressort',
                'aliases' => ['spring', 'manuel'],
                'definition' => 'Propulsion où le tireur arme lui-même le ressort avant chaque coup. Un coup par armement, aucune batterie, aucun gaz : c\'est la mécanique la plus simple et la moins chère du rayon.',
                'filter' => ['category' => 'repliques-airsoft', 'label' => 'Propulsion', 'value' => 'Ressort', 'see' => 'les répliques à ressort'],
            ],
            [
                'term' => 'Cible réactive',
                'aliases' => ['autocollante', 'splatter', 'fluorescente'],
                'definition' => 'Cible dont la couche superficielle éclate à l\'impact et découvre un halo fluorescent : on voit où l\'on a touché sans quitter la ligne de tir. La plupart sont autocollantes.',
                'filter' => ['category' => 'cibles', 'label' => 'Fixation', 'value' => 'Autocollante', 'see' => 'les cibles autocollantes'],
            ],
            [
                'term' => 'Témoin de chambre vide',
                'aliases' => ['drapeau de sécurité', 'chamber flag'],
                'definition' => 'Languette de couleur engagée dans la chambre, visible de loin, qui prouve que l\'arme ne peut pas partir. Exigée sur la plupart des stands dès que l\'arme quitte le pas de tir.',
                'link' => ['kind' => 'Rayon', 'label' => 'Témoin de chambre vide', 'category' => 'temoin-de-chambre-vide'],
            ],
            [
                'term' => 'Zones',
                'aliases' => ['anneaux', 'cotation'],
                'definition' => 'Les anneaux concentriques d\'une cible et les points qu\'ils valent. Cinq zones suffisent à l\'entraînement décontracté, dix servent à noter une série comme en compétition.',
                'filter' => ['category' => 'cibles', 'label' => 'Zones', 'value' => '5', 'see' => 'les cibles à cinq zones'],
            ],
        ];
    }

    /**
     * The entries, each resolved to a link and, where it names a catalogue
     * filter, to the number of products currently answering to it. A filter
     * that matches nothing keeps its definition and loses its link: a dead
     * end helps nobody.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function resolved(): Collection
    {
        $products = Product::query()->active()->get();
        $slugs = Category::query()->pluck('slug');

        return collect(self::entries())
            ->map(function (array $entry) use ($products, $slugs): array {
                $entry['initial'] = mb_strtoupper(mb_substr($entry['term'], 0, 1));
                $entry['source'] = null;
                $entry['kind'] = null;
                $entry['url'] = null;
                $entry['count'] = null;

                if (isset($entry['filter'])) {
                    $filter = $entry['filter'];
                    $count = $products->filter(fn (Product $product): bool => collect($product->filter_attributes ?? [])
                        ->contains(fn (array $attribute): bool => ($attribute['label'] ?? null) === $filter['label']
                            && ($attribute['value'] ?? null) === $filter['value']))->count();

                    $entry['kind'] = 'filtre';
                    $entry['source'] = 'Filtre · '.$filter['label'];

                    if ($count > 0) {
                        $entry['count'] = $count;
                        $entry['label'] = 'Voir '.$filter['see'];
                        $entry['url'] = localized_route('categories.show', ['category' => $filter['category']])
                            .'?'.http_build_query(['filter' => [$filter['label'] => $filter['value']]]);
                    }

                    return $entry;
                }

                $link = $entry['link'];
                $entry['source'] = $link['kind'];

                // Three kinds of destination, and the page colours them
                // apart: stock, shelf, and the shop's own writing.
                $entry['kind'] = $link['kind'] === 'Rayon' ? 'rayon' : 'lecture';

                // A rayon this instance does not carry loses its link and
                // keeps its definition, the same rule an empty filter obeys.
                if (isset($link['category']) && ! $slugs->contains($link['category'])) {
                    return $entry;
                }

                // The same rule off the catalogue: the control says what it
                // does, not what it is named after.
                $entry['label'] = match ($link['kind']) {
                    'Rayon' => 'Voir le rayon '.$link['label'],
                    'Guide' => 'Lire le guide '.$link['label'],
                    default => 'Lire l\'article',
                };
                $entry['url'] = match (true) {
                    isset($link['category']) => localized_route('categories.show', ['category' => $link['category']]),
                    isset($link['route']) => route($link['route']),
                    default => route('blog.show', $link['post']),
                };

                return $entry;
            })
            ->sortBy(fn (array $entry): string => self::sortKey($entry['term']))
            ->values();
    }

    /** The letters that actually open an entry, in order. */
    public static function initials(): Collection
    {
        return self::resolved()->pluck('initial')->unique()->values();
    }

    /**
     * Accents fold and case is ignored, so « Étui » would file under E
     * rather than after Z.
     */
    private static function sortKey(string $term): string
    {
        return mb_strtolower(
            transliterator_transliterate('Any-Latin; Latin-ASCII', $term) ?: $term
        );
    }
}
