<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['conversation_id', 'user_id', 'author_type', 'body'])]
class ConversationMessage extends Model
{
    public const AUTHOR_CUSTOMER = 'customer';

    public const AUTHOR_ADMIN = 'admin';

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isFromAdmin(): bool
    {
        return $this->author_type === self::AUTHOR_ADMIN;
    }

    /**
     * Who the message is shown as being from. Admin replies are attributed to
     * the shop, never to the staff member who wrote them — the authoring admin
     * stays on user_id for the audit trail. Keeping this on the model means the
     * admin and customer views cannot drift on attribution.
     */
    public function authorLabel(): string
    {
        return $this->isFromAdmin()
            ? config('app.name')
            : $this->conversation->name;
    }

    /**
     * Initials for the timeline avatar. Admin replies carry the shop's mark,
     * matching authorLabel() — never the staff member's initials.
     */
    public function avatarInitials(): string
    {
        if (! $this->isFromAdmin()) {
            return $this->conversation->initials();
        }

        $words = array_values(array_filter(explode(' ', trim((string) config('app.name')))));

        return strtoupper(implode('', array_map(
            fn (string $word): string => mb_substr($word, 0, 1),
            array_slice($words, 0, 2),
        )));
    }

    /**
     * True when this message continues a run from the same side, so the
     * timeline can group it under the previous one instead of repeating
     * the author and avatar.
     */
    public function continues(?self $previous): bool
    {
        return $previous !== null
            && $previous->author_type === $this->author_type
            && $previous->created_at->isSameDay($this->created_at);
    }
}
