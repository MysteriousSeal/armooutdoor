<?php

namespace App\Models;

use App\Support\ImageThumbnailer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une photo d'annonce. Les siennes, pas celles du catalogue : sur Vinted on
 * montre l'article posé sur une table ou porté, ce que la fiche produit ne
 * montre pas et n'a pas à montrer.
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

    public function imageUrl(): string
    {
        return asset('images/'.$this->image);
    }

    public function thumbnailUrl(): string
    {
        return ImageThumbnailer::urlFor($this->image);
    }
}
