<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:40'],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'line1' => ['required', 'string', 'max:120'],
            'line2' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['required', 'string', 'max:12'],
            'city' => ['required', 'string', 'max:80'],
            'country' => ['required', 'string', Rule::in(config('shop.countries'))],
            'phone' => ['required', 'string', 'max:30'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
