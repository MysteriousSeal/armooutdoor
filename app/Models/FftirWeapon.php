<?php

namespace App\Models;

use App\Enums\Caliber;
use App\Enums\WeaponType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['brand', 'model', 'caliber', 'type', 'price_cents'])]
class FftirWeapon extends Model
{
    protected $table = 'fftir_weapons';

    protected function casts(): array
    {
        return [
            'type' => WeaponType::class,
            'caliber' => Caliber::class,
            'price_cents' => 'integer',
        ];
    }

    public function sessionLines(): HasMany
    {
        return $this->hasMany(FftirSessionLine::class, 'fftir_weapon_id');
    }

    public function roundsFired(): int
    {
        return $this->sessionLines->sum('quantity');
    }

    /**
     * The weapon's own price, amortized over every round ever fired through
     * it, plus the cost of the ammunition those rounds actually used. Null
     * when it has never been fired: there is nothing to divide by yet.
     */
    public function costPerRoundFiredCents(): ?int
    {
        $totalRounds = $this->roundsFired();

        if ($totalRounds === 0) {
            return null;
        }

        $ammunitionCost = $this->sessionLines->sum(function (FftirSessionLine $line) {
            $priceCents = $line->ammunition !== null
                ? $line->ammunition->averageUnitPriceCents()
                : FftirAmmunition::averageUnitPriceCentsForCaliber($line->caliber);

            return $line->quantity * ($priceCents ?? 0);
        });

        return (int) round((($this->price_cents ?? 0) + $ammunitionCost) / $totalRounds);
    }
}
