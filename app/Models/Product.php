<?php

namespace App\Models;

use App\Support\HtmlSanitizer;
use App\Support\ImageThumbnailer;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'category_id',
    'supplier_id',
    'available_at_supplier',
    'supplier_product_url',
    'supplier_reference',
    'supplier_price_cents',
    'markup_basis_points',
    'slug',
    'is_active',
    'sku',
    'gtin',
    'weight_grams',
    'carrier_ids',
    'age_restricted',
    'image_may_vary',
    'name',
    'description',
    'characteristics',
    'filter_attributes',
    'price_cents',
    'quantity',
    'image',
    'featured',
    'sort_order',
])]
class Product extends Model
{
    /** TVA française applicable à l'achat fournisseur, en points de base. */
    public const VAT_RATE_BASIS_POINTS = 2000;

    /**
     * Plafond du champ Prix du formulaire. Une recommandation au-dessus
     * remplirait le champ d'une valeur hors bornes : le navigateur refuse
     * alors l'envoi sans rien afficher, et le bouton Enregistrer paraît mort.
     */
    public const MAX_PRICE_CENTS = 9999999;

    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updated(function (Product $product): void {
            if ($product->wasChanged('is_active') && ! $product->is_active) {
                CartItem::query()->where('product_id', $product->id)->delete();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'characteristics' => 'array',
            'filter_attributes' => 'array',
            'price_cents' => 'integer',
            'supplier_price_cents' => 'integer',
            'markup_basis_points' => 'integer',
            'quantity' => 'integer',
            'weight_grams' => 'integer',
            'carrier_ids' => 'array',
            'age_restricted' => 'boolean',
            'image_may_vary' => 'boolean',
            'is_active' => 'boolean',
            'available_at_supplier' => 'boolean',
            'featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    public function hasVariants(): bool
    {
        return $this->relationLoaded('variants')
            ? $this->variants->isNotEmpty()
            : $this->variants()->exists();
    }

    /**
     * Keeps quantity mirroring the sum of variant stock. Call this after
     * anything changes a variant's quantity (a sale, a restock, an admin
     * edit) — quantity itself must never be treated as authoritative once
     * a product has variants.
     */
    public function reconcileQuantity(): void
    {
        if ($this->hasVariants()) {
            $this->update(['quantity' => $this->variants()->sum('quantity')]);
        }
    }

    public function wishlistItems(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class)->latest();
    }

    public function reviewsCount(): int
    {
        return $this->relationLoaded('reviews') ? $this->reviews->count() : $this->reviews()->count();
    }

    public function averageRating(): ?float
    {
        $average = $this->relationLoaded('reviews')
            ? $this->reviews->avg('rating')
            : $this->reviews()->avg('rating');

        return $average !== null ? round((float) $average, 1) : null;
    }

    public function hasBeenReviewedBy(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $this->reviews()->where('user_id', $user->id)->exists();
    }

    /**
     * The oldest shipped order belonging to the user that contains this
     * product and doesn't already have a review — i.e. the order a new
     * review would be attached to. A customer can review each qualifying
     * order once, so this keeps returning orders as long as any are left
     * unreviewed. Null once every eligible order has been reviewed.
     */
    public function eligibleOrderFor(?User $user): ?Order
    {
        if ($user === null) {
            return null;
        }

        return Order::query()
            ->where('user_id', $user->id)
            ->where('status', 'shipped')
            ->whereHas('items', fn (Builder $query) => $query->where('product_id', $this->id))
            ->whereDoesntHave('reviews', fn (Builder $query) => $query->where('product_id', $this->id))
            ->oldest()
            ->first();
    }

    public function canBeReviewedBy(?User $user): bool
    {
        return $this->eligibleOrderFor($user) !== null;
    }

    public function localizedName(): string
    {
        return $this->localized('name');
    }

    public function localizedDescription(): string
    {
        return HtmlSanitizer::forDisplay($this->localized('description'));
    }

    public function localizedDescriptionText(): string
    {
        return HtmlSanitizer::toPlainText($this->localized('description'));
    }

    public function discount(): HasOne
    {
        return $this->hasOne(Discount::class);
    }

    public function hasDiscount(): bool
    {
        $discount = $this->relationLoaded('discount') ? $this->discount : $this->discount()->first();

        return $discount !== null && $discount->isActive();
    }

    public function effectivePriceCents(): int
    {
        return $this->hasDiscount() ? $this->discount->apply($this->price_cents) : $this->price_cents;
    }

    public function formattedPrice(): string
    {
        return format_euros($this->effectivePriceCents());
    }

    public function formattedOriginalPrice(): string
    {
        return format_euros($this->price_cents);
    }

    public function inStock(): bool
    {
        return $this->quantity > 0;
    }

    public function isPurchasable(): bool
    {
        if ($this->hasVariants()) {
            return $this->variants->contains(fn (ProductVariant $variant): bool => $variant->is_active && ($variant->inStock() || $variant->isBackorderable()));
        }

        return $this->inStock() || $this->isBackorderable();
    }

    /**
     * Out of stock, but the supplier can still get it — sold as a
     * one-unit backorder. Only applies to products without variants.
     */
    public function isBackorderable(): bool
    {
        return ! $this->hasVariants() && $this->supplier_id !== null && $this->available_at_supplier;
    }

    public function lowStock(): bool
    {
        return $this->quantity > 0 && $this->quantity <= 2;
    }

    /**
     * A null carrier_ids means unrestricted — every carrier can ship this
     * product. Once set, only the listed carriers are allowed.
     */
    public function isCarrierAllowed(Carrier $carrier): bool
    {
        return $this->carrier_ids === null || in_array($carrier->id, $this->carrier_ids, true);
    }

    public function maxPurchasable(): int
    {
        if (! $this->inStock() && $this->isBackorderable()) {
            return 1;
        }

        return max(0, min(10, $this->quantity));
    }

    /**
     * Le prix de vente conseillé : prix d'achat HT, plus 20 % de TVA, plus la
     * marge voulue, puis arrondi au palier psychologique supérieur.
     *
     * Null sans prix d'achat : sans lui il n'y a rien à recommander. Une marge
     * absente vaut 0 %, ce qui donne au moins le prix de revient TTC.
     */
    public function recommendedPriceCents(): ?int
    {
        // Zéro se traite comme une absence : sans coût, il n'y a pas de marge
        // à calculer, et 0,49 € recommandé sur un article gratuit ressemble à
        // un bug plutôt qu'à un conseil.
        if ($this->supplier_price_cents === null || $this->supplier_price_cents === 0) {
            return null;
        }

        $withVat = $this->supplier_price_cents * (1 + self::VAT_RATE_BASIS_POINTS / 10000);
        $withMarkup = $withVat * (1 + ($this->markup_basis_points ?? 0) / 10000);

        return min(
            self::roundUpToPsychologicalPrice((int) ceil(round($withMarkup, 4))),
            self::MAX_PRICE_CENTS,
        );
    }

    /**
     * Remonte au prochain montant finissant par ,49 ou ,99 — jamais en
     * dessous, pour ne pas rogner la marge demandée. 12,39 donne 12,49 ;
     * 12,51 donne 12,99 ; un montant déjà sur un palier n'est pas touché.
     */
    public static function roundUpToPsychologicalPrice(int $cents): int
    {
        $euros = intdiv($cents, 100);
        $remainder = $cents % 100;

        return $remainder <= 49
            ? $euros * 100 + 49
            : $euros * 100 + 99;
    }

    public function imageUrl(): string
    {
        if (str_starts_with($this->image, 'https://') || str_starts_with($this->image, 'http://')) {
            return $this->image;
        }

        return asset('images/'.$this->image);
    }

    public function thumbnailUrl(): string
    {
        return ImageThumbnailer::urlFor($this->image);
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
