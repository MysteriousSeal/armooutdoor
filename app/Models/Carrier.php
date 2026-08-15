<?php

namespace App\Models;

use App\Enums\DeliveryMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'slug',
    'name',
    'description',
    'eta',
    'method',
    'price_cents',
    'sort_order',
    'active',
])]
class Carrier extends Model
{
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'eta' => 'array',
            'method' => DeliveryMethod::class,
            'price_cents' => 'integer',
            'sort_order' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true)->orderBy('sort_order');
    }

    public function priceTiers(): HasMany
    {
        return $this->hasMany(CarrierPriceTier::class)->orderBy('min_weight_grams');
    }

    public function localizedName(): string
    {
        return $this->localized('name');
    }

    public function localizedDescription(): string
    {
        return $this->localized('description');
    }

    public function localizedEta(): string
    {
        return $this->localized('eta');
    }

    public function formattedPrice(): string
    {
        return format_euros($this->price_cents);
    }

    /**
     * The price for a cart of the given weight, based on this carrier's
     * price tiers (the highest tier whose min weight the cart meets or
     * exceeds). Falls back to the flat price_cents when no tiers are set.
     */
    public function effectivePriceCentsForWeight(int $weightGrams): int
    {
        $tier = $this->priceTiers()
            ->where('min_weight_grams', '<=', $weightGrams)
            ->reorder('min_weight_grams', 'desc')
            ->first();

        return $tier?->price_cents ?? $this->price_cents;
    }

    public function formattedStartingPrice(): string
    {
        return format_euros($this->effectivePriceCentsForWeight(0));
    }

    public function isRelay(): bool
    {
        return $this->method === DeliveryMethod::Relay;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'eta' => $this->eta,
            'method' => $this->method->value,
            'price_cents' => $this->price_cents,
        ];
    }

    private function localized(string $attribute): string
    {
        $value = $this->{$attribute};

        if (! is_array($value)) {
            return (string) $value;
        }

        return $value['fr'] ?? '';
    }
}
