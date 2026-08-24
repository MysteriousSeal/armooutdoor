<?php

namespace App\Services;

use App\Enums\StockMovementReason;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Support\StockContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Books a delivery against a purchase order.
 *
 * This is the only place a purchase order touches stock, and stock rises
 * here only — as goods physically arrive, possibly across several partial
 * deliveries. Everything runs under row locks: two admins booking the same
 * delivery at once must not both credit the same units.
 */
class PurchaseOrderReceiver
{
    /**
     * @param  array<int|string, mixed>  $lines  item id => quantity received now
     *
     * @throws ValidationException when a line exceeds its outstanding quantity
     */
    public function receive(PurchaseOrder $purchaseOrder, array $lines): void
    {
        DB::transaction(function () use ($purchaseOrder, $lines): void {
            // Re-read under lock; the state may have moved since the form was
            // rendered.
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->id);

            abort_unless($po->canReceive(), 403);

            $received = [];

            foreach ($lines as $lineId => $quantity) {
                $quantity = (int) $quantity;

                if ($quantity <= 0) {
                    continue;
                }

                /** @var PurchaseOrderItem $line */
                $line = $po->items()->lockForUpdate()->findOrFail($lineId);

                if ($quantity > $line->quantityRemaining()) {
                    // Rejected rather than clamped: a v1 constraint, stated
                    // out loud. Suppliers do over-ship in real life.
                    throw ValidationException::withMessages([
                        'lines.'.$lineId => 'More than the outstanding quantity for this line.',
                    ]);
                }

                // Le journal de stock a besoin de la raison, pas d'un chemin
                // à part : la réception elle-même ne change pas d'un iota.
                StockContext::during(
                    StockMovementReason::PurchaseOrderReceived,
                    fn () => $this->addStock($line, $quantity),
                    subject: $po,
                );
                $line->increment('quantity_received', $quantity);
                $received[] = $quantity.' × '.$line->name;
            }

            if ($received === []) {
                throw ValidationException::withMessages([
                    'lines' => 'Nothing to receive — every quantity is zero.',
                ]);
            }

            $po->load('items')->syncReceivedStatus('Received '.implode(', ', $received));
        });
    }

    private function addStock(PurchaseOrderItem $line, int $quantity): void
    {
        if ($line->product_variant_id !== null) {
            $variant = ProductVariant::query()->lockForUpdate()->find($line->product_variant_id);
            $variant?->increment('quantity', $quantity);
            // A product with variants keeps quantity as the mirror of their
            // sum — see Product::reconcileQuantity().
            $variant?->product?->reconcileQuantity();

            return;
        }

        $product = Product::query()->lockForUpdate()->find($line->product_id);

        // Guard, not an assumption: a deleted product (nullOnDelete) books
        // the receipt without moving stock, and a product that has since
        // gained variants no longer owns its own quantity — writing to it
        // would be silently overwritten by the next reconcile.
        if ($product !== null && ! $product->hasVariants()) {
            $product->increment('quantity', $quantity);
        }
    }
}
