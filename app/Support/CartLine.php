<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;

class CartLine
{
    public function __construct(
        public Product $product,
        public int $quantity,
        public ?ProductVariant $variant = null,
    ) {}

    public function unitPriceCents(): int
    {
        return $this->variant?->effectivePriceCents() ?? $this->product->effectivePriceCents();
    }

    public function lineCents(): int
    {
        return $this->unitPriceCents() * $this->quantity;
    }

    public function formattedUnitPrice(): string
    {
        return format_euros($this->unitPriceCents());
    }

    public function formattedLineTotal(): string
    {
        return format_euros($this->lineCents());
    }

    /**
     * A variant's own price (when set) overrides the product price
     * entirely, so a product-level discount only actually applies when
     * there's no variant, or the variant just inherits the product price.
     */
    public function hasDiscount(): bool
    {
        return $this->variant?->price_cents === null && $this->product->hasDiscount();
    }

    public function formattedOriginalUnitPrice(): string
    {
        return format_euros($this->product->price_cents);
    }

    public function variantLabel(): ?string
    {
        if ($this->variant === null || $this->variant->label() === '') {
            return null;
        }

        return $this->variant->label();
    }
}
