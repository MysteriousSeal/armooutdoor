<?php

namespace App\Models;

use App\Enums\DeliveryMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'slug',
    'name',
    'description',
    'eta',
    'method',
    'price_cents',
    'sort_order',
    'active',
])]
class Carrier extends Model
{
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'eta' => 'array',
            'method' => DeliveryMethod::class,
            'price_cents' => 'integer',
            'sort_order' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true)->orderBy('sort_order');
    }

    public function localizedName(): string
    {
        return $this->localized('name');
    }

    public function localizedDescription(): string
    {
        return $this->localized('description');
    }

    public function localizedEta(): string
    {
        return $this->localized('eta');
    }

    public function formattedPrice(): string
    {
        return format_euros($this->price_cents);
    }

    public function isRelay(): bool
    {
        return $this->method === DeliveryMethod::Relay;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'eta' => $this->eta,
            'method' => $this->method->value,
            'price_cents' => $this->price_cents,
        ];
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
