<?php

namespace App\Http\Requests\Admin;

use App\Models\AccountingEntry;
use App\Support\AccountingPeriods;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/** Une écriture saisie à la main dans le journal comptable. */
class StoreAccountingEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()?->isOwner() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'entered_on' => ['required', 'date'],
            'invoice_number' => ['nullable', 'string', 'max:80'],
            'client' => ['nullable', 'string', 'max:120'],
            'channel' => ['nullable', 'string', 'max:80'],
            'type' => ['required', Rule::in(array_keys(AccountingEntry::TYPES))],
            'total' => ['required', 'numeric', 'min:-99999.99', 'max:99999.99'],
            // Les frais se saisissent en positif et se retiennent : le signe
            // se lit dans la colonne, pas dans la saisie.
            'fees' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'payment_method' => ['required', Rule::in(array_keys(AccountingEntry::PAYMENT_METHODS))],
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * La date doit tomber dans le mois qu'on est en train de lire.
     *
     * Sans quoi l'écriture serait enregistrée puis disparaîtrait de la page
     * qui vient de l'accepter.
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

    /** @return array<string, mixed> */
    public function entryPayload(string $section, CarbonImmutable $period, bool $keepAuthor = false): array
    {
        $validated = $this->validated();

        $payload = [
            'section' => $section,
            'entered_on' => $validated['entered_on'],
            'invoice_number' => $validated['invoice_number'] ?? null,
            'client' => $validated['client'] ?? null,
            'channel' => $validated['channel'] ?? null,
            'type' => $validated['type'],
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
