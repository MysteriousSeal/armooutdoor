<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An entry written by hand into the journal.
 *
 * It carries the same columns as an order in the month's table, except that
 * it comes from no order at all.
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
    /** The kinds of sale. The list is short on purpose: it gets totalled. */
    public const TYPES = [
        'stock_sale' => 'Stock sale',
        'prestation' => 'Prestation',
        'repair' => 'Repair',
        'other' => 'Other',
    ];

    /** The same kinds in French, since the accounting documents are. */
    public const TYPES_FR = [
        'stock_sale' => 'Vente sur stock',
        'prestation' => 'Prestation',
        'repair' => 'Réparation',
        'other' => 'Autre',
    ];

    /** How the money lands. A bank wire, until proven otherwise. */
    public const PAYMENT_METHODS = [
        'bank_wire' => 'Bank wire',
        'card' => 'Card',
        'cash' => 'Cash',
        'cheque' => 'Cheque',
    ];

    public const PAYMENT_METHODS_FR = [
        'bank_wire' => 'Virement',
        'card' => 'Carte',
        'cash' => 'Espèces',
        'cheque' => 'Chèque',
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

    public function typeLabelFr(): string
    {
        return self::TYPES_FR[$this->type] ?? $this->typeLabel();
    }

    public function paymentLabelFr(): string
    {
        return self::PAYMENT_METHODS_FR[$this->payment_method] ?? $this->paymentLabel();
    }

    /** What is left once the fees are held back. */
    public function perceivedCents(): int
    {
        return $this->total_cents - $this->fees_cents;
    }
}
