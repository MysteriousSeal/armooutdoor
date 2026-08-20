<?php

namespace App\Http\Requests\Admin;

use App\Models\DiscountCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDiscountCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $discountCode = $this->route('discountCode');

        return [
            'code' => [
                'required',
                'string',
                'max:40',
                'regex:/^[A-Z0-9-]+$/',
                Rule::unique('discount_codes', 'code')->ignore($discountCode),
            ],
            'type' => ['required', Rule::in([
                DiscountCode::TYPE_PERCENTAGE,
                DiscountCode::TYPE_FIXED,
                DiscountCode::TYPE_FREE_RELAY_SHIPPING,
            ])],
            // A free-delivery code has no amount, so whatever the form posts
            // is ignored rather than validated — a stale value left in the
            // hidden field must never be able to block the save.
            'value' => $this->input('type') === DiscountCode::TYPE_FREE_RELAY_SHIPPING
                ? ['nullable']
                : [
                    'required',
                    'numeric',
                    'min:0.01',
                    $this->input('type') === DiscountCode::TYPE_PERCENTAGE ? 'max:100' : 'max:99999.99',
                ],
            'user_id' => ['nullable', 'exists:users,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'max_uses_per_customer' => [
                'nullable',
                'integer',
                'min:1',
                Rule::when($this->filled('quantity'), ['lte:quantity']),
            ],
            'ends_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'code',
            'type' => 'type',
            'value' => 'value',
            'user_id' => 'customer',
            'quantity' => 'quantity',
            'max_uses_per_customer' => 'max uses per customer',
            'ends_at' => 'deadline',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Enter a code.',
            'code.regex' => 'Use only letters, numbers, and hyphens.',
            'code.unique' => 'That code is already in use.',
            'type.required' => 'Choose a discount type.',
            'value.required' => 'Enter a discount value.',
            'value.max' => 'The value is too high for this discount type.',
            'user_id.exists' => 'That customer could not be found.',
            'quantity.min' => 'Quantity must be at least 1, or left blank for unlimited.',
            'max_uses_per_customer.min' => 'Max uses per customer must be at least 1, or left blank for unlimited.',
            'max_uses_per_customer.lte' => 'Max uses per customer can\'t be higher than the total quantity available.',
        ];
    }
}
