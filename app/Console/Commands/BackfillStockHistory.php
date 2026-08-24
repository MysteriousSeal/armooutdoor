<?php

namespace App\Console\Commands;

use App\Enums\StockMovementReason;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Remonte le journal de stock jusqu'à ce qui s'est passé avant lui.
 *
 * Le journal démarre vide et n'enregistre que ce qui se produit après sa
 * mise en service. Cette commande écrit après coup les deux mouvements dont
 * la boutique garde une trace exploitable — les ventes des commandes et les
 * réceptions des bons de commande — sans toucher à une seule quantité.
 *
 * Les deux sources sont reconstruites ensemble, jamais l'une après l'autre :
 * un produit vendu puis réapprovisionné a une seule chronologie, et deux
 * passages séparés laisseraient les soldes de la première à côté.
 *
 * Ces soldes sont reconstruits à rebours, en partant du stock connu et en
 * remontant mouvement par mouvement. Le résultat est cohérent et retombe
 * exactement sur le stock d'aujourd'hui, mais il n'est pas véridique : tout
 * ce qui a bougé le stock sans laisser de trace — un inventaire corrigé à la
 * main, un import — reste invisible et se reporte sur les soldes plus
 * anciens. Chaque ligne rattrapée le dit à l'écran.
 */
#[Signature('stock:backfill-history {--dry-run : Show what would be written without writing it}')]
#[Description('Write stock movements for past orders and purchase-order receipts, without touching any quantity')]
class BackfillStockHistory extends Command
{
    private const NOTE = 'Backfilled from history — balance reconstructed';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $events = $this->sales()->concat($this->receipts());

        if ($events->isEmpty()) {
            $this->info('Nothing to backfill.');

            return self::SUCCESS;
        }

        $written = $this->reconstruct($events, $dryRun);

        $this->line(sprintf(
            '%d movements over %d stockables — %d sales, %d receipts.',
            $written,
            $events->groupBy('key')->count(),
            $events->where('delta', '<', 0)->count(),
            $events->where('delta', '>', 0)->count(),
        ));

        if ($dryRun) {
            $this->comment('Dry run — nothing was written.');
        }

        return self::SUCCESS;
    }

    /**
     * Ce que les commandes ont retiré.
     *
     * Un brouillon n'a jamais pris de stock, et une commande de test ne doit
     * peser sur aucun chiffre de la boutique.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function sales(): Collection
    {
        $orders = Order::query()
            ->excludingTest()
            ->where('status', '!=', 'draft')
            ->with(['items', 'statusHistories'])
            ->get();

        return $orders->flatMap(function (Order $order): Collection {
            $at = $order->statusHistories->firstWhere('status', 'placed')?->created_at
                ?? $order->created_at;

            return $order->items
                ->filter(fn (OrderItem $item): bool => $item->product_id !== null)
                ->map(fn (OrderItem $item): array => $this->event(
                    productId: $item->product_id,
                    variantId: $item->product_variant_id,
                    variantLabel: $item->variant_label ?: null,
                    delta: -(int) $item->quantity,
                    reason: StockMovementReason::OrderPlaced,
                    subject: $order,
                    at: $at,
                ));
        })->values();
    }

    /**
     * Ce que les réceptions ont ajouté.
     *
     * Une ligne ne garde que son total reçu, pas le détail des livraisons :
     * un bon reçu en plusieurs fois compte donc pour un seul mouvement, à la
     * date de sa première réception. Un bon annulé garde les siennes — ce qui
     * est arrivé est arrivé.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function receipts(): Collection
    {
        $purchaseOrders = PurchaseOrder::query()
            ->whereHas('items', fn ($query) => $query->where('quantity_received', '>', 0))
            ->with(['items', 'statusHistories'])
            ->get();

        return $purchaseOrders->flatMap(function (PurchaseOrder $po): Collection {
            $at = $po->statusHistories
                ->whereIn('status', ['partially_received', 'received'])
                ->sortBy('created_at')
                ->first()?->created_at
                ?? $po->received_at
                ?? $po->created_at;

            return $po->items
                ->filter(fn (PurchaseOrderItem $item): bool => $item->quantity_received > 0 && $item->product_id !== null)
                ->map(fn (PurchaseOrderItem $item): array => $this->event(
                    productId: $item->product_id,
                    variantId: $item->product_variant_id,
                    variantLabel: $item->variant?->label() ?: null,
                    delta: (int) $item->quantity_received,
                    reason: StockMovementReason::PurchaseOrderReceived,
                    subject: $po,
                    at: $at,
                ));
        })->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function event(
        int $productId,
        ?int $variantId,
        ?string $variantLabel,
        int $delta,
        StockMovementReason $reason,
        Model $subject,
        Carbon $at,
    ): array {
        return [
            // Le stock vit sur la déclinaison quand il y en a une : c'est
            // elle, pas le produit, dont le solde doit s'enchaîner.
            'key' => $variantId !== null ? 'v'.$variantId : 'p'.$productId,
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'variant_label' => $variantLabel,
            'delta' => $delta,
            'reason' => $reason,
            'subject' => $subject,
            'at' => $at,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $events
     */
    private function reconstruct(Collection $events, bool $dryRun): int
    {
        $written = 0;
        $byStockable = $events->groupBy('key');
        $starts = $this->startingBalances($byStockable);

        $write = function () use ($byStockable, $starts, $dryRun, &$written): void {
            // Le rattrapage ne réécrit que ses propres lignes : un mouvement
            // réel, lui, a été observé et ne se recalcule pas.
            if (! $dryRun) {
                StockMovement::query()->where('backfilled', true)->delete();
            }

            foreach ($byStockable as $key => $stockableEvents) {
                $running = $starts[$key];

                $ordered = $stockableEvents
                    ->sortByDesc(fn (array $event) => [$event['at']->getTimestamp(), $event['subject']->id])
                    ->values();

                foreach ($ordered as $event) {
                    $after = $running;
                    $before = $running - $event['delta'];
                    $running = $before;

                    if ($this->output->isVerbose()) {
                        $this->line(sprintf(
                            '  %s  %-22s %+d  %d → %d',
                            $event['at']->format('Y-m-d'),
                            $event['subject']->number,
                            $event['delta'],
                            $before,
                            $after,
                        ));
                    }

                    $written++;

                    if ($dryRun) {
                        continue;
                    }

                    $movement = new StockMovement([
                        'product_id' => $event['product_id'],
                        'product_variant_id' => $event['product_variant_id'],
                        'variant_label' => $event['variant_label'],
                        'reason' => $event['reason'],
                        'delta' => $event['delta'],
                        'quantity_before' => $before,
                        'quantity_after' => $after,
                        'subject_type' => $event['subject']->getMorphClass(),
                        'subject_id' => $event['subject']->getKey(),
                        'user_id' => null,
                        'note' => self::NOTE,
                        'backfilled' => true,
                    ]);

                    // La date de l'événement, pas celle du rattrapage. Elle
                    // ne passe pas par l'assignation de masse : un journal ne
                    // doit pas se laisser antidater par n'importe quel appel.
                    $movement->created_at = $event['at'];
                    $movement->save();
                }
            }
        };

        // Une transaction seulement quand on écrit : un essai à blanc n'a
        // rien à annuler.
        $dryRun ? $write() : DB::transaction($write);

        return $written;
    }

    /**
     * D'où repart la remontée, pour chaque chose qui porte du stock.
     *
     * Le stock d'aujourd'hui, sauf si un mouvement réel a déjà été observé :
     * dans ce cas le passé s'arrête là où ce mouvement a commencé, et c'est
     * son solde d'avant qui sert de point de départ.
     *
     * @param  Collection<string, Collection<int, array<string, mixed>>>  $byStockable
     * @return array<string, int>
     */
    private function startingBalances(Collection $byStockable): array
    {
        $productQuantities = Product::query()->pluck('quantity', 'id');
        $variantQuantities = ProductVariant::query()->pluck('quantity', 'id');

        $oldestObserved = StockMovement::query()
            ->where('backfilled', false)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['product_id', 'product_variant_id', 'quantity_before'])
            ->groupBy(fn (StockMovement $movement): string => $movement->product_variant_id !== null
                ? 'v'.$movement->product_variant_id
                : 'p'.$movement->product_id)
            ->map(fn (Collection $group): int => (int) $group->first()->quantity_before);

        $starts = [];

        foreach ($byStockable as $key => $events) {
            $id = (int) substr((string) $key, 1);

            $starts[$key] = $oldestObserved[$key] ?? (int) (str_starts_with((string) $key, 'v')
                ? ($variantQuantities[$id] ?? 0)
                : ($productQuantities[$id] ?? 0));
        }

        return $starts;
    }
}
