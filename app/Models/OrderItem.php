<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id',
    'product_id',
    'product_slug',
    'name',
    'image',
    'unit_price_cents',
    'quantity',
    'line_cents',
])]
class OrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'unit_price_cents' => 'integer',
            'quantity' => 'integer',
            'line_cents' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function localizedName(): string
    {
        $name = $this->name;

        if (! is_array($name)) {
            return (string) $name;
        }

        return $name['fr'] ?? '';
    }

    public function formattedLineTotal(): string
    {
        return format_euros($this->line_cents);
    }

    public function imageUrl(): string
    {
        if (str_starts_with($this->image, 'https://') || str_starts_with($this->image, 'http://')) {
            return $this->image;
        }

        return asset('images/'.$this->image);
    }
}
