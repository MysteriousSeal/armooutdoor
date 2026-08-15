<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'carrier_id',
    'min_weight_grams',
    'price_cents',
])]
class CarrierPriceTier extends Model
{
    protected function casts(): array
    {
        return [
            'min_weight_grams' => 'integer',
            'price_cents' => 'integer',
        ];
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }
}
