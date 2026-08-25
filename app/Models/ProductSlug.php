<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une adresse qu'un produit a portée.
 *
 * La ligne active est celle d'aujourd'hui ; les autres sont d'anciennes
 * adresses, gardées pour rediriger vers elle.
 */
#[Fillable(['product_id', 'slug', 'is_active'])]
class ProductSlug extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeRetired(Builder $query): void
    {
        $query->where('is_active', false);
    }
}
