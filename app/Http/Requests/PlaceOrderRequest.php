<?php

namespace App\Http\Requests;

use App\Models\Carrier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PlaceOrderRequest extends FormRequest
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
            'address_id' => [
                'required',
                Rule::exists('addresses', 'id')->where('user_id', $this->user()->id),
            ],
            'same_billing_address' => ['nullable', 'boolean'],
            'billing_address_id' => [
                Rule::requiredIf(! $this->boolean('same_billing_address')),
                'nullable',
                Rule::exists('addresses', 'id')->where('user_id', $this->user()->id),
            ],
            'carrier_id' => ['required', Rule::exists('carriers', 'id')->where('active', true)],
            'relay_point_id' => ['nullable', 'exists:relay_points,id'],
            'payment_method' => ['required', Rule::in(['card', 'paypal'])],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $carrier = Carrier::query()->find($this->integer('carrier_id'));

                if ($carrier === null) {
                    return;
                }

                if ($carrier->isRelay() && ! $this->filled('relay_point_id')) {
                    $validator->errors()->add('relay_point_id', __('store.relay_required'));
                }
            },
        ];
    }
}
