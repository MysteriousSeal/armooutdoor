<?php

namespace App\Models;

use App\Support\Cart;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
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
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function effectivePriceCents(): int
    {
        return $this->price_cents ?? $this->product->effectivePriceCents();
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

    public function maxPurchasable(): int
    {
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
        return \App\Support\ImageThumbnailer::urlFor($this->image ?: $this->product->image);
    }
}
