<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['date', 'note'])]
class FftirSession extends Model
{
    protected $table = 'fftir_sessions';

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FftirSessionLine::class);
    }
}
