<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['free_shipping_threshold_cents', 'free_shipping_carrier_ids'])]
class ShippingSetting extends Model
{
    protected function casts(): array
    {
        return [
            'free_shipping_threshold_cents' => 'integer',
            'free_shipping_carrier_ids' => 'array',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }

    public function isFreeFor(Carrier $carrier, int $subtotalCents): bool
    {
        if ($this->free_shipping_threshold_cents === null) {
            return false;
        }

        if ($subtotalCents < $this->free_shipping_threshold_cents) {
            return false;
        }

        return in_array($carrier->id, $this->free_shipping_carrier_ids ?? [], true);
    }

    public function effectivePriceCents(Carrier $carrier, int $subtotalCents, int $weightGrams = 0): int
    {
        return $this->isFreeFor($carrier, $subtotalCents) ? 0 : $carrier->effectivePriceCentsForWeight($weightGrams);
    }

    public function isUnlockedBy(int $subtotalCents): bool
    {
        if ($this->free_shipping_threshold_cents === null || ($this->free_shipping_carrier_ids ?? []) === []) {
            return false;
        }

        return $subtotalCents >= $this->free_shipping_threshold_cents;
    }
}
