<?php

namespace App\Services;

/**
 * What became of one line's stock: served from the shelf, or owed to the
 * customer with an idea of how long the supplier takes.
 */
readonly class OrderStockAllocation
{
    public function __construct(
        public bool $backordered,
        public ?int $supplierLeadTimeDays,
    ) {}
}
