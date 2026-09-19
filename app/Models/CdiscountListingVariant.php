<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one variant answers for itself on Cdiscount.
 *
 * Each variant is an offer of its own there, so the attributes that differ
 * from one to the next — the size, the colour — are answered here rather than
 * once for the product.
 */
#[Fillable(['cdiscount_listing_id', 'product_variant_id', 'values'])]
class CdiscountListingVariant extends Model
{
    protected $attributes = [
        'values' => '[]',
    ];

    protected function casts(): array
    {
        return ['values' => 'array'];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(CdiscountListing::class, 'cdiscount_listing_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** @return array<string, string> */
    public function answers(): array
    {
        return array_map('strval', (array) ($this->values ?? []));
    }
}
