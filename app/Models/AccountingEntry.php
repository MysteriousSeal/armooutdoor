<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une écriture saisie à la main dans le journal.
 *
 * Elle porte les mêmes colonnes qu'une commande dans le tableau du mois, à
 * ceci près qu'elle ne vient d'aucune commande.
 */
#[Fillable([
    'section',
    'entered_on',
    'invoice_number',
    'client',
    'channel',
    'type',
    'total_cents',
    'fees_cents',
    'payment_method',
    'remark',
    'created_by_user_id',
])]
class AccountingEntry extends Model
{
    /** Les natures de vente. La liste est courte exprès : elle se totalise. */
    public const TYPES = [
        'stock_sale' => 'Stock sale',
        'prestation' => 'Prestation',
        'repair' => 'Repair',
        'other' => 'Other',
    ];

    /** Ce qui arrive sur le compte. Le virement, jusqu'à preuve du contraire. */
    public const PAYMENT_METHODS = [
        'bank_wire' => 'Bank wire',
        'card' => 'Card',
        'cash' => 'Cash',
        'cheque' => 'Cheque',
    ];

    protected function casts(): array
    {
        return [
            'entered_on' => 'date',
            'total_cents' => 'integer',
            'fees_cents' => 'integer',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeSection(Builder $query, string $section): void
    {
        $query->where('section', $section);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function paymentLabel(): string
    {
        return self::PAYMENT_METHODS[$this->payment_method] ?? $this->payment_method;
    }

    /** Ce qui reste une fois les frais retenus. */
    public function perceivedCents(): int
    {
        return $this->total_cents - $this->fees_cents;
    }
}
