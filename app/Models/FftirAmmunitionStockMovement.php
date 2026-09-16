<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A stock movement took place; it doesn't get corrected afterwards. */
#[Fillable(['fftir_ammunition_id', 'delta', 'quantity_before', 'quantity_after', 'total_price_cents', 'note', 'user_id'])]
class FftirAmmunitionStockMovement extends Model
{
    protected $table = 'fftir_ammunition_stock_movements';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'delta' => 'integer',
            'quantity_before' => 'integer',
            'quantity_after' => 'integer',
            'total_price_cents' => 'integer',
        ];
    }

    public function ammunition(): BelongsTo
    {
        return $this->belongsTo(FftirAmmunition::class, 'fftir_ammunition_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
