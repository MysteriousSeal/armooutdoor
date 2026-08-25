<?php

namespace App\Services\Naturabuy;

use App\Models\NaturabuyListing;
use Illuminate\Support\Carbon;

/**
 * Le rapatriement des annonces, en un seul endroit.
 *
 * La commande et le bouton d'administration font le même travail ; le laisser
 * dans la commande et l'appeler par `Artisan::call()` depuis un contrôleur
 * rendrait le résultat difficile à lire et à tester. Le service renvoie un
 * décompte, chacun le présente à sa façon.
 */
class NaturabuySynchronizer
{
    public function __construct(private readonly NaturabuyClient $client) {}

    /**
     * @return array{fetched: int, created: int, updated: int, deleted: int}
     */
    public function sync(bool $prune = false): array
    {
        $items = $this->client->items();
        $syncedAt = now();
        $seen = [];
        $created = 0;
        $updated = 0;

        foreach ($items as $item) {
            if (! isset($item['id'])) {
                continue;
            }

            $seen[] = (int) $item['id'];

            $listing = NaturabuyListing::query()->updateOrCreate(
                ['naturabuy_id' => (int) $item['id']],
                $this->attributes($item, $syncedAt),
            );

            $listing->wasRecentlyCreated ? $created++ : $updated++;
        }

        $deleted = 0;

        if ($prune) {
            // Une annonce supprimée chez eux ne reviendra jamais dans la
            // réponse : sans élagage, elle resterait ici indéfiniment.
            $deleted = NaturabuyListing::query()->whereNotIn('naturabuy_id', $seen ?: [0])->delete();
        }

        return [
            'fetched' => count($items),
            'created' => $created,
            'updated' => $updated,
            'deleted' => $deleted,
        ];
    }

    /** @return array<string, mixed> */
    private function attributes(array $item, Carbon $syncedAt): array
    {
        return [
            'title' => (string) ($item['title'] ?? ''),
            'url' => $item['url'] ?? null,
            'category' => isset($item['category']) ? (int) $item['category'] : null,
            'internalcode' => ($item['internalcode'] ?? '') !== '' ? (string) $item['internalcode'] : null,
            // Leurs prix sont des décimaux ; ici tout est en centimes entiers.
            'price_cents' => $this->cents($item['price'] ?? 0),
            'oldprice_cents' => isset($item['oldprice']) ? $this->cents($item['oldprice']) : null,
            'quantity' => (int) ($item['quantity'] ?? 0),
            'physical_quantity' => (int) ($item['physical_quantity'] ?? 0),
            'out_of_stock' => (bool) ($item['out_of_stock'] ?? false),
            'out_of_stock_available' => (bool) ($item['out_of_stock_available'] ?? false),
            'closed' => (bool) ($item['closed'] ?? false),
            'variants' => $item['variants'] ?? [],
            'synced_at' => $syncedAt,
        ];
    }

    private function cents(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
