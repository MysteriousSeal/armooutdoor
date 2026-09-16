<?php

namespace App\Models;

use App\Enums\Caliber;
use App\Enums\ShootingDistance;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['fftir_session_id', 'fftir_weapon_id', 'fftir_ammunition_id', 'caliber', 'distance', 'quantity'])]
class FftirSessionLine extends Model
{
    protected $table = 'fftir_session_lines';

    protected function casts(): array
    {
        return [
            'caliber' => Caliber::class,
            'distance' => ShootingDistance::class,
            'quantity' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(FftirSession::class, 'fftir_session_id');
    }

    public function weapon(): BelongsTo
    {
        return $this->belongsTo(FftirWeapon::class, 'fftir_weapon_id');
    }

    public function ammunition(): BelongsTo
    {
        return $this->belongsTo(FftirAmmunition::class, 'fftir_ammunition_id');
    }
}
