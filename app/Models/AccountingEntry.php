<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

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
    'invoice_path',
    'client',
    'channel',
    'type',
    'total_cents',
    'vat_rate_basis_points',
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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'entered_on' => 'date',
            'total_cents' => 'integer',
            'vat_rate_basis_points' => 'integer',
            'fees_cents' => 'integer',
        ];
    }

    /** The admin who first wrote this entry. Kept through later corrections. */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** Narrows to one side of the accounts, sales or purchases. */
    public function scopeSection(Builder $query, string $section): void
    {
        $query->where('section', $section);
    }

    /** The kind of sale, for the admin screen. */
    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** How the money landed, for the admin screen. */
    public function paymentLabel(): string
    {
        return self::PAYMENT_METHODS[$this->payment_method] ?? $this->payment_method;
    }

    /** The kind of sale, for the printed journal. */
    public function typeLabelFr(): string
    {
        return self::TYPES_FR[$this->type] ?? $this->typeLabel();
    }

    /** How the money landed, for the printed journal. */
    public function paymentLabelFr(): string
    {
        return self::PAYMENT_METHODS_FR[$this->payment_method] ?? $this->paymentLabel();
    }

    /**
     * The rate as a percentage, for display: 2000 basis points is 20.
     *
     * Null on a sale, where VAT is settled elsewhere.
     */
    public function vatRatePercent(): ?float
    {
        return $this->vat_rate_basis_points === null
            ? null
            : $this->vat_rate_basis_points / 100;
    }

    /**
     * The amount before tax.
     *
     * `total_cents` is what was actually paid, tax included, because that is
     * the figure printed on the invoice and the one that leaves the bank. The
     * two others are worked back from it.
     */
    public function exVatCents(): int
    {
        if (! $this->vat_rate_basis_points) {
            return $this->total_cents;
        }

        return (int) round($this->total_cents / (1 + $this->vat_rate_basis_points / 10000));
    }

    /** The tax itself, which is what gets reclaimed. */
    public function vatCents(): int
    {
        return $this->total_cents - $this->exVatCents();
    }

    /** Whether the supplier's invoice has been attached to this line. */
    public function hasInvoiceFile(): bool
    {
        return filled($this->invoice_path);
    }

    /**
     * Whether a file can be attached at all.
     *
     * A line with no invoice number has no paper behind it to attach — a
     * shop receipt, a marketplace charge — so nothing is offered for it.
     */
    public function acceptsInvoiceFile(): bool
    {
        return $this->section === 'purchases' && filled($this->invoice_number);
    }

    /** A line whose invoice exists on paper but has not been attached yet. */
    public function isMissingInvoiceFile(): bool
    {
        return $this->acceptsInvoiceFile() && ! $this->hasInvoiceFile();
    }

    /**
     * What the attached file is called when it is opened.
     *
     * Supplier and invoice number, so a file saved out of the browser still
     * says which purchase it belongs to. A line with no number falls back to
     * its date, which identifies it just as well.
     */
    public function invoiceFileName(): string
    {
        $supplier = Str::slug((string) $this->client) ?: 'fournisseur';
        $reference = Str::slug((string) $this->invoice_number) ?: $this->entered_on->format('Y-m-d');

        return $supplier.'_'.$reference.'.pdf';
    }

    /** What is left once the fees are held back. */
    public function perceivedCents(): int
    {
        return $this->total_cents - $this->fees_cents;
    }
}
