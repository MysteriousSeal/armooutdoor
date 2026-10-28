<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** One active eBay listing, as the last sync read it. */
#[Fillable([
    'item_id',
    'title',
    'url',
    'sku',
    'price_cents',
    'currency',
    'quantity',
    'quantity_sold',
    'variations',
    'synced_at',
])]
class EbayListing extends Model
{
    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'quantity' => 'integer',
            'quantity_sold' => 'integer',
            'variations' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * The SKUs this listing sells: its own, or one per variation when it
     * has them, since a listing with variations carries none of its own.
     *
     * @return list<string>
     */
    public function skus(): array
    {
        $skus = array_column($this->variations ?? [], 'sku');

        if ($this->sku !== null) {
            $skus[] = $this->sku;
        }

        return array_values(array_unique(array_filter($skus, 'filled')));
    }
}
