<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Middleware\EnsureAdminApiToken;
use App\Models\Discount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Rules shared by creating and editing a product discount over the API:
 * only whether the fields are required separates the two.
 */
abstract class DiscountPayloadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->get(EnsureAdminApiToken::VERIFIED_ATTRIBUTE) === true;
    }

    /** `sometimes` when editing, `required` when creating. */
    abstract protected function presence(): string;

    protected function editedDiscount(): ?Discount
    {
        $discount = $this->route('discount');

        return $discount instanceof Discount ? $discount : null;
    }

    /** The type the discount will have once saved, sent or kept. */
    protected function effectiveType(): ?string
    {
        return $this->input('type', $this->editedDiscount()?->type);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $discount = $this->editedDiscount();

        return [
            'product_id' => [
                $this->presence(),
                'integer',
                'exists:products,id',
                Rule::unique('discounts', 'product_id')->ignore($discount),
            ],
            'type' => [$this->presence(), Rule::in(['percentage', 'fixed'])],
            // A percentage (1-100) or an amount in euros, like the web form.
            'value' => [
                $this->presence(),
                'numeric',
                'min:0.01',
                $this->effectiveType() === 'percentage' ? 'max:100' : 'max:99999.99',
            ],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'product_id.exists' => 'That product could not be found.',
            'product_id.unique' => 'That product already has a discount.',
            'type.in' => 'Choose a percentage or a fixed amount.',
            'value.min' => 'The value must be greater than 0.',
            'value.max' => 'The value is too high for this discount type.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $discount = $this->editedDiscount();
            $errors = $validator->errors();

            // The unit of `value` depends on the type, so switching type
            // without restating the value would silently change the price.
            if ($discount !== null
                && $this->has('type')
                && $this->input('type') !== $discount->type
                && ! $this->has('value')) {
                $errors->add('value', 'Send the value again when changing the discount type.');
            }

            if ($errors->hasAny(['starts_at', 'ends_at'])) {
                return;
            }

            $startsAt = $this->has('starts_at') ? $this->input('starts_at') : $discount?->starts_at;
            $endsAt = $this->has('ends_at') ? $this->input('ends_at') : $discount?->ends_at;

            if (filled($startsAt) && filled($endsAt)
                && Carbon::parse($endsAt)->lt(Carbon::parse($startsAt))) {
                $errors->add('ends_at', 'The end date must be on or after the start date.');
            }
        });
    }
}
