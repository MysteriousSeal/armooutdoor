<?php

namespace App\Services\Ebay;

use App\Models\EbayListing;

/**
 * Brings the active eBay listings in, and drops the ones that have ended:
 * an ended listing never comes back in the answer, so without pruning it
 * would stay here for good.
 */
class EbaySynchronizer
{
    public function __construct(private readonly EbayClient $client) {}

    /**
     * @return array{fetched: int, created: int, updated: int, deleted: int}
     */
    public function sync(): array
    {
        $listings = $this->client->activeListings();
        $syncedAt = now();
        $seen = [];
        $created = 0;
        $updated = 0;

        foreach ($listings as $attributes) {
            if ($attributes['item_id'] === '') {
                continue;
            }

            $seen[] = $attributes['item_id'];

            $listing = EbayListing::query()->updateOrCreate(
                ['item_id' => $attributes['item_id']],
                [...$attributes, 'synced_at' => $syncedAt],
            );

            $listing->wasRecentlyCreated ? $created++ : $updated++;
        }

        $deleted = EbayListing::query()->whereNotIn('item_id', $seen ?: [''])->delete();

        return [
            'fetched' => count($listings),
            'created' => $created,
            'updated' => $updated,
            'deleted' => $deleted,
        ];
    }
}
