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
        return $this->variant?->effectivePriceCents() ?? $this->product->price_cents;
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

    public function variantLabel(): ?string
    {
        if ($this->variant === null || $this->variant->label() === '') {
            return null;
        }

        return $this->variant->label();
    }
}
