<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

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
            'free_shipping_carrier_ids.*' => ['integer', 'exists:carriers,id'],
        ];
    }
}
