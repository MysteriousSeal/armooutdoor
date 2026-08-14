<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class CreateSubcategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $tree = [
            'targets' => [
                'round-targets' => [
                    'name' => ['fr' => 'Cibles rondes'],
                    'description' => ['fr' => 'Cibles réactives rondes pour le stand.'],
                    'slugs' => [
                        '200-round-targets-fluorescent-splatter-targets-for-precision-shooting-practice',
                        '200-radius-letters-targets-fluorescent-splatter-targets-for-precision-shooting-practice',
                        '200-pink-targets-76mm-fluorescent-splatter-targets-for-precision-shooting-practice',
                        '200-round-orange-green-targets-76mm-fluorescent-splatter-targets-for-precision-shooting-practice',
                        '250-round-targets-76mm-fluorescent-splatter-targets-for-precision-shooting-practice',
                        '100-round-targets-76mm-fluorescent-splatter-targets-for-precision-shooting-practice',
                        '100-round-targets-red-and-black-10-16cm-fluorescent-splatter-targets-for-precision-shooting-practice',
                        '100-orange-round-targets-10-10cm-fluorescent-splatter-targets-for-precision-shooting-practice',
                        '100-black-numbers-round-targets-10-10cm-fluorescent-splatter-targets-for-precision-shooting-practice',
                        'lot-250-cibles-autocollantes-rouges-reactives-rondes-76mm-fluorescentes-entrainement-tir-carabine',
                        'lot-de-200-cibles-fluorescentes-7-62-cm-pour-stand-de-tir',
                    ],
                ],
                'square-targets' => [
                    'name' => ['fr' => 'Cibles carrées'],
                    'description' => ['fr' => 'Grilles, viseurs et losanges pour le réglage.'],
                    'slugs' => [
                        '200-square-grid-51mm-targets-fluorescent-splatter-targets-for-precision-shooting-practice',
                        '200-square-grid-76mm-targets-fluorescent-splatter-targets-for-precision-shooting-practice',
                        '200-crosshair-square-targets-76mm-fluorescent-splatter-targets-for-precision-shooting-practice',
                        '200-square-5-diamonds-targets-76mm-fluorescent-splatter-targets-for-precision-shooting-practice',
                    ],
                ],
                'target-sheets' => [
                    'name' => ['fr' => 'Planches'],
                    'description' => ['fr' => 'Planches et lots de cibles à coller.'],
                    'slugs' => [
                        '50-sheet-fluorescent-splatter-targets-for-precision-shooting-practice',
                        '25-15-20cm-grid-letters-sheet-fluorescent-splatter-targets-for-precision-shooting-practice',
                        '25-20-30cm-42-stickers-sheet-fluorescent-splatter-targets-for-precision-shooting-practice',
                        '10-15-20cm-5-dots-sheet-fluorescent-splatter-targets-for-precision-shooting-practice',
                        'lot-25-cibles-autocollantes-fluorescentes-grille-viseur-15-24-cm-entrainement-tir-interieur-exterieur',
                    ],
                ],
            ],
            'range' => [
                'ammo-boxes' => [
                    'name' => ['fr' => 'Boîtes'],
                    'description' => ['fr' => 'Boîtes de rangement pour cartouches et munitions.'],
                    'slugs' => [
                        '100-round-flip-top-ammo-storage-box-for-380-and-9mm-magazines',
                        'black-40-round-rifle-ammo-storage-box-flip-top-for-22-250-to-7-62x39',
                        'black-100-round-9mm-ammunition-storage-box-with-secure-latch-organizer-case',
                        'green-100-round-9mm-ammunition-storage-box-with-secure-latch-organizer-case',
                        '50-black-round-9mm-ammunition-storage-box-with-secure-latch-organizer-case',
                    ],
                ],
                'gun-care' => [
                    'name' => ['fr' => 'Entretien'],
                    'description' => ['fr' => 'Cordes de nettoyage, kits et drapeaux de chambre.'],
                    'slugs' => [
                        'bore-rope-cleaning-snake-22-223-5-56',
                        '16pcs-tactical-gun-cleaning-kit-universal-brass-rods-for-22-9mm-40-357-caliber',
                        'bore-rope-cleaning-snake-12-gauge',
                        'bore-rope-cleaning-snake-30-308-30-06-300-303-7-62mm',
                        'lot-2-etiquettes-chambre-vide-brodees-rouges-porte-cles-securite-fusil-pistolet-universel',
                        'bore-rope-cleaning-snake-38-357-380-9mm',
                    ],
                ],
                'pouches' => [
                    'name' => ['fr' => 'Poches & étuis'],
                    'description' => ['fr' => 'Étuis, poches MOLLE et sacs de stand.'],
                    'slugs' => [
                        'brown-universal-tactical-phone-case-with-molle-portable-belt-pouch',
                        'black-cp-camouflage-outdoor-bullet-storage-pouch-detachable-adjustable-gun-stock-holder',
                        'black-tactical-padded-handgun-case-double-pistol-soft-storage-pouch',
                        '10-round-12ga-shell-holder-edc-pouch-durable-oxford-cloth-with-buckle-closure',
                        'cp-camouflage-tactical-camouflage-silencer-sleeve-durable-nylon-protective-cover',
                        'black-9-slot-outdoor-tactical-storage-bag-hunting-organizer-pouch',
                        'sac-recuperateur-de-douilles-ar-outdoor-filet-de-tir-noir',
                    ],
                ],
            ],
            'apparel' => [
                'caps' => [
                    'name' => ['fr' => 'Casquettes'],
                    'description' => ['fr' => 'Casquettes, boonie et chapeaux de chasse.'],
                    'slugs' => [
                        'black-unisex-outdoor-snapback-cap-moisture-wicking-quick-dry-breathable-hat',
                        'cp-camouflage-unisex-outdoor-snapback-cap-moisture-wicking-quick-dry-breathable-hat',
                        'shallow-cp-unisex-outdoor-snapback-cap-moisture-wicking-quick-dry-breathable-hat',
                        'army-green-unisex-outdoor-snapback-cap-moisture-wicking-quick-dry-breathable-hat',
                        'cp-camouflage-unisex-side-mesh-sports-cap-breathable-outdoor-hat',
                        'casquette-militaire-camouflage-foret-unisexe-sport-outdoor',
                        'casquette-de-chasse-orange-camouflage-broderie-bois-de-cerf-unisexe-4-saisons',
                        'chapeau-boonie-cp-camouflage-homme-large-bord-randonnee-chasse-peche-outdoor',
                    ],
                ],
                'beanies' => [
                    'name' => ['fr' => 'Bonnets'],
                    'description' => ['fr' => 'Bonnets polaires et bonnets d’hiver.'],
                    'slugs' => [
                        'army-green-unisex-warm-winter-beanie-thick-padded-hat-for-skiing-cycling-running',
                        'black-skull-patch-knitted-beanie-hat-for-men-and-women',
                        'bonnet-polaire-homme-camouflage-chaud-bonnet-crane-hiver-outdoor',
                        'bonnet-polaire-homme-vert-olive-chaud-bonnet-crane-hiver-outdoor',
                        'bonnet-polaire-vert-fluo-unisexe-chaud-double-sport-outdoor-hiver',
                    ],
                ],
                'neckwear' => [
                    'name' => ['fr' => 'Cache-cou'],
                    'description' => ['fr' => 'Cache-cou, cagoules, keffiehs et tours de tête.'],
                    'slugs' => [
                        'army-green-plaid-tartan-lightweight-scarf-for-men-women',
                        'lightweight-tactical-camouflage-head-cover-breathable-sun-protection-turban',
                        'black-unisex-cooling-neck-gaiter-breathable-face-mask-scarf-for-outdoor-sports',
                        'black-camouflage-breathable-motorcycle-full-face-mask-neck-gaiter-for-cycling-outdoor-sports',
                        'cache-cou-camouflage-python-noir-unisexe-respirant-sport-outdoor-4-saisons',
                        'cache-cou-camouflage-scorpion-unisexe-respirant-sechage-rapide-outdoor',
                        'cache-cou-camouflage-foret-unisexe-respirant-sechage-rapide-outdoor',
                    ],
                ],
                'belts' => [
                    'name' => ['fr' => 'Ceintures'],
                    'description' => ['fr' => 'Ceintures tactiques, ceintures de ville et sandales.'],
                    'slugs' => [
                        'sandales-homme-eva-noix-de-coco-couleur-unie-toutes-saisons-legeres-antiderapantes-a-enfiler',
                        'ceinture-tactique-unisexe-noire-reglable-boucle-securite-plastique-randonnee-camping',
                        'ceinture-tactique-unisexe-verte-reglable-boucle-securite-plastique-randonnee-camping',
                        'ceinture-homme-similicuir-noire-boucle-automatique-sans-trou-business-decontractee-2024',
                    ],
                ],
            ],
            'field-gear' => [
                'camo-tape' => [
                    'name' => ['fr' => 'Rubans camo'],
                    'description' => ['fr' => 'Rubans adhésifs pour habiller le matériel.'],
                    'slugs' => [
                        'desert-camo-self-adhesive-sports-tape',
                        'jungle-camo-self-adhesive-sports-tape',
                        'snow-camo-self-adhesive-sports-tape',
                        'acu-camo-self-adhesive-sports-tape',
                        'forest-camo-self-adhesive-sports-tape',
                        'random-camo-self-adhesive-sports-tape',
                    ],
                ],
                'survival' => [
                    'name' => ['fr' => 'Survival'],
                    'description' => ['fr' => 'Boussoles et allume-feu pour le terrain.'],
                    'slugs' => [
                        'multifunctional-outdoor-survival-compass-waterproof-portable-navigation-tool',
                        'magnesium-fire-starter-with-wooden-handle-outdoor-survival-flint-tool',
                        'boussole-jaune-etanche-avec-echelle-acrylique-durable-camping-randonnee-orientation-carte-outdoor',
                    ],
                ],
                'range-kit' => [
                    'name' => ['fr' => 'Stand'],
                    'description' => ['fr' => 'Sangles, tapis, trépied et protections de genoux.'],
                    'slugs' => [
                        'dark-mystic-tactical-single-point-sling-with-qd-buckle',
                        'black-adjustable-quick-release-crossbody-shoulder-strap',
                        'black-portable-mini-adjustable-tripod-32-46cm-with-pan-tilt-head',
                        'black-portable-outdoor-shooting-mat-waterproof-moisture-proof-training-camping-mat',
                        'gray-and-green-multi-purpose-knee-pads-for-sports-gardening-yoga-and-household-use',
                    ],
                ],
            ],
            'everyday' => [
                'stickers' => [
                    'name' => ['fr' => 'Pastilles'],
                    'description' => ['fr' => 'Pastilles autocollantes pour le marquage et le codage.'],
                    'slugs' => [
                        '1000-red-round-colored-dot-stickers-13mm',
                        '1000-white-round-colored-dot-stickers-13mm',
                        '1000-black-round-colored-dot-stickers-13mm',
                        'lot-1000-pastilles-autocollantes-rondes-blanches-19mm-etiquettes-adhesives-codage-couleur',
                        'lot-1000-pastilles-autocollantes-rondes-jaunes-25mm-etiquettes-adhesives-codage-couleur',
                        'lot-1000-pastilles-autocollantes-rouges-jaunes-25mm-etiquettes-adhesives-codage-couleur',
                    ],
                ],
                'watch-bands' => [
                    'name' => ['fr' => 'Bracelets'],
                    'description' => ['fr' => 'Bracelets de montre et bijoux du quotidien.'],
                    'slugs' => [
                        'bracelet-apple-watch-orange-paracorde-nylon-homme-42-44-45-46-49mm',
                        'bracelet-apple-watch-noir-paracorde-nylon-homme-42-44-45-46-49mm',
                        'bracelet-apple-watch-vert-paracorde-nylon-homme-42-44-45-46-49mm',
                        'bracelet-cubain-acier-inoxydable-19cm-homme-femme-couple-resistant-durable-cadeau-mode',
                        'bracelet-corde-tressee-noir-ancre-marine-queue-de-baleine-unisexe-style-ocean',
                        'bracelet-sport-apple-watch-noir-gris-49-45-44-42mm',
                        'bracelet-sport-apple-watch-noir-gris-41-40-38mm',
                        'bracelet-apple-watch-noir-silicone-boucle-parachute-42-44-45-46-49mm',
                    ],
                ],
                'tools' => [
                    'name' => ['fr' => 'Outils'],
                    'description' => ['fr' => 'Tournevis, aiguiseur et organisateur d’atelier.'],
                    'slugs' => [
                        'organisateur-d-outils-vert-roulant-robuste-multi-poches-multi-compartiments-sangle-reglable',
                        'aiguiseur-couteaux-4-en-1-professionnel-tungstene-diamant-ceramique-manuel-cuisine',
                        'jeu-de-tournevis-de-precision-25-en-1-multifonction-reparation-telephone-tablette-pc',
                        'kit-tournevis-precision-25-en-1-multifonction-reparation-telephone-tablette-electronique',
                    ],
                ],
                'tech' => [
                    'name' => ['fr' => 'Tech'],
                    'description' => ['fr' => 'Écouteurs, câbles, micro et le reste des extras.'],
                    'slugs' => [
                        '5-bouchons-de-valve-pneu-alu-noir-universels-voiture-moto-velo-camion-etanches-antipoussiere',
                        'jouet-electrique-pour-chat-balle-bleue-intelligente-roulante-automatique-interactive-divertissement',
                        'micro-cravate-sans-fil-2-4ghz-usb-c-iphone-16-15-android-pc-reduction-bruit-plug-and-play',
                        'ecouteurs-tws-blancs-semi-intra-sans-fil-led-type-c-sport-hd-stereo-micro-reduction-de-bruit',
                        'diffuseur-d-huiles-essentielles-noir-humidificateur-aromatherapie-veilleuse-led-brume-froide',
                        'cable-usb-c-vers-usb-c-charge-rapide-blanc-100cm',
                    ],
                ],
            ],
        ];

        foreach ($tree as $parentSlug => $children) {
            $parent = Category::query()->where('slug', $parentSlug)->first();

            if ($parent === null) {
                continue;
            }

            $index = 0;

            foreach ($children as $slug => $data) {
                $index++;

                $category = Category::query()->updateOrCreate(
                    ['slug' => $slug],
                    [
                        'parent_id' => $parent->id,
                        'name' => $data['name'],
                        'description' => $data['description'],
                        'sort_order' => $index,
                    ],
                );

                Product::query()
                    ->whereIn('slug', $data['slugs'])
                    ->update(['category_id' => $category->id]);
            }
        }
    }
}
