<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What will be written on Vinted for a product.
 *
 * Nothing is sent anywhere: Vinted opens no API for depositing a listing,
 * and depositing it by hand is in any case the moment one decides it goes
 * up. This holds the wording ready from one posting to the next, so it is
 * neither rewritten each time nor hunted for in a notebook.
 */
#[Fillable([
    'product_id',
    'title',
    'description',
    'price_cents',
])]
class VintedListing extends Model
{
    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(VintedListingImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /** The price as one types it into a field: "12.90", never "12,90". */
    public function priceInput(): string
    {
        return $this->price_cents === null ? '' : number_format($this->price_cents / 100, 2, '.', '');
    }

    /**
     * What gets pasted into Vinted, with a French comma and no symbol:
     * Vinted's price field will not take one.
     */
    public function priceForCopy(): string
    {
        return $this->price_cents === null ? '' : number_format($this->price_cents / 100, 2, ',', '');
    }

    /** An empty listing has nothing to copy: the button says so rather than lying. */
    public function isEmpty(): bool
    {
        return blank($this->title) && blank($this->description) && $this->price_cents === null;
    }

    /**
     * Vinted refuses a listing with a single photo, and a listing with a
     * single photo sells badly anyway: two is the minimum that counts.
     */
    public const MINIMUM_IMAGES = 2;

    /**
     * What is written and what is missing, item by item. The product page
     * uses it to say at a glance whether the listing can go up, without
     * having to open it.
     *
     * @return array<string, bool>
     */
    public function readiness(): array
    {
        return [
            'Title' => filled($this->title),
            'Text' => filled($this->description),
            'Price' => $this->price_cents !== null,
            'Photos' => $this->imageCount() >= self::MINIMUM_IMAGES,
        ];
    }

    public function isReady(): bool
    {
        return ! in_array(false, $this->readiness(), true);
    }

    /** Counted on the already-loaded relation when there is one, so a list
     *  does not run a query per product. */
    public function imageCount(): int
    {
        return $this->relationLoaded('images')
            ? $this->images->count()
            : $this->images()->count();
    }
}
