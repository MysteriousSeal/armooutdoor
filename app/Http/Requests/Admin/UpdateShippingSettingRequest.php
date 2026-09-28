<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShippingSettingRequest extends FormRequest
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
        return [
            'free_shipping_threshold' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'free_shipping_carrier_ids' => ['nullable', 'array'],
            // A manual-only carrier is never offered at checkout, so it cannot be free there.
            'free_shipping_carrier_ids.*' => ['integer', Rule::exists('carriers', 'id')->where('manual_only', 0)],
        ];
    }
}
