<?php

namespace App\Models;

use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * An order placed with a supplier to restock the shop.
 *
 * It moves no stock by itself: stock rises only as goods are received, line
 * by line, possibly across several deliveries. Cancelling never reverses
 * stock already received — those goods physically arrived; cancelling only
 * closes out what was still expected.
 */
#[Fillable([
    'number',
    'supplier_id',
    'supplier_name',
    'status',
    'reference',
    'expected_at',
    'notes',
    'shipping_cents',
    'created_by_user_id',
    'sent_at',
    'received_at',
    'cancelled_at',
])]
class PurchaseOrder extends Model
{
    /** @use HasFactory<PurchaseOrderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'expected_at' => 'date',
            'shipping_cents' => 'integer',
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(PurchaseOrderStatusHistory::class)->latest()->latest('id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Unlike Order::generateNumber(), the uniqueness is settled by the
     * database rather than by a look-before-insert: two admins creating at
     * the same instant collide on the unique index and one of them simply
     * draws a new number.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function createWithNumber(array $attributes): self
    {
        for ($attempt = 0; ; $attempt++) {
            try {
                return static::query()->create([
                    ...$attributes,
                    'number' => 'BC-'.now()->format('Ymd').'-'.strtoupper(Str::random(4)),
                ]);
            } catch (QueryException $exception) {
                if ($attempt >= 5 || ! str_contains($exception->getMessage(), 'UNIQUE')) {
                    throw $exception;
                }
            }
        }
    }

    public function markStatus(string $status, ?string $note = null): void
    {
        $changed = $this->status !== $status;

        if ($changed) {
            $this->update(['status' => $status]);
        }

        // A second partial receipt changes no status but is still an event:
        // the note is what makes the timeline an audit trail.
        if ($changed || $note !== null) {
            $this->statusHistories()->create([
                'status' => $status,
                'note' => $note,
                'user_id' => Auth::id(),
            ]);
        }
    }

    public function subtotalCents(): int
    {
        return (int) $this->items->sum(fn (PurchaseOrderItem $item): int => $item->lineTotalCents());
    }

    public function totalCents(): int
    {
        return $this->subtotalCents() + $this->shipping_cents;
    }

    public function receivedValueCents(): int
    {
        return (int) $this->items->sum(
            fn (PurchaseOrderItem $item): int => $item->quantity_received * $item->unit_cost_cents,
        );
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isEditable(): bool
    {
        return $this->isDraft();
    }

    public function canReceive(): bool
    {
        return in_array($this->status, ['sent', 'partially_received'], true);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['sent', 'partially_received'], true);
    }

    /**
     * A sent order is a record of something that happened: it is cancelled,
     * never deleted. Only a draft can go without trace.
     */
    public function canBeDeleted(): bool
    {
        return $this->isDraft();
    }

    /**
     * Derived from the line quantities, never set by hand.
     */
    public function syncReceivedStatus(?string $note = null): void
    {
        $allReceived = $this->items->every(
            fn (PurchaseOrderItem $item): bool => $item->quantity_received >= $item->quantity_ordered,
        );
        $anyReceived = $this->items->contains(
            fn (PurchaseOrderItem $item): bool => $item->quantity_received > 0,
        );

        $status = match (true) {
            $this->items->isNotEmpty() && $allReceived => 'received',
            $anyReceived => 'partially_received',
            default => $this->status,
        };

        if ($status === 'received' && $this->received_at === null) {
            $this->update(['received_at' => now()]);
        }

        $this->markStatus($status, $note);
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['sent', 'partially_received']);
    }

    public function scopeAwaitingReceipt($query)
    {
        return $query->whereIn('status', ['sent', 'partially_received']);
    }
}
