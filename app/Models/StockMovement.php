<?php

namespace App\Models;

use App\Enums\StockMovementReason;
use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Une ligne du journal de stock : ce qui a bougé, de combien, pourquoi.
 *
 * Rien ne l'écrit à la main. StockMovementObserver la pose dès qu'une
 * quantité change, ce qui la rend impossible à contourner tant que le
 * changement passe par Eloquent.
 */
#[Fillable([
    'product_id',
    'product_variant_id',
    'variant_label',
    'reason',
    'delta',
    'quantity_before',
    'quantity_after',
    'subject_type',
    'subject_id',
    'user_id',
    'note',
    'backfilled',
])]
class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    /** Un mouvement a eu lieu ; il ne se corrige pas. */
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'reason' => StockMovementReason::class,
            'delta' => 'integer',
            'quantity_before' => 'integer',
            'quantity_after' => 'integer',
            'backfilled' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** La déclinaison concernée, ou son nom figé si elle a été supprimée. */
    public function variantLabel(): ?string
    {
        return $this->variant_label ?: null;
    }

    /** Le numéro de la commande ou du bon de commande à l'origine du mouvement. */
    public function subjectLabel(): ?string
    {
        return match ($this->subject_type) {
            Order::class => $this->subject?->number,
            PurchaseOrder::class => $this->subject?->number,
            default => null,
        };
    }

    /** Null quand la source a disparu : la ligne reste lisible sans lien. */
    public function subjectUrl(): ?string
    {
        if ($this->subject === null) {
            return null;
        }

        return match ($this->subject_type) {
            Order::class => route('admin.orders.show', $this->subject),
            PurchaseOrder::class => route('admin.purchase-orders.show', $this->subject),
            default => null,
        };
    }
}
