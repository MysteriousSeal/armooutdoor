<?php

namespace App\Models;

use App\Support\HtmlSanitizer;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'category_id',
    'slug',
    'is_active',
    'sku',
    'gtin',
    'name',
    'description',
    'characteristics',
    'price_cents',
    'quantity',
    'image',
    'featured',
    'sort_order',
])]
class Product extends Model
{
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
            'price_cents' => 'integer',
            'quantity' => 'integer',
            'is_active' => 'boolean',
            'featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
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

    public function wishlistItems(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
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

    public function formattedPrice(): string
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
            return $this->variants->contains(fn (ProductVariant $variant): bool => $variant->is_active && $variant->inStock());
        }

        return $this->inStock();
    }

    public function lowStock(): bool
    {
        return $this->quantity > 0 && $this->quantity <= 2;
    }

    public function maxPurchasable(): int
    {
        return max(0, min(10, $this->quantity));
    }

    public function imageUrl(): string
    {
        if (str_starts_with($this->image, 'https://') || str_starts_with($this->image, 'http://')) {
            return $this->image;
        }

        return asset('images/'.$this->image);
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
