<?php

namespace App\Models;

use App\Enums\Caliber;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['brand', 'caliber', 'denomination', 'quantity'])]
class FftirAmmunition extends Model
{
    protected $table = 'fftir_ammunitions';

    protected function casts(): array
    {
        return [
            'caliber' => Caliber::class,
            'quantity' => 'integer',
        ];
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(FftirAmmunitionStockMovement::class, 'fftir_ammunition_id');
    }

    /**
     * The weighted average of what was actually paid for the stock bought
     * in, across every priced stock-in movement. Unpriced additions (a gift,
     * an unrecorded old batch) don't count either way. Null when nothing
     * bought in has ever had a price attached.
     */
    public function averageUnitPriceCents(): ?int
    {
        $priced = $this->stockMovements
            ->filter(fn (FftirAmmunitionStockMovement $movement) => $movement->delta > 0 && $movement->total_price_cents !== null);

        $totalRounds = $priced->sum('delta');

        if ($totalRounds === 0) {
            return null;
        }

        return (int) round($priced->sum('total_price_cents') / $totalRounds);
    }

    /**
     * The same weighted average as {@see averageUnitPriceCents()}, pooled
     * across every ammunition of a given caliber rather than a single one.
     * Used when a session line only names a caliber, not a specific box.
     */
    public static function averageUnitPriceCentsForCaliber(Caliber $caliber): ?int
    {
        $priced = static::query()
            ->where('caliber', $caliber)
            ->with('stockMovements')
            ->get()
            ->flatMap(fn (self $ammunition) => $ammunition->stockMovements)
            ->filter(fn (FftirAmmunitionStockMovement $movement) => $movement->delta > 0 && $movement->total_price_cents !== null);

        $totalRounds = $priced->sum('delta');

        if ($totalRounds === 0) {
            return null;
        }

        return (int) round($priced->sum('total_price_cents') / $totalRounds);
    }
}
