<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One purchase of a Vinted-only item: how many pieces, and what the whole
 * lot cost. The item's own totals are the sum of these.
 */
#[Fillable([
    'vinted_item_id',
    'quantity',
    'purchase_total_cents',
])]
class VintedItemLot extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'purchase_total_cents' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(VintedItem::class, 'vinted_item_id');
    }

    /** What one piece of this lot cost. */
    public function unitCostCents(): int
    {
        return (int) round($this->purchase_total_cents / max(1, $this->quantity));
    }
}
