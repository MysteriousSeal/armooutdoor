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

    /**
     * The free shipping threshold as a shopper reads it, or null when the
     * shop is not promising one.
     *
     * A threshold with no carrier behind it is not a promise: checkout would
     * never apply it. The rule lived in the home controller, and the footer
     * now makes the same claim on every page, so it lives here instead where
     * the two cannot drift apart.
     */
    public function freeShippingLabel(): ?string
    {
        if (($this->free_shipping_carrier_ids ?? []) === []) {
            return null;
        }

        $cents = $this->free_shipping_threshold_cents;

        if ($cents === null || $cents <= 0) {
            return null;
        }

        $euros = $cents / 100;

        // A round figure loses its ",00": the point is to be read, not to be
        // exact to the cent.
        return fmod($euros, 1.0) === 0.0
            ? number_format($euros, 0, ',', ' ').'€'
            : format_euros($cents);
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
