<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'order_id', 'name', 'email', 'subject', 'message'])]
class ContactMessage extends Model
{
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function initials(): string
    {
        $words = array_values(array_filter(explode(' ', trim($this->name))));
        $initials = strtoupper(implode('', array_map(fn (string $word): string => mb_substr($word, 0, 1), array_slice($words, 0, 2))));

        return $initials !== '' ? $initials : strtoupper(mb_substr((string) $this->email, 0, 1));
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->read_at = now();
            $this->save();
        }
    }

    /**
     * A guest message's email may belong to an existing customer account
     * (they just didn't sign in when they wrote it). Never guessed for a
     * message that's already linked to a user.
     */
    public function possibleCustomer(): ?User
    {
        if ($this->user_id !== null) {
            return null;
        }

        return User::query()
            ->where('is_admin', false)
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($this->email)])
            ->first();
    }
}
