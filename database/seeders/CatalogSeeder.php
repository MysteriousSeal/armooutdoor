<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            [
                'slug' => 'shelters',
                'name' => [
                    'en' => 'Shelters',
                    'fr' => 'Abris',
                ],
                'description' => [
                    'en' => 'Quiet tents and tarps cut for ridgelines and long evenings.',
                    'fr' => 'Tentes et bâches discrètes, pensées pour les crêtes et les longues soirées.',
                ],
                'products' => [
                    [
                        'slug' => 'ridge-tent',
                        'name' => ['en' => 'Ridge Two-Person Tent', 'fr' => 'Tente crête deux places'],
                        'description' => [
                            'en' => 'A low, two-person ridge tent in olive canvas. Double-stitched seams, brass hardware, and a quiet footprint for nights above the treeline.',
                            'fr' => 'Une tente crête basse pour deux personnes, en toile olive. Coutures doubles, quincaillerie en laiton, et une emprise discrète pour les nuits au-dessus de la lisière.',
                        ],
                        'price_cents' => 34900,
                        'image' => 'products/ridge-tent.jpg',
                        'featured' => true,
                    ],
                    [
                        'slug' => 'alpine-tarp',
                        'name' => ['en' => 'Alpine Tarp Shelter', 'fr' => 'Bâche-abri alpine'],
                        'description' => [
                            'en' => 'A rectangular tarp in waxed olive cloth, with brass grommets and hemp lines. Pitch it as a lean-to, an A-frame, or a kitchen fly.',
                            'fr' => 'Une bâche rectangulaire en toile olive cirée, œillets en laiton et cordages en chanvre. Montez-la en appentis, en A, ou en auvent de cuisine.',
                        ],
                        'price_cents' => 12900,
                        'image' => 'products/alpine-tarp.jpg',
                        'featured' => false,
                    ],
                    [
                        'slug' => 'hammock-shelter',
                        'name' => ['en' => 'River Hammock Shelter', 'fr' => 'Hamac-abri rivière'],
                        'description' => [
                            'en' => 'A gathered-end hammock with a short hex fly. For woods with two trees and not much else.',
                            'fr' => 'Un hamac à extrémités froncées, avec une petite bâche hexagonale. Pour les bois où il y a deux arbres, et pas grand-chose d’autre.',
                        ],
                        'price_cents' => 15900,
                        'image' => $this->unsplash('1487730116645-74489c95b41b'),
                        'featured' => false,
                    ],
                    [
                        'slug' => 'bivy-sack',
                        'name' => ['en' => 'Night Bivy Sack', 'fr' => 'Sac bivy de nuit'],
                        'description' => [
                            'en' => 'A breathable bivy for the nights you do not want to pitch. Waterproof floor, mesh face, quiet zip.',
                            'fr' => 'Un bivy respirant pour les nuits où l’on ne monte rien. Plancher étanche, visière en mesh, zip discret.',
                        ],
                        'price_cents' => 8900,
                        'image' => $this->unsplash('1517824806704-9040b037703b'),
                        'featured' => false,
                    ],
                    [
                        'slug' => 'three-season-dome',
                        'name' => ['en' => 'Three-Season Dome', 'fr' => 'Dôme trois saisons'],
                        'description' => [
                            'en' => 'A freestanding two-person dome. Two doors, two vestibules, and poles that remember the way they bend.',
                            'fr' => 'Un dôme autoportant pour deux. Deux portes, deux absides, et des arceaux qui se souviennent de leur courbe.',
                        ],
                        'price_cents' => 27900,
                        'image' => $this->unsplash('1504280390367-361c6d9f38f4'),
                        'featured' => false,
                    ],
                    [
                        'slug' => 'pyramid-mid',
                        'name' => ['en' => 'Single-Pole Pyramid Mid', 'fr' => 'Tipi une perche'],
                        'description' => [
                            'en' => 'A four-sided mid with a single centre pole. Plenty of sitting room, almost no poles to carry.',
                            'fr' => 'Un mid à quatre pans, une seule perche au centre. De la place pour s’asseoir, presque rien à porter.',
                        ],
                        'price_cents' => 21900,
                        'image' => $this->unsplash('1478131143081-80f7f84ca84d'),
                        'featured' => false,
                    ],
                    [
                        'slug' => 'canvas-bell-tent',
                        'name' => ['en' => 'Canvas Bell Tent', 'fr' => 'Tente cloche en toile'],
                        'description' => [
                            'en' => 'A four-metre bell in heavy cotton canvas. For basecamps that last a week, not a night.',
                            'fr' => 'Une cloche de quatre mètres en coton lourd. Pour les camps qui durent une semaine, pas une nuit.',
                        ],
                        'price_cents' => 58900,
                        'image' => $this->unsplash('1537905569824-f89f14cceb68'),
                        'featured' => false,
                    ],
                    [
                        'slug' => 'bothy-bag',
                        'name' => ['en' => 'Four-Person Bothy Bag', 'fr' => 'Bothy bag quatre places'],
                        'description' => [
                            'en' => 'An emergency bothy that sits four, just. For lunch in the wind, or the wait for the cloud to lift.',
                            'fr' => 'Un bothy d’urgence où l’on tient à quatre, tout juste. Pour le déjeuner dans le vent, ou l’attente que le nuage se lève.',
                        ],
                        'price_cents' => 9900,
                        'image' => $this->unsplash('1445308394109-4ec2920981b1'),
                        'featured' => false,
                    ],
                    [
                        'slug' => 'porch-awning',
                        'name' => ['en' => 'Porch Awning Fly', 'fr' => 'Auvent de porche'],
                        'description' => [
                            'en' => 'A simple porch fly that ties to the tent or stands alone. Shade for boots, bags, and a slow breakfast.',
                            'fr' => 'Un auvent simple, attaché à la tente ou dressé seul. De l’ombre pour les chaussures, les sacs, et un petit-déjeuner lent.',
                        ],
                        'price_cents' => 7500,
                        'image' => $this->unsplash('1523987355523-c7b5b0dd90a7'),
                        'featured' => false,
                    ],
                    [
                        'slug' => 'solo-hoop-tent',
                        'name' => ['en' => 'Solo Hoop Tent', 'fr' => 'Tente cerceau solo'],
                        'description' => [
                            'en' => 'One hoop, one person, one long vestibule for wet kit. Light enough to forget it is there.',
                            'fr' => 'Un cerceau, une personne, un long vestibule pour le linge mouillé. Assez légère pour qu’on l’oublie.',
                        ],
                        'price_cents' => 19900,
                        'image' => $this->unsplash('1464822759023-fed622ff2c3b'),
                        'featured' => false,
                    ],
                ],
            ],
            [
                'slug' => 'packs',
                'name' => [
                    'en' => 'Packs',
                    'fr' => 'Sacs',
                ],
                'description' => [
                    'en' => 'Waxed canvas packs for day walks and longer traverses.',
                    'fr' => 'Sacs en toile cirée pour les marches d’un jour et les traversées plus longues.',
                ],
                'products' => [
                    [
                        'slug' => 'daylight-pack',
                        'name' => ['en' => 'Daylight 28L Pack', 'fr' => 'Sac Daylight 28 L'],
                        'description' => [
                            'en' => 'A compact 28-litre daypack in waxed canvas. Enough room for a thermos, a sweater, and the long way home.',
                            'fr' => 'Un sac à dos compact de 28 litres en toile cirée. Assez de place pour un thermos, un pull, et le chemin du retour.',
                        ],
                        'price_cents' => 15900,
                        'image' => 'products/daylight-pack.jpg',
                        'featured' => true,
                    ],
                    [
                        'slug' => 'traverse-pack',
                        'name' => ['en' => 'Traverse 55L Expedition', 'fr' => 'Sac Traverse 55 L expédition'],
                        'description' => [
                            'en' => 'A 55-litre expedition pack with leather straps and a quiet, balanced carry. Built for multi-day walks, not the summit photo.',
                            'fr' => 'Un sac d’expédition de 55 litres, bretelles en cuir et portage calme et équilibré. Conçu pour les marches de plusieurs jours, pas pour la photo du sommet.',
                        ],
                        'price_cents' => 28900,
                        'image' => 'products/traverse-pack.jpg',
                        'featured' => false,
                    ],
                    [
                        'slug' => 'summit-sling',
                        'name' => ['en' => 'Summit Sling 8L', 'fr' => 'Sac sling Summit 8 L'],
                        'description' => [
                            'en' => 'A small sling for the camera, the map, and a roll. Worn across the chest when the big pack stays in camp.',
                            'fr' => 'Un petit sling pour l’appareil, la carte, et un pain. En bandoulière quand le grand sac reste au camp.',
                        ],
                        'price_cents' => 6900,
                        'image' => $this->unsplash('1553062407-98eeb64c6a62'),
                        'featured' => false,
                    ],
                    [
                        'slug' => 'roll-dry-bag',
                        'name' => ['en' => 'Roll-Top Dry Bag 20L', 'fr' => 'Sac étanche roll-top 20 L'],
                        'description' => [
                            'en' => 'A welded dry bag that rolls three times and clips. For river days and the bottom of a wet pack.',
                            'fr' => 'Un sac étanche soudé, trois tours et un clip. Pour les jours de rivière et le fond d’un sac mouillé.',
                        ],
                        'price_cents' => 3900,
                        'image' => $this->unsplash('1491637639811-60e2756cc1c7'),
                        'featured' => false,
                    ],
                    [
                        'slug' => 'trail-belt-pouch',
                        'name' => ['en' => 'Trail Belt Pouch', 'fr' => 'Pochette de ceinture'],
                        'description' => [
                            'en' => 'A small belt pouch for the knife, the compass, and the last square of chocolate.',
                            'fr' => 'Une petite pochette de ceinture pour le couteau, la boussole, et le dernier carré de chocolat.',
                        ],
                        'price_cents' => 3500,
                        'image' => $this->unsplash('1622260614153-03223fb72052'),
                        'featured' => false,
                    ],
                    [
                        'slug' => 'hill-camera-pack',
                        'name' => ['en' => 'Hill Camera Pack', 'fr' => 'Sac photo de colline'],
                        'description' => [
                            'en' => 'A padded 22-litre pack that hides a body and two lenses, then still takes lunch.',
                            'fr' => 'Un sac rembourré de 22 litres qui cache un boîtier et deux objectifs, puis prend encore le déjeuner.',
                        ],
                        'price_cents' => 18900,
                        'image' => $this->unsplash('1551632811-561732d1e306'),
                        'featured' => false,
                    ],
                    [
                        'slug' => 'trail-youth-pack',
                        'name' => ['en' => 'Trail Youth Pack 18L', 'fr' => 'Sac jeunesse Trail 18 L'],
                        'description' => [
                            'en' => 'A lighter 18-litre pack with a shorter back. Same canvas, smaller walk.',
                            'fr' => 'Un sac de 18 litres, dos plus court. La même toile, une plus petite marche.',
                        ],
                        'price_cents' => 9900,
                        'image' => $this->unsplash('1622560480605-d83c853bc5c3'),
                        'featured' => false,
                    ],
                    [
                        'slug' => 'ski-tour-pack',
                        'name' => ['en' => 'Ski Tour Pack 32L', 'fr' => 'Sac ski-randonnée 32 L'],
                        'description' => [
                            'en' => 'A 32-litre tour pack with diagonal ski carry and a shove-it pocket for the skins.',
                            'fr' => 'Un sac de randonnée à ski de 32 litres, portage diagonal et poche pour les peaux.',
                        ],
                        'price_cents' => 22900,
                        'image' => $this->unsplash('1547949003-9792a18a2601'),
                        'featured' => false,
                    ],
                    [
                        'slug' => 'market-tote',
                        'name' => ['en' => 'Market Tote', 'fr' => 'Cabas de marché'],
                        'description' => [
                            'en' => 'A stiff canvas tote for the village run. Handles that sit on the shoulder without a fight.',
                            'fr' => 'Un cabas en toile raide pour la course au village. Des anses qui tiennent à l’épaule sans discuter.',
                        ],
                        'price_cents' => 4900,
                        'image' => $this->unsplash('1571687949921-1306bfb24b72'),
                        'featured' => false,
                    ],
                    [
                        'slug' => 'weekender-duffel',
                        'name' => ['en' => 'Weekender Duffel 45L', 'fr' => 'Duffel week-end 45 L'],
                        'description' => [
                            'en' => 'A 45-litre duffel with a shoulder strap and a quiet zip. For the train, not the trail.',
                            'fr' => 'Un duffel de 45 litres, bandoulière et zip discret. Pour le train, pas pour le sentier.',
                        ],
                        'price_cents' => 13900,
                        'image' => $this->unsplash('1476514525535-07fb3b4ae5f1'),
                        'featured' => false,
                    ],
                ],
            ],
            [
                'slug' => 'apparel',
                'name' => [
                    'en' => 'Apparel',
                    'fr' => 'Vêtements',
                ],
                'description' => [
                    'en' => 'Merino and waxed cotton for weather that changes its mind.',
                    'fr' => 'Mérinos et coton ciré pour un temps qui change d’avis.',
                ],
                'products' => [
                    [
                        'slug' => 'merino-field-shirt',
                        'name' => ['en' => 'Merino Field Shirt', 'fr' => 'Chemise de terrain en mérinos'],
                        'description' => [
                            'en' => 'A midweight merino field shirt in heather taupe. Warm when the wind picks up, breathable when the sun returns.',
                            'fr' => 'Une chemise de terrain en mérinos mi-épais, taupe chiné. Chaude quand le vent se lève, respirante quand le soleil revient.',
                        ],
                        'price_cents' => 11900,
                        'image' => 'products/merino-shirt.jpg',
                        'featured' => true,
                    ],
                    [
                        'slug' => 'waxed-field-jacket',
                        'name' => ['en' => 'Waxed Field Jacket', 'fr' => 'Veste de terrain cirée'],
                        'description' => [
                            'en' => 'A waxed cotton field jacket in olive-brown, with brass snaps and a lining that takes a day to warm and then stays that way.',
                            'fr' => 'Une veste de terrain en coton ciré brun-olive, pressions en laiton, et une doublure qui met une journée à se chauffer — puis reste ainsi.',
                        ],
                        'price_cents' => 24900,
                        'image' => 'products/field-jacket.jpg',
                        'featured' => false,
                    ],
                    [
                        'slug' => 'merino-beanie',
                        'name' => ['en' => 'Merino Watch Beanie', 'fr' => 'Bonnet de quart en mérinos'],
                        'description' => [
                            'en' => 'A close merino beanie that covers the ears and then some. No pom-pom, no logo.',
                            'fr' => 'Un bonnet en mérinos qui couvre les oreilles, et un peu plus. Sans pompon, sans logo.',
                        ],
                        'price_cents' => 3500,
                        'image' => $this->unsplash('1434389677669-e08b4cac3105'),
                        'featured' => false,
                    ],
                    [
                        'slug' => 'trail-trousers',
                        'name' => ['en' => 'Trail Trousers', 'fr' => 'Pantalon de sentier'],
                        'description' => [
                            'en' => 'Cotton-nylon trousers with a deep hem and two real pockets. Cut to walk, not to pose.',
                            'fr' => 'Un pantalon coton-nylon, ourlet profond et deux vraies poches. Coupé pour marcher, pas pour poser.',
                        ],
                        'price_cents' => 12900,
                        'image' => $this->unsplash('1591047139829-d91aecb6caea'),
                        'featured' => false,
                    ],
                    [
                        'slug' => 'wool-overshirt',
                        'name' => ['en' => 'Wool Overshirt', 'fr' => 'Surchemise en laine'],
                        'description' => [
                            'en' => 'A heavy wool overshirt that works as a jacket until it does not. Two chest pockets, horn buttons.',
                            'fr' => 'Une surchemise en laine lourde qui fait office de veste, jusqu’à ce que ce ne soit plus assez. Deux poches poitrine, boutons en corne.',
                        ],
                        'price_cents' => 16900,
                        'image' => $this->unsplash('1544022613-e87ca75a784a'),
                        'featured' => false,
                    ],
                    [
                        'slug' => 'rain-poncho',
                        'name' => ['en' => 'Packable Rain Poncho', 'fr' => 'Poncho de pluie pliable'],
                        'description' => [
                            'en' => 'A long poncho that covers you and the pack. Snaps at the sides, stuffs into its own pocket.',
                            'fr' => 'Un long poncho qui vous couvre, et le sac. Pressions sur les côtés, se range dans sa poche.',
                        ],
                        'price_cents' => 5900,
                        'image' => $this->unsplash('1521223890158-f9f7c3d5d504'),
                        'featured' => false,
                    ],
                    [
                        'slug' => 'liner-gloves',
                        'name' => ['en' => 'Merino Liner Gloves', 'fr' => 'Sous-gants en mérinos'],
                        'description' => [
                            'en' => 'Thin merino liners for under the leather pair, or alone on a cold morning walk.',
                            'fr' => 'De fins sous-gants en mérinos, sous la paire en cuir, ou seuls pour une marche froide.',
                        ],
                        'price_cents' => 2900,
                        'image' => $this->unsplash('1618354691373-d851c5c3a990'),
                        'featured' => false,
                    ],
                    [
                        'slug' => 'camp-socks',
                        'name' => ['en' => 'Camp Sock Pair', 'fr' => 'Paire de chaussettes de camp'],
                        'description' => [
                            'en' => 'A mid-calf merino pair with a reinforced heel. Two days of walking, then the wash bag.',
                            'fr' => 'Une paire mi-mollet en mérinos, talon renforcé. Deux jours de marche, puis le sac de linge.',
                        ],
                        'price_cents' => 1800,
                        'image' => $this->unsplash('1489987707025-afc232f7ea0f'),
                        'featured' => false,
                    ],
                    [
                        'slug' => 'flannel-shirt',
                        'name' => ['en' => 'Checked Flannel Shirt', 'fr' => 'Chemise flanelle à carreaux'],
                        'description' => [
                            'en' => 'A brushed cotton flannel in a quiet check. Soft on day one, better on day thirty.',
                            'fr' => 'Une flanelle de coton brossé, carreaux discrets. Douce dès le premier jour, meilleure au trentième.',
                        ],
                        'price_cents' => 8900,
                        'image' => $this->unsplash('1521572163474-6864f9cf17ab'),
                        'featured' => false,
                    ],
                    [
                        'slug' => 'down-vest',
                        'name' => ['en' => 'Down Camp Vest', 'fr' => 'Gilet de camp en duvet'],
                        'description' => [
                            'en' => 'A light down vest for the hour after the sun drops. Packs into its own pocket.',
                            'fr' => 'Un gilet en duvet léger pour l’heure après le soleil. Se range dans sa poche.',
                        ],
                        'price_cents' => 14900,
                        'image' => $this->unsplash('1418065460487-3e41a6c84dc5'),
                        'featured' => false,
                    ],
                ],
            ],
            [
                'slug' => 'camp-kitchen',
                'name' => [
                    'en' => 'Camp kitchen',
                    'fr' => 'Cuisine de camp',
                ],
                'description' => [
                    'en' => 'Cast iron and enamel for meals that take their time.',
                    'fr' => 'Fonte et émail pour les repas qui prennent leur temps.',
                ],
                'products' => [
                    [
                        'slug' => 'cast-iron-skillet',
                        'name' => ['en' => 'Cast Iron Skillet', 'fr' => 'Poêle en fonte'],
                        'description' => [
                            'en' => 'A seasoned cast iron skillet. Heavy enough to sit still on the fire, wide enough for breakfast for two.',
                            'fr' => 'Une poêle en fonte déjà culottée. Assez lourde pour tenir sur le feu, assez large pour le petit-déjeuner de deux.',
                        ],
                        'price_cents' => 7900,
                        'image' => 'products/cast-iron-skillet.jpg',
                        'featured' => true,
                    ],
                    [
                        'slug' => 'enamel-camp-kettle',
                        'name' => ['en' => 'Enamel Camp Kettle', 'fr' => 'Bouilloire de camp en émail'],
                        'description' => [
                            'en' => 'A cream enamel kettle with a taupe rim. For coffee at first light, and again when the tents are down.',
                            'fr' => 'Une bouilloire en émail crème, liseré taupe. Pour le café à la première lumière, et encore une fois une fois les tentes pliées.',
                        ],
                        'price_cents' => 4500,
                        'image' => 'products/enamel-kettle.jpg',
                        'featured' => false,
                    ],
                    [
                        'slug' => 'nesting-cups',
                        'name' => ['en' => 'Nesting Enamel Cups', 'fr' => 'Tasses émail emboîtables'],
                        'description' => [
                            'en' => 'Two enamel cups that nest. One for coffee, one for soup, both for the wash-up.',
                            'fr' => 'Deux tasses en émail qui s’emboîtent. Une pour le café, une pour la soupe, les deux pour la vaisselle.',
                        ],
                        'price_cents' => 2400,
                        'image' => $this->unsplash('1495474472287-4d71bcdd2085'),
                        'featured' => false,
                    ],
                    [
                        'slug' => 'wooden-spoon-set',
                        'name' => ['en' => 'Carved Spoon Set', 'fr' => 'Set de cuillères sculptées'],
                        'description' => [
                            'en' => 'A beech cooking spoon and a smaller eating spoon. Oil them once, then leave them be.',
                            'fr' => 'Une cuillère de cuisine en hêtre, et une plus petite pour manger. Une huile, puis on les laisse.',
                        ],
                        'price_cents' => 1800,
                        'image' => $this->unsplash('1466637574441-749b8f19452f'),
                        'featured' => false,
                    ],
                    [
                        'slug' => 'folding-grill',
                        'name' => ['en' => 'Folding Fire Grill', 'fr' => 'Grille pliante'],
                        'description' => [
                            'en' => 'A folding steel grill that stands over coals. Four legs, one catch, no fuss.',
                            'fr' => 'Une grille en acier pliante, à poser sur les braises. Quatre pieds, un loquet, rien de plus.',
                        ],
                        'price_cents' => 4200,
                        'image' => $this->unsplash('1590794056226-79ef3a8147e1'),
                        'featured' => false,
                    ],
                    [
                        'slug' => 'pour-over-cone',
                        'name' => ['en' => 'Pour-Over Coffee Cone', 'fr' => 'Cône à café filtre'],
                        'description' => [
                            'en' => 'A steel pour-over that sits on the enamel mug. Filters fold flat beside it.',
                            'fr' => 'Un cône en acier qui pose sur la tasse émail. Les filtres se plient à côté.',
                        ],
                        'price_cents' => 2800,
                        'image' => $this->unsplash('1515442261605-65987783cb6a'),
                        'featured' => false,
                    ],
                    [
                        'slug' => 'tin-plates',
                        'name' => ['en' => 'Enamel Plate Pair', 'fr' => 'Paire d’assiettes émail'],
                        'description' => [
                            'en' => 'Two deep enamel plates with a taupe rim. They stack. They chip, eventually, and look better for it.',
                            'fr' => 'Deux assiettes creuses en émail, liseré taupe. Elles s’empilent. Elles s’écaillent, un jour, et s’en portent mieux.',
                        ],
                        'price_cents' => 3200,
                        'image' => $this->unsplash('1556910103-1c02745aae4d'),
                        'featured' => false,
                    ],
                    [
                        'slug' => 'spice-tin',
                        'name' => ['en' => 'Three-Tin Spice Kit', 'fr' => 'Kit d’épices trois boîtes'],
                        'description' => [
                            'en' => 'Three small tins in a sleeve: salt, pepper, and whatever you actually cook with.',
                            'fr' => 'Trois petites boîtes dans un étui : sel, poivre, et ce avec quoi vous cuisinez vraiment.',
                        ],
                        'price_cents' => 1600,
                        'image' => $this->unsplash('1556909114-f6e7ad7d3136'),
                        'featured' => false,
                    ],
                    [
                        'slug' => 'fire-gloves',
                        'name' => ['en' => 'Leather Fire Gloves', 'fr' => 'Gants de feu en cuir'],
                        'description' => [
                            'en' => 'A pair of split-leather gloves for the pot, the grate, and the log that rolls the wrong way.',
                            'fr' => 'Une paire en croûte de cuir pour la casserole, la grille, et la bûche qui roule du mauvais côté.',
                        ],
                        'price_cents' => 2700,
                        'image' => $this->unsplash('1556911220-bff31c812dba'),
                        'featured' => false,
                    ],
                    [
                        'slug' => 'pot-hanger',
                        'name' => ['en' => 'Adjustable Pot Hanger', 'fr' => 'Crémaillère de camp'],
                        'description' => [
                            'en' => 'A notched iron hanger for the kettle over the fire. Raise it when it boils, leave it when it simmers.',
                            'fr' => 'Une crémaillère en fer pour la bouilloire au-dessus du feu. On la monte quand ça bout, on la laisse quand ça mijote.',
                        ],
                        'price_cents' => 3400,
                        'image' => $this->unsplash('1600891964599-f61ba0e24092'),
                        'featured' => false,
                    ],
                ],
            ],
        ];

        foreach ($catalog as $categoryIndex => $categoryData) {
            $category = Category::query()->updateOrCreate(
                ['slug' => $categoryData['slug']],
                [
                    'name' => $categoryData['name'],
                    'description' => $categoryData['description'],
                    'sort_order' => $categoryIndex + 1,
                ],
            );

            foreach ($categoryData['products'] as $productIndex => $productData) {
                Product::query()->updateOrCreate(
                    ['slug' => $productData['slug']],
                    [
                        'category_id' => $category->id,
                        'name' => $productData['name'],
                        'description' => $productData['description'],
                        'price_cents' => $productData['price_cents'],
                        'quantity' => $productData['quantity'] ?? 20,
                        'image' => $productData['image'],
                        'featured' => $productData['featured'],
                        'sort_order' => $productIndex + 1,
                    ],
                );
            }
        }
    }

    private function unsplash(string $photoId): string
    {
        return 'https://images.unsplash.com/photo-'.$photoId.'?auto=format&fit=crop&w=900&h=1200&q=80';
    }
}
