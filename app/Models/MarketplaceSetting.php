<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * What the shop says about the marketplaces it also sells on.
 *
 * The rating and the review count are typed in the back office rather than
 * fetched: NaturaBuy publishes no feed for them, and a figure nobody can
 * refresh is better shown as a figure somebody owns.
 *
 * The rating is stored in tenths, so 4.9 is 49 and no float rounds itself
 * into 4.8999 on the way to the page.
 */
#[Fillable(['naturabuy_url', 'naturabuy_rating_tenths', 'naturabuy_reviews', 'naturabuy_sales', 'naturabuy_on_home'])]
class MarketplaceSetting extends Model
{
    protected $attributes = [
        'naturabuy_on_home' => false,
    ];

    protected function casts(): array
    {
        return [
            'naturabuy_rating_tenths' => 'integer',
            'naturabuy_reviews' => 'integer',
            'naturabuy_sales' => 'integer',
            'naturabuy_on_home' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }

    /** The rating as a number, or null while nobody has entered one. */
    public function naturabuyRating(): ?float
    {
        return $this->naturabuy_rating_tenths === null
            ? null
            : $this->naturabuy_rating_tenths / 10;
    }

    /**
     * Whether the home page has something worth showing: the block claims a
     * standing, so it needs the standing, the count it rests on, and the
     * page a visitor can check it against.
     */
    public function showsNaturabuyOnHome(): bool
    {
        return $this->naturabuy_on_home
            && filled($this->naturabuy_url)
            && $this->naturabuy_rating_tenths !== null
            && $this->naturabuy_reviews !== null;
    }
}
