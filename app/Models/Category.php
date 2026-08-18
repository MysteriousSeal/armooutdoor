<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['parent_id', 'slug', 'name', 'description', 'sort_order'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    private const ROOT_ICON_NAMES = [
        'targets' => 'bullseye',
        'range' => 'toolbox',
        'apparel' => 'shirt',
        'field-gear' => 'campground',
        'everyday' => 'screwdriver-wrench',
        'munitions' => 'box-open',
        'repliques-airsoft' => 'gun',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class)->orderBy('sort_order');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    public function listingProducts()
    {
        if ($this->relationLoaded('children') && $this->children->isNotEmpty()) {
            return $this->children
                ->flatMap(fn (Category $child) => $child->products)
                ->sortBy([
                    ['sort_order', 'asc'],
                    ['id', 'asc'],
                ])
                ->values();
        }

        return $this->products;
    }

    public function listingCount(): int
    {
        if ($this->relationLoaded('children') && $this->children->isNotEmpty()) {
            return $this->children->sum(fn (Category $child): int => $child->products->count());
        }

        return $this->products->count();
    }

    public function root(): self
    {
        return $this->parent ?? $this;
    }

    public function localizedName(): string
    {
        return $this->localized('name');
    }

    public function localizedDescription(): string
    {
        return $this->localized('description');
    }

    /**
     * Font Awesome icon name for this category's root, used wherever a
     * category needs a visual icon (nav, homepage, category pages).
     */
    public function iconName(): string
    {
        return self::ROOT_ICON_NAMES[$this->root()->slug] ?? 'default';
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
