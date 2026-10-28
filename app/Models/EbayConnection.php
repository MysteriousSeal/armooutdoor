<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The shop's sign-in to its eBay seller account.
 *
 * The refresh token is good for some eighteen months and buys two-hour
 * access tokens; both are encrypted at rest, since either one acts as the
 * seller. There is only ever one row.
 */
#[Fillable(['refresh_token', 'refresh_token_expires_at', 'access_token', 'access_token_expires_at'])]
class EbayConnection extends Model
{
    protected $hidden = ['refresh_token', 'access_token'];

    protected function casts(): array
    {
        return [
            'refresh_token' => 'encrypted',
            'access_token' => 'encrypted',
            'refresh_token_expires_at' => 'datetime',
            'access_token_expires_at' => 'datetime',
        ];
    }

    public static function current(): ?self
    {
        return static::query()->latest('id')->first();
    }

    /** Whether eBay must be signed in to again: the refresh token has lapsed. */
    public function hasExpired(): bool
    {
        return $this->refresh_token_expires_at !== null && $this->refresh_token_expires_at->isPast();
    }
}
