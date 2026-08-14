<?php

namespace App\Support;

use App\Models\Product;

class CartLine
{
    public function __construct(
        public Product $product,
        public int $quantity,
    ) {}

    public function lineCents(): int
    {
        return $this->product->price_cents * $this->quantity;
    }

    public function formattedLineTotal(): string
    {
        return format_euros($this->lineCents());
    }
}
