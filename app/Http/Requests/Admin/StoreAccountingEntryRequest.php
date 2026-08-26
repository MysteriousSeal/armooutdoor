<?php

namespace App\Http\Requests\Admin;

use App\Models\AccountingEntry;
use App\Support\AccountingPeriods;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * An entry written by hand into the accounting journal.
 *
 * Serves both adding and correcting, since the two take exactly the same
 * fields. Money arrives as decimal euros and leaves as integer cents.
 */
class StoreAccountingEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()?->isOwner() === true;
    }

    /**
     * Only the date, the kind, the amount and the payment are required: an
     * entry can be written before its invoice number is known.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'entered_on' => ['required', 'date'],
            'invoice_number' => ['nullable', 'string', 'max:80'],
            'client' => ['nullable', 'string', 'max:120'],
            'channel' => ['nullable', 'string', 'max:80'],
            'type' => ['required', Rule::in(array_keys(AccountingEntry::TYPES))],
            // A negative total is allowed: a credit note is a line of the
            // journal like any other.
            'total' => ['required', 'numeric', 'min:-99999.99', 'max:99999.99'],
            // Fees are typed as a positive figure and held back: the sign is
            // read in the column, not entered in the field.
            'fees' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'payment_method' => ['required', Rule::in(array_keys(AccountingEntry::PAYMENT_METHODS))],
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The date has to fall inside the month being read.
     *
     * Otherwise the entry would be saved and then vanish from the page that
     * had just accepted it.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $period = AccountingPeriods::parse((string) $this->route('month'));
            $date = $this->date('entered_on');

            if ($period === null || $date === null) {
                return;
            }

            if ($date->lessThan($period) || $date->greaterThan($period->endOfMonth())) {
                $validator->errors()->add(
                    'entered_on',
                    'The date must fall inside '.AccountingPeriods::label($period).'.',
                );
            }
        });
    }

    /**
     * Turns the validated fields into columns.
     *
     * The month is not taken here: `withValidator()` has already refused a date
     * outside it, so `entered_on` can be trusted as it stands.
     *
     * @param  bool  $keepAuthor  True when correcting: the entry keeps whoever
     *                            wrote it rather than taking the current admin.
     * @return array<string, mixed>
     */
    public function entryPayload(string $section, bool $keepAuthor = false): array
    {
        $validated = $this->validated();

        $payload = [
            'section' => $section,
            'entered_on' => $validated['entered_on'],
            'invoice_number' => $validated['invoice_number'] ?? null,
            'client' => $validated['client'] ?? null,
            'channel' => $validated['channel'] ?? null,
            'type' => $validated['type'],
            // Decimal euros in, integer cents stored, like every other amount
            // in the shop.
            'total_cents' => (int) round($validated['total'] * 100),
            'fees_cents' => (int) round(($validated['fees'] ?? 0) * 100),
            'payment_method' => $validated['payment_method'],
            'remark' => $validated['remark'] ?? null,
        ];

        if (! $keepAuthor) {
            $payload['created_by_user_id'] = Auth::id();
        }

        return $payload;
    }
}
