<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What will be written on Cdiscount for a product.
 *
 * Nothing is sent from here: Octopia takes its own Excel template, filled in
 * and uploaded by hand. This holds the category that describes the product
 * and the answers to that category's attributes, kept from one export to the
 * next. It sits beside the product, as the Vinted listing does: what a
 * marketplace asks is not what the catalogue knows.
 */
#[Fillable(['product_id', 'octopia_template_id', 'values', 'per_variant'])]
class CdiscountListing extends Model
{
    protected $attributes = [
        'values' => '[]',
        'per_variant' => '[]',
    ];

    protected function casts(): array
    {
        return [
            'values' => 'array',
            'per_variant' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(OctopiaTemplate::class, 'octopia_template_id');
    }

    /** The answers a variant gives for itself. */
    public function variants(): HasMany
    {
        return $this->hasMany(CdiscountListingVariant::class);
    }

    /**
     * What this product answers, attribute code by attribute code.
     *
     * @return array<string, string>
     */
    public function answers(): array
    {
        return array_map('strval', (array) ($this->values ?? []));
    }

    /**
     * The attributes answered variant by variant: the colour of a product
     * sold in three of them is three answers, its material one.
     *
     * @return list<string>
     */
    public function perVariantCodes(): array
    {
        return array_values(array_map('strval', (array) ($this->per_variant ?? [])));
    }

    /**
     * One variant's own answers, the product's standing where it says
     * nothing.
     *
     * @return array<string, string>
     */
    public function answersFor(?ProductVariant $variant): array
    {
        $answers = $this->answers();

        if ($variant === null) {
            return $answers;
        }

        $own = $this->variants->firstWhere('product_variant_id', $variant->id);

        foreach ($own?->answers() ?? [] as $code => $value) {
            if (filled($value)) {
                $answers[$code] = $value;
            }
        }

        return $answers;
    }
}
