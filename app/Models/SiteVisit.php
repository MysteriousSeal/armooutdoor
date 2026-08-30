<?php

namespace App\Models;

use App\Support\UserAgentParser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['path', 'session_id', 'product_id', 'category_id', 'user_id', 'ip_address', 'user_agent', 'referrer', 'country', 'city'])]
class SiteVisit extends Model
{
    const UPDATED_AT = null;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Browser family or bot product name, derived from user_agent. */
    protected function browser(): Attribute
    {
        return Attribute::get(fn (): string => UserAgentParser::browser($this->user_agent));
    }

    protected function device(): Attribute
    {
        return Attribute::get(fn (): string => UserAgentParser::device($this->user_agent));
    }

    protected function os(): Attribute
    {
        return Attribute::get(fn (): string => UserAgentParser::os($this->user_agent));
    }

    protected function isBot(): Attribute
    {
        return Attribute::get(fn (): bool => UserAgentParser::isBot($this->user_agent));
    }

    /** "City, Country" or whichever part is known. */
    protected function locationLabel(): Attribute
    {
        return Attribute::get(function (): string {
            $parts = array_values(array_filter([
                $this->city,
                $this->country,
            ], fn ($value) => filled($value)));

            return $parts === [] ? '—' : implode(', ', $parts);
        });
    }
}
