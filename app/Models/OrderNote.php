<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A note an admin left on an order, for the other admins. Customers never
 * see it. It is added or deleted, never rewritten, so the log can be read
 * as it was written.
 */
#[Fillable([
    'order_id',
    'user_id',
    'body',
])]
class OrderNote extends Model
{
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Its author, or the owner, who answers for everything on the order. */
    public function canBeDeletedBy(User $user): bool
    {
        return $user->isOwner() || (int) $this->user_id === (int) $user->id;
    }
}
