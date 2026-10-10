<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One piece of a Vinted-only item sold, and what it went for.
 */
#[Fillable([
    'vinted_item_id',
    'price_cents',
])]
class VintedItemSale extends Model
{
    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(VintedItem::class, 'vinted_item_id');
    }
}
