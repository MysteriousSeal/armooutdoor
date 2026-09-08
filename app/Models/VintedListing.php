<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ce qu'on écrira sur Vinted pour un produit.
 *
 * Rien n'est envoyé nulle part : Vinted n'ouvre pas d'API pour déposer une
 * annonce, et la déposer à la main est de toute façon le moment où l'on
 * décide qu'elle part. Cette page garde le texte prêt entre deux fois, pour
 * qu'il ne soit pas réécrit à chaque dépôt ni cherché dans un carnet.
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

    /** Le prix tel qu'on le tape dans un champ : « 12.90 », jamais « 12,90 ». */
    public function priceInput(): string
    {
        return $this->price_cents === null ? '' : number_format($this->price_cents / 100, 2, '.', '');
    }

    /**
     * Ce qu'on colle dans Vinted, à la virgule française et sans symbole :
     * le champ prix de Vinted n'en veut pas.
     */
    public function priceForCopy(): string
    {
        return $this->price_cents === null ? '' : number_format($this->price_cents / 100, 2, ',', '');
    }

    /** Une annonce vide n'a rien à copier : le bouton le dit plutôt que de mentir. */
    public function isEmpty(): bool
    {
        return blank($this->title) && blank($this->description) && $this->price_cents === null;
    }

    /**
     * Vinted refuse une annonce à une seule photo, et une annonce à une seule
     * photo se vend mal de toute façon : deux est le minimum qui compte.
     */
    public const MINIMUM_IMAGES = 2;

    /**
     * Ce qui est écrit et ce qui manque, poste par poste. La fiche produit
     * s'en sert pour dire d'un coup d'œil si l'annonce peut partir, sans
     * avoir à l'ouvrir.
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

    /** Compté sur la relation déjà chargée quand elle l'est, pour ne pas
     *  refaire une requête par produit dans une liste. */
    public function imageCount(): int
    {
        return $this->relationLoaded('images')
            ? $this->images->count()
            : $this->images()->count();
    }
}
