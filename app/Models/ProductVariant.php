<?php

namespace App\Models;

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
        return $this->price_cents ?? $this->product->price_cents;
    }

    public function formattedPrice(): string
    {
        return format_euros($this->effectivePriceCents());
    }

    public function label(): string
    {
        return collect($this->getAttribute('attribute_values') ?? [])->pluck('value')->implode(' / ');
    }

    public function inStock(): bool
    {
        return $this->quantity > 0;
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
}
