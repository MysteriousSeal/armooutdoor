<?php

namespace App\Models;

use App\Support\ImageThumbnailer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An article sold on Vinted only.
 *
 * Not a product: it has no page, no price in the shop, and nothing on the
 * storefront reads this table. It is a line in a list kept by hand, with what
 * the lot cost and how many pieces are left.
 */
#[Fillable([
    'title',
    'image',
    'purchase_total_cents',
    'lot_quantity',
    'quantity',
])]
class VintedItem extends Model
{
    protected function casts(): array
    {
        return [
            'purchase_total_cents' => 'integer',
            'lot_quantity' => 'integer',
            'quantity' => 'integer',
        ];
    }

    /** Every purchase of this item, the oldest first. */
    public function lots(): HasMany
    {
        return $this->hasMany(VintedItemLot::class)->orderBy('id');
    }

    /** Every piece sold, the latest first. */
    public function sales(): HasMany
    {
        return $this->hasMany(VintedItemSale::class)->orderByDesc('id');
    }

    public function scopeAvailable(Builder $query): void
    {
        $query->where('quantity', '>', 0);
    }

    public function scopeSoldOut(Builder $query): void
    {
        $query->where('quantity', 0);
    }

    public function isSoldOut(): bool
    {
        return $this->quantity === 0;
    }

    /**
     * What one piece cost, averaged over every lot: all that was paid over
     * all that was bought, not over what is left.
     */
    public function unitCostCents(): int
    {
        return (int) round($this->purchase_total_cents / max(1, $this->lot_quantity));
    }

    /** How many pieces have gone: what was bought less what is left. */
    public function soldCount(): int
    {
        return max(0, $this->lot_quantity - $this->quantity);
    }

    /**
     * What the sales brought in. A sale with no price on record counts for
     * nothing here, neither as income nor as a loss.
     */
    public function soldTotalCents(): int
    {
        return (int) $this->pricedSales()->sum('price_cents');
    }

    /**
     * What a sale left once the piece is paid for, at the average cost.
     * Null when the price was never recorded.
     */
    public function marginCents(VintedItemSale $sale): ?int
    {
        return $sale->price_cents === null ? null : $sale->price_cents - $this->unitCostCents();
    }

    /**
     * What the priced sales left in all. Worked out from the lot total
     * rather than by adding rounded margins, so the cents do not drift.
     */
    public function profitCents(): int
    {
        $priced = $this->pricedSales();

        return (int) $priced->sum('price_cents')
            - (int) round($this->purchase_total_cents * $priced->count() / max(1, $this->lot_quantity));
    }

    /**
     * The sales whose price is known, off the loaded relation: lists load it
     * once for every row rather than asking per item.
     *
     * @return Collection<int, VintedItemSale>
     */
    private function pricedSales(): Collection
    {
        return $this->sales->filter(fn (VintedItemSale $sale): bool => $sale->price_cents !== null);
    }

    public function thumbnailUrl(): ?string
    {
        return filled($this->image) ? ImageThumbnailer::urlFor($this->image) : null;
    }

    public function imageUrl(): ?string
    {
        return filled($this->image) ? asset('images/'.$this->image) : null;
    }
}
