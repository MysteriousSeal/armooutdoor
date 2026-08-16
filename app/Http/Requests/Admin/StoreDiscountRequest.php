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

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'product_id' => 'product',
            'type' => 'type',
            'value' => 'value',
            'starts_at' => 'start date',
            'ends_at' => 'end date',
        ];
    }

    /**
     * Keep admin validation in English even though the shop locale is French.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.required' => 'Choose a product.',
            'product_id.exists' => 'That product could not be found.',
            'product_id.unique' => 'That product already has a discount.',
            'type.required' => 'Choose a discount type.',
            'type.in' => 'Choose a percentage or a fixed amount.',
            'value.required' => 'Enter a discount value.',
            'value.numeric' => 'The value must be a number.',
            'value.min' => 'The value must be greater than 0.',
            'value.max' => 'The value is too high for this discount type.',
            'starts_at.date' => 'Enter a valid start date.',
            'ends_at.date' => 'Enter a valid end date.',
            'ends_at.after_or_equal' => 'The end date must be on or after the start date.',
        ];
    }
}
