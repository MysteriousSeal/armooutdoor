<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * The shop's own buying guides, written once.
 *
 * The list lived in two places — the guides index and the home page's
 * reading strip — which meant a new guide had to be added twice and the
 * home page could only ever offer the two that were typed into it. Named
 * here, both read the same shelf and the home page can rotate through it.
 */
class Guides
{
    /**
     * Ordered as the index page presents them: the law first, then the two
     * rayons it applies to.
     *
     * @return list<array{topic: string, title: string, url: string, route: string, categories: list<string>, published: string, updated: string, image?: string, teaser: string, summary: string}>
     */
    public static function all(): array
    {
        return [
            [
                'topic' => 'Réglementation',
                'title' => 'Classer son arme',
                'url' => route('guides.classification'),
                'route' => 'guides.classification',
                'categories' => [],
                'published' => '2026-09-06',
                'updated' => '2026-09-06',
                'teaser' => 'Sous 2 joules, de 2 à 20, dès 20 : ce que la loi range en D, C, B et A.',
                'summary' => 'Sous 2 joules, de 2 à 20, dès 20, puis l\'autorisation : ce que la loi range en D, C, B et A, et le piège du chargeur.',
            ],
            [
                'topic' => 'Lieux',
                'title' => 'Où tirer légalement',
                'url' => route('guides.ou-tirer'),
                'route' => 'guides.ou-tirer',
                'categories' => [],
                'published' => '2026-09-07',
                'updated' => '2026-09-07',
                'teaser' => 'Chez soi, sur un terrain, en stand : ce qui décide vraiment, et les textes cités de travers.',
                'summary' => 'Chez soi, sur un terrain d\'airsoft ou en stand homologué : la direction plutôt que la distance, le bruit, l\'arrêté du maire, et les deux textes que le web recopie de travers.',
            ],
            [
                'topic' => 'Vocabulaire',
                'title' => 'Le glossaire',
                'url' => route('guides.glossaire'),
                'route' => 'guides.glossaire',
                'categories' => [],
                'published' => '2026-09-05',
                'updated' => '2026-09-06',
                'teaser' => 'AEG, hop-up, diabolo, MED : les mots du rayon, et où chacun se rencontre.',
                'summary' => 'AEG, hop-up, joule, MED, diabolo, grille graduée, témoin de chambre vide : les mots que portent les fiches et les filtres, définis un par un, chacun menant au rayon ou au guide où on le rencontre.',
            ],
            [
                'topic' => 'Énergie',
                'title' => 'Joules et FPS',
                'url' => route('guides.joules'),
                'route' => 'guides.joules',
                'categories' => ['repliques-airsoft', 'repliques-de-poing', 'repliques-longues', 'repliques-sniper'],
                'published' => '2026-09-07',
                'updated' => '2026-09-07',
                'teaser' => 'La conversion, la calculette, et pourquoi la même réplique ne chrone pas deux fois pareil.',
                'summary' => 'Le magasin annonce des FPS, la loi compte en joules et le terrain aussi : la formule, une calculette, le tableau bille par bille, et pourquoi la même réplique ne chrone pas deux fois pareil.',
            ],
            [
                'topic' => 'Cibles',
                'title' => 'Bien choisir sa cible',
                'url' => route('guides.cibles'),
                'route' => 'guides.cibles',
                'categories' => ['cibles', 'cibles-rondes', 'cibles-carrees', 'planches-cibles', 'cibles-carton-metal'],
                'published' => '2026-09-04',
                'updated' => '2026-09-06',
                'teaser' => 'Réactives, planches, carton ou métal : quel format pour quelle distance, et ce qu\'on lit après le tir.',
                'summary' => 'Réactives autocollantes, planches, carton ou métal basculant : quel format pour quelle distance, ce qu\'on lit après le tir, et combien de feuilles prévoir.',
            ],
            [
                'topic' => 'Camouflage',
                'title' => 'Choisir son camouflage',
                'url' => route('guides.camouflage'),
                'route' => 'guides.camouflage',
                'categories' => ['vetements', 'cagoules', 'casquettes', 'gants', 'ruban-camo'],
                'published' => '2026-09-09',
                'updated' => '2026-09-09',
                'image' => 'images/guides/camouflage/ce.webp',
                'teaser' => 'Le motif est la dernière des cinq choses qui vous trahissent. Les quatre autres, puis les familles.',
                'summary' => 'Le mouvement, la brillance, la silhouette, l\'ombre, la peau : le motif ne vient qu\'après. Les sept familles de motifs, ce que chacune vaut sur un terrain français, et un sélecteur par terrain et par saison.',
            ],
            [
                'topic' => 'Entretien',
                'title' => 'Entretenir son arme',
                'url' => route('guides.entretien'),
                'route' => 'guides.entretien',
                'categories' => ['entretien-arme'],
                'published' => '2026-09-04',
                'updated' => '2026-09-06',
                'teaser' => 'Corde ou kit à tiges, calibre par calibre, dans quel sens nettoyer et à quelle fréquence.',
                'summary' => 'Corde de nettoyage ou kit à tiges : quel matériel pour quel calibre, du 4,5 mm au calibre 12, dans quel sens nettoyer et à quelle fréquence.',
            ],
        ];
    }

    /**
     * One guide, by the route it answers to.
     *
     * A guide page needs the dates and the picture the shelf already holds,
     * and the sitemap needs the same dates: written in the page they would be
     * written twice, and the two copies would drift the first time a guide
     * was revised.
     *
     * @return array{topic: string, title: string, url: string, route: string, categories: list<string>, published: string, updated: string, image?: string, teaser: string, summary: string}
     */
    public static function byRoute(string $route): array
    {
        foreach (self::all() as $guide) {
            if ($guide['route'] === $route) {
                return $guide;
            }
        }

        throw new \InvalidArgumentException('No guide answers to the route '.$route.'.');
    }

    /**
     * The guide that serves a rayon, if one does.
     *
     * A shopper on the targets page is nearer to needing the targets guide
     * than anyone arriving cold from a search, and the shelf was reachable
     * from a category page only through a link to the shelf itself. Named
     * here rather than on the category row: which guide answers for a rayon
     * is an editorial fact about the guide, and it changes when a guide is
     * written, not when a rayon is renamed.
     *
     * @return array{topic: string, title: string, url: string, route: string, categories: list<string>, published: string, updated: string, image?: string, teaser: string, summary: string}|null
     */
    public static function forCategory(string $slug): ?array
    {
        foreach (self::all() as $guide) {
            if (in_array($slug, $guide['categories'], true)) {
                return $guide;
            }
        }

        return null;
    }

    /**
     * The guides the home page offers today.
     *
     * The strip has room for two and the shelf holds more, so the two that
     * showed were simply the two that had been typed in: the rest were
     * advertised nowhere but the footer. The window slides by one guide a
     * day instead of drawing at random, which is the only way a shelf this
     * small changes every morning rather than merely changing on average.
     * It is read off the date, so every visitor of a given day sees the
     * same pair, and it turns over at French midnight rather than UTC's.
     *
     * @return Collection<int, array{topic: string, title: string, url: string, route: string, categories: list<string>, published: string, updated: string, image?: string, teaser: string, summary: string}>
     */
    public static function ofTheDay(int $count = 2): Collection
    {
        $guides = self::all();
        $total = count($guides);

        if ($total === 0 || $count >= $total) {
            return collect($guides);
        }

        $day = intdiv(now('Europe/Paris')->startOfDay()->getTimestamp(), 86400);

        return collect(range(0, $count - 1))
            ->map(fn (int $offset): array => $guides[($day + $offset) % $total])
            ->values();
    }
}
