<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCarrierPriceTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Each carrier's modal is its own form on the same page, so a
        // failure needs its own error bag to reopen just that modal.
        $this->errorBag = 'carrierTiers'.$this->route('carrier')->id;

        return [
            'default_price' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'tiers' => ['nullable', 'array'],
            'tiers.*.min_weight' => ['required', 'integer', 'min:0', 'max:999999'],
            'tiers.*.price' => ['required', 'numeric', 'min:0', 'max:9999.99'],
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator): void {
            $seen = [];

            foreach ((array) $this->input('tiers', []) as $index => $row) {
                $weight = $row['min_weight'] ?? null;

                if ($weight === null || $weight === '') {
                    continue;
                }

                if (isset($seen[$weight])) {
                    $validator->errors()->add("tiers.{$index}.min_weight", 'This weight is already used by another tier.');

                    continue;
                }

                $seen[$weight] = true;
            }
        });
    }
}
