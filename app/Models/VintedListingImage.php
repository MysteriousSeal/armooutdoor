<?php

namespace App\Models;

use App\Support\ImageThumbnailer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

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

    /**
     * Le nom sous lequel cette photo se télécharge : la référence du produit,
     * un tiret bas, son rang dans l'annonce. Il vit ici plutôt que dans le
     * contrôleur parce que le lien l'écrit aussi, dans son attribut
     * `download` — deux endroits qui le calculeraient chacun de leur côté
     * finiraient par ne plus dire la même chose.
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
