<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Once;

#[Fillable(['low_stock_threshold'])]
class ProductSetting extends Model
{
    /**
     * Mirrors the column default: a row freshly made by firstOrCreate()
     * would otherwise hold null in memory until re-read from the base.
     */
    protected $attributes = [
        'low_stock_threshold' => 2,
    ];

    protected function casts(): array
    {
        return [
            'low_stock_threshold' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // once() memoizes the threshold for the request; a save must not
        // leave the old value answering until the next one.
        static::saved(fn () => Once::flush());
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }

    /**
     * At or below this quantity, a product shows « Derniers stocks
     * disponibles ». Memoized: the threshold is read once per product on
     * a listing, and twenty cards must not mean twenty queries.
     *
     * Read without creating: firstOrCreate() would fire saved(), whose
     * Once::flush() evicts this very memo as it forms. The row only comes
     * to exist when the settings page saves it.
     */
    public static function lowStockThreshold(): int
    {
        return once(fn (): int => static::query()->value('low_stock_threshold') ?? 2);
    }
}
