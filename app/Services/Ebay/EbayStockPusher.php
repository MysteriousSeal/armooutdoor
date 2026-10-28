<?php

namespace App\Services\Ebay;

use App\Models\EbayListing;
use Throwable;

/**
 * Sends the shop's stock to eBay, for every listing whose SKU is in the
 * catalogue and whose quantity differs.
 *
 * The shop's number is sent as it is: a sale made on eBay and not yet entered
 * as an order here is put back on sale until it is. The listings should be
 * synced just before, so the comparison is against what eBay has now.
 */
class EbayStockPusher
{
    /** ReviseInventoryStatus takes at most four changes per call. */
    private const PER_CALL = 4;

    public function __construct(
        private readonly EbayClient $client,
        private readonly EbayCatalogue $catalogue,
    ) {}

    /**
     * @return array{changed: int, skipped_zero: int, failed: int, errors: list<string>}
     */
    public function push(): array
    {
        $listings = EbayListing::query()->get();
        $stock = $this->catalogue->forSkus($listings->flatMap->skus()->unique()->values()->all());
        $changes = [];

        foreach ($listings as $listing) {
            if ($listing->variations) {
                foreach ($listing->variations as $variation) {
                    $ours = $stock[$variation['sku'] ?? '']['quantity'] ?? null;

                    if ($ours !== null && $ours !== $variation['quantity']) {
                        $changes[] = ['listing' => $listing, 'item_id' => $listing->item_id, 'sku' => $variation['sku'], 'quantity' => $ours];
                    }
                }
            } elseif ($listing->sku !== null && isset($stock[$listing->sku]) && $stock[$listing->sku]['quantity'] !== $listing->quantity) {
                $changes[] = ['listing' => $listing, 'item_id' => $listing->item_id, 'sku' => null, 'quantity' => $stock[$listing->sku]['quantity']];
            }
        }

        $skippedZero = 0;

        // Asked only when a zero is about to go out, since it costs a call.
        if (collect($changes)->contains('quantity', 0) && ! $this->client->outOfStockControl()) {
            $skippedZero = collect($changes)->where('quantity', 0)->count();
            $changes = array_values(array_filter($changes, fn (array $change): bool => $change['quantity'] > 0));
        }

        $changed = 0;
        $failed = 0;
        $errors = [];

        foreach (array_chunk($changes, self::PER_CALL) as $batch) {
            try {
                $this->client->reviseQuantities(array_map(
                    fn (array $change): array => array_diff_key($change, ['listing' => true]),
                    $batch,
                ));
            } catch (Throwable $e) {
                // One refused batch does not stop the others.
                $failed += count($batch);
                $errors[] = $e->getMessage();

                continue;
            }

            foreach ($batch as $change) {
                $this->remember($change['listing'], $change['sku'], $change['quantity']);
            }

            $changed += count($batch);
        }

        return ['changed' => $changed, 'skipped_zero' => $skippedZero, 'failed' => $failed, 'errors' => array_values(array_unique($errors))];
    }

    /** One sentence on a push, for the admin flash and the command alike. */
    public static function summary(array $result): string
    {
        $message = $result['changed'].' eBay '.($result['changed'] === 1 ? 'quantity' : 'quantities').' updated.';

        if ($result['skipped_zero'] > 0) {
            $message .= ' '.$result['skipped_zero'].' left as they are rather than set to 0, which would end the listing:'
                .' turn on the out-of-stock option in eBay\'s selling preferences.';
        }

        if ($result['failed'] > 0) {
            $message .= ' '.$result['failed'].' refused by eBay: '.implode(' ', $result['errors']);
        }

        return $message;
    }

    /** Keeps the local row in step, so the page shows what eBay now has. */
    private function remember(EbayListing $listing, ?string $sku, int $quantity): void
    {
        if ($sku === null) {
            $listing->update(['quantity' => $quantity]);

            return;
        }

        $variations = array_map(
            fn (array $variation): array => $variation['sku'] === $sku ? [...$variation, 'quantity' => $quantity] : $variation,
            $listing->variations,
        );

        $listing->update([
            'variations' => $variations,
            'quantity' => array_sum(array_column($variations, 'quantity')),
        ]);
    }
}
