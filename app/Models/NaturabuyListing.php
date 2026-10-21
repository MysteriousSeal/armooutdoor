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
    'photo_count',
    'photos_checked_at',
])]
class NaturabuyListing extends Model
{
    use HasFactory;

    /** NaturaBuy's commission on the sale price, excluding VAT. */
    public const COMMISSION_PERCENT_EXCL_VAT = 12;

    /** The VAT NaturaBuy adds on top of its commission. */
    public const COMMISSION_VAT_PERCENT = 20;

    protected function casts(): array
    {
        return [
            'photo_count' => 'integer',
            'photos_checked_at' => 'datetime',
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

    /**
     * NaturaBuy's commission, VAT included: 12% plus 20% VAT, so 14.4% of the
     * price. Rounded once on the whole, as their statements do: 11,49 € is
     * charged 1,65 €, where rounding the 12% then the VAT would give 1,66 €.
     */
    public function commissionInclVatCents(): int
    {
        return (int) round(
            $this->price_cents * self::COMMISSION_PERCENT_EXCL_VAT * (100 + self::COMMISSION_VAT_PERCENT) / 10000
        );
    }

    /** What the shop receives once NaturaBuy has taken its commission. */
    public function netAfterCommissionCents(): int
    {
        return $this->price_cents - $this->commissionInclVatCents();
    }
}
