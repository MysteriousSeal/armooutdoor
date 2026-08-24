<?php

namespace App\Services;

use App\Enums\StockMovementReason;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\StockContext;

/**
 * Takes the stock a line needs, and says what happened.
 *
 * The same twenty lines lived in three places: the two customer checkouts had
 * drifted into byte-identical copies, and the admin one had drifted the other
 * way — it refuses a line it cannot cover where a customer would be put on
 * backorder. Both behaviours are wanted, so the difference is a parameter
 * rather than a third copy.
 *
 * Locking is the caller's business: the customer paths lock line by line as
 * they go, the admin path locks everything up front because it needs the
 * quantities before the order exists. This only decides and decrements.
 */
class OrderStockAllocator
{
    /**
     * Can this line be served at all? Asked before the order exists, where
     * the subtotal has to be computed but nothing may be taken yet. It is the
     * same rule as take(), which is the point: the caller that asks and the
     * caller that takes must not be able to disagree.
     */
    public function canAllocate(Product $product, ?ProductVariant $variant, int $quantity, bool $allowBackorder): bool
    {
        $stockable = $variant ?? $product;

        return $stockable->quantity >= $quantity
            || ($allowBackorder && $stockable->isBackorderable());
    }

    /**
     * The reason and the order come from the caller: only it knows whether
     * this is a customer checking out or an admin typing an order in. The
     * backorder leg names itself, since only this class knows it was taken.
     *
     * @throws \RuntimeException with message "stock" when the line cannot be
     *                           covered and backordering is not allowed
     */
    public function allocate(
        Product $product,
        ?ProductVariant $variant,
        int $quantity,
        bool $allowBackorder,
        StockMovementReason $reason = StockMovementReason::OrderPlaced,
        ?Order $order = null,
    ): OrderStockAllocation {
        if ($variant !== null) {
            $allocation = $this->take($variant, $quantity, $allowBackorder, $reason, $order);

            // A variant's stock is the truth; the product's own figure is a
            // total that has to be recomputed once a variant moves.
            $product->reconcileQuantity();

            return $allocation;
        }

        return $this->take($product, $quantity, $allowBackorder, $reason, $order);
    }

    private function take(
        Product|ProductVariant $stockable,
        int $quantity,
        bool $allowBackorder,
        StockMovementReason $reason,
        ?Order $order,
    ): OrderStockAllocation {
        if ($stockable->quantity >= $quantity) {
            StockContext::during($reason, fn () => $stockable->decrement('quantity', $quantity), subject: $order);

            return new OrderStockAllocation(backordered: false, supplierLeadTimeDays: null);
        }

        if (! $allowBackorder || ! $stockable->isBackorderable()) {
            throw new \RuntimeException('stock');
        }

        // Empty the shelf, then owe the rest. Stock never goes negative here:
        // what is missing is carried by the backorder flag instead. The
        // ledger names this leg apart — it is a different event from a line
        // served in full, and the shelf hitting zero is the interesting part.
        if ($stockable->quantity > 0) {
            StockContext::during(
                StockMovementReason::BackorderPartial,
                fn () => $stockable->decrement('quantity', $stockable->quantity),
                subject: $order,
            );
        }

        return new OrderStockAllocation(
            backordered: true,
            supplierLeadTimeDays: $stockable->supplier?->lead_time_days,
        );
    }
}
