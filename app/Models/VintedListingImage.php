<?php

namespace App\Models;

use App\Support\ImageThumbnailer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A listing photo. The listing's own, not the catalogue's: on Vinted an
 * article is shown on a table or worn, which the product page does not show
 * and has no business showing.
 */
#[Fillable([
    'vinted_listing_id',
    'image',
    'sort_order',
])]
class VintedListingImage extends Model
{
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(VintedListing::class, 'vinted_listing_id');
    }

    /**
     * The name this photo downloads under: the product's reference, an
     * underscore, its rank in the listing. It lives here rather than in the
     * controller because the link writes it too, in its `download`
     * attribute — two places computing it apart would end up disagreeing.
     */
    public function downloadName(): string
    {
        $listing = $this->listing;
        $product = $listing?->product;

        $rank = $listing?->images->search(fn (self $image): bool => $image->id === $this->id);
        $name = filled($product?->sku) ? $product->sku : ($product?->slug ?? 'photo');

        return Str::slug($name).'_'.(is_int($rank) ? $rank + 1 : 1).'.jpg';
    }

    public function imageUrl(): string
    {
        return asset('images/'.$this->image);
    }

    public function thumbnailUrl(): string
    {
        return ImageThumbnailer::urlFor($this->image);
    }
}
