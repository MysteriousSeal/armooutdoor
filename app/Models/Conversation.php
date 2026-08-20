<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['user_id', 'order_id', 'name', 'email', 'subject'])]
class Conversation extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected function casts(): array
    {
        return [
            'last_customer_message_at' => 'datetime',
            'last_admin_message_at' => 'datetime',
            'admin_last_read_at' => 'datetime',
            'customer_last_read_at' => 'datetime',
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

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class)->oldest();
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(ConversationMessage::class)->latestOfMany();
    }

    /**
     * A guest wrote this: there is no account behind it, so there is nowhere
     * for them to read a reply. Every reply affordance is gated on this.
     */
    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * The single write path for a message. Keeps the denormalised
     * last_*_message_at columns honest and reopens a closed thread when the
     * customer comes back. Never insert a ConversationMessage directly.
     */
    public function postMessage(string $body, string $authorType, ?User $author = null): ConversationMessage
    {
        $message = $this->messages()->create([
            'user_id' => $author?->id,
            'author_type' => $authorType,
            'body' => $body,
        ]);

        if ($authorType === ConversationMessage::AUTHOR_ADMIN) {
            $this->last_admin_message_at = $message->created_at;
        } else {
            $this->last_customer_message_at = $message->created_at;

            if ($this->isClosed()) {
                $this->status = self::STATUS_OPEN;
            }
        }

        $this->save();

        return $message;
    }

    public function hasUnreadForAdmin(): bool
    {
        if ($this->last_customer_message_at === null) {
            return false;
        }

        return $this->admin_last_read_at === null
            || $this->last_customer_message_at->greaterThan($this->admin_last_read_at);
    }

    public function hasUnreadForCustomer(): bool
    {
        if ($this->last_admin_message_at === null) {
            return false;
        }

        return $this->customer_last_read_at === null
            || $this->last_admin_message_at->greaterThan($this->customer_last_read_at);
    }

    public function markReadForAdmin(): void
    {
        $this->admin_last_read_at = now();
        $this->save();
    }

    public function markReadForCustomer(): void
    {
        $this->customer_last_read_at = now();
        $this->save();
    }

    public function scopeOpen(Builder $query): void
    {
        $query->where('status', self::STATUS_OPEN);
    }

    public function scopeClosed(Builder $query): void
    {
        $query->where('status', self::STATUS_CLOSED);
    }

    public function scopeUnreadForAdmin(Builder $query): void
    {
        $query->whereNotNull('last_customer_message_at')
            ->where(fn (Builder $query) => $query
                ->whereNull('admin_last_read_at')
                ->orWhereColumn('last_customer_message_at', '>', 'admin_last_read_at'));
    }

    public function scopeUnreadForCustomer(Builder $query): void
    {
        $query->whereNotNull('last_admin_message_at')
            ->where(fn (Builder $query) => $query
                ->whereNull('customer_last_read_at')
                ->orWhereColumn('last_admin_message_at', '>', 'customer_last_read_at'));
    }

    public function initials(): string
    {
        $words = array_values(array_filter(explode(' ', trim($this->name))));
        $initials = strtoupper(implode('', array_map(fn (string $word): string => mb_substr($word, 0, 1), array_slice($words, 0, 2))));

        return $initials !== '' ? $initials : strtoupper(mb_substr((string) $this->email, 0, 1));
    }

    /**
     * A guest's email may belong to an existing customer account (they just
     * didn't sign in). Never guessed for a thread already linked to a user.
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
