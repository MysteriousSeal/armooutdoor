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

    /** The profit a suggested price aims for, as a share of the product cost. */
    public const TARGET_PROFIT_PERCENT = 200;

    /** What packing one sale costs, paid out of the price like the product. */
    public const PACKAGING_COST_CENTS = 30;

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

    /**
     * The lowest price ending in ,90 that leaves at least the target profit
     * once NaturaBuy has taken its commission and the packaging is paid:
     * with 200%, what the shop receives covers the packaging and three
     * times what the product cost.
     */
    public static function suggestedPriceCents(int $costCents): int
    {
        $neededCents = $costCents * (100 + self::TARGET_PROFIT_PERCENT) / 100 + self::PACKAGING_COST_CENTS;

        // Start just under the price that would leave exactly enough once
        // the commission is taken, then step up a euro at a time: the
        // rounding of the commission may cost a step.
        $keptShare = 1 - self::COMMISSION_PERCENT_EXCL_VAT * (100 + self::COMMISSION_VAT_PERCENT) / 10000;
        $euros = max(0, intdiv((int) floor($neededCents / $keptShare), 100) - 1);

        while (true) {
            $listing = new self(['price_cents' => $euros * 100 + 90]);

            if ($listing->netAfterCommissionCents() >= $neededCents) {
                return $listing->price_cents;
            }

            $euros++;
        }
    }
}
