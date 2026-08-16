<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDiscountRequest extends FormRequest
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
        $discount = $this->route('discount');

        return [
            'product_id' => [
                'required',
                'exists:products,id',
                Rule::unique('discounts', 'product_id')->ignore($discount),
            ],
            'type' => ['required', Rule::in(['percentage', 'fixed'])],
            'value' => [
                'required',
                'numeric',
                'min:0.01',
                $this->input('type') === 'percentage' ? 'max:100' : 'max:99999.99',
            ],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }
}
