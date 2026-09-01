<?php

namespace App\Models;

use App\Observers\StockMovementObserver;
use App\Support\Cart;
use App\Support\ImageThumbnailer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(StockMovementObserver::class)]
#[Fillable([
    'product_id',
    'supplier_id',
    'available_at_supplier',
    'supplier_product_url',
    'supplier_reference',
    'attribute_values',
    'sku',
    'gtin',
    'price_cents',
    'quantity',
    'image',
    'is_active',
    'sort_order',
])]
class ProductVariant extends Model
{
    private const SIZE_ORDER = [
        'XXS' => 0,
        '2XS' => 0,
        'XS' => 1,
        'S' => 2,
        'M' => 3,
        'L' => 4,
        'XL' => 5,
        'XXL' => 6,
        '2XL' => 6,
        'XXXL' => 7,
        '3XL' => 7,
        'XXXXL' => 8,
        '4XL' => 8,
    ];

    protected function casts(): array
    {
        return [
            'attribute_values' => 'array',
            'price_cents' => 'integer',
            'quantity' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'available_at_supplier' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function effectivePriceCents(): int
    {
        return $this->price_cents ?? $this->product->effectivePriceCents();
    }

    /**
     * Whether this variant is sold at a reduction.
     *
     * A variant carrying its own price is sold at it, discount or none: the
     * reduction belongs to the product's price, and a variant that overrides
     * that price has stepped outside it.
     */
    public function isDiscounted(): bool
    {
        return $this->price_cents === null && $this->product?->hasDiscount() === true;
    }

    /**
     * What it costs before any reduction: its own price, or the product's.
     */
    public function formattedOriginalPrice(): string
    {
        return format_euros($this->price_cents ?? $this->product?->price_cents ?? 0);
    }

    public function formattedPrice(): string
    {
        return format_euros($this->effectivePriceCents());
    }

    public function label(): string
    {
        return collect($this->getAttribute('attribute_values') ?? [])->pluck('value')->implode(' / ');
    }

    /**
     * Rank for sorting by size (XXS, XS, S, M, L, XL, ...) when this variant
     * has a "Size"/"Taille" attribute. Null if it has no such attribute, so
     * callers can fall back to the admin-defined sort_order.
     */
    public function sizeSortRank(): ?int
    {
        $sizeAttribute = collect($this->getAttribute('attribute_values') ?? [])
            ->first(fn (array $attribute): bool => in_array(
                mb_strtolower(trim($attribute['label'] ?? '')),
                ['size', 'taille'],
                true,
            ));

        if ($sizeAttribute === null) {
            return null;
        }

        $value = mb_strtoupper(trim($sizeAttribute['value'] ?? ''));

        return self::SIZE_ORDER[$value] ?? 999;
    }

    public function inStock(): bool
    {
        return $this->quantity > 0;
    }

    /**
     * Out of stock, but the supplier can still get it — sold as a
     * one-unit backorder, independently from the parent product's
     * own supplier fields.
     */
    /** Toutes les lignes de commande fournisseur, reçues ou non. */
    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * Les lignes de commande fournisseur encore en attente pour cet article.
     *
     * Ligne par ligne, pas commande par commande : une commande partiellement
     * reçue contient des articles arrivés et d'autres non, et seuls les
     * seconds sont encore en approvisionnement.
     */
    public function restockingPurchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)
            ->whereColumn('quantity_received', '<', 'quantity_ordered')
            ->whereHas('purchaseOrder', fn ($query) => $query->open());
    }

    /** Un réassort est en route pour cet article. */
    public function isRestocking(): bool
    {
        // Si la relation est déjà chargée, on ne repart pas en base : les
        // listings affichent des dizaines de produits d'affilée.
        if ($this->relationLoaded('restockingPurchaseItems')) {
            return $this->restockingPurchaseItems->isNotEmpty();
        }

        return $this->restockingPurchaseItems()->exists();
    }

    public function isBackorderable(): bool
    {
        return ! $this->isRestocking()
            && $this->supplier_id !== null && $this->available_at_supplier;
    }

    public function maxPurchasable(): int
    {
        if (! $this->inStock() && $this->isBackorderable()) {
            return 1;
        }

        return max(0, min(Cart::MAX_QUANTITY, $this->quantity));
    }

    public function lowStock(): bool
    {
        return $this->quantity > 0 && $this->quantity <= 2;
    }

    public function imageUrl(): string
    {
        $image = $this->image ?: $this->product->image;

        if (str_starts_with($image, 'https://') || str_starts_with($image, 'http://')) {
            return $image;
        }

        return asset('images/'.$image);
    }

    public function thumbnailUrl(): string
    {
        return ImageThumbnailer::urlFor($this->image ?: $this->product->image);
    }
}
