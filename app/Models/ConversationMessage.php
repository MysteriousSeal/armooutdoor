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
}
