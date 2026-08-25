<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'naturabuy_id',
    'title',
    'url',
    'category',
    'internalcode',
    'price_cents',
    'oldprice_cents',
    'quantity',
    'physical_quantity',
    'out_of_stock',
    'out_of_stock_available',
    'closed',
    'variants',
    'synced_at',
])]
class NaturabuyListing extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'category' => 'integer',
            'price_cents' => 'integer',
            'oldprice_cents' => 'integer',
            'quantity' => 'integer',
            'physical_quantity' => 'integer',
            'out_of_stock' => 'boolean',
            'out_of_stock_available' => 'boolean',
            'closed' => 'boolean',
            'variants' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    /** L'adresse publique de l'annonce, que l'API ne donne qu'en relatif. */
    public function publicUrl(): ?string
    {
        return $this->url ? 'https://www.naturabuy.fr/'.ltrim($this->url, '/') : null;
    }

    public function isDiscounted(): bool
    {
        return $this->oldprice_cents !== null && $this->oldprice_cents > $this->price_cents;
    }
}
