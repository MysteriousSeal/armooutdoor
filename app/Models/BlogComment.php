<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'blog_post_id',
    'user_id',
    'parent_id',
    'author_name',
    'is_admin',
    'body',
])]
class BlogComment extends Model
{
    /**
     * Ten uppercase hex characters an admin reads off the page and pastes
     * into the back-office search. Stamped after creation - the id it
     * hashes does not exist any earlier - and salted upward until unique:
     * a collision at 16^10 is theory, but the loop makes it impossible.
     */
    protected static function booted(): void
    {
        static::created(function (self $comment): void {
            $comment->forceFill([
                'reference' => self::uniqueReference($comment->id.'|'.$comment->created_at),
            ])->saveQuietly();
        });
    }

    public static function uniqueReference(string $seed): string
    {
        $salt = 0;

        do {
            $reference = strtoupper(substr(sha1($seed.($salt > 0 ? '|'.$salt : '')), 0, 10));
            $salt++;
        } while (static::query()->where('reference', $reference)->exists());

        return $reference;
    }

    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('created_at');
    }

    /**
     * The name over the comment: the shop's own for an admin reply, first
     * name and last initial for an account - comments are public, full
     * names are not, same rule as the reviews - and the chosen pseudonym
     * for a guest.
     */
    public function authorLabel(): string
    {
        if ($this->is_admin) {
            return config('app.name');
        }

        if ($this->user !== null) {
            $lastInitial = mb_strtoupper(mb_substr(trim($this->user->last_name ?? ''), 0, 1));

            return trim($this->user->first_name.($lastInitial !== '' ? ' '.$lastInitial.'.' : ''));
        }

        return (string) $this->author_name;
    }
}
