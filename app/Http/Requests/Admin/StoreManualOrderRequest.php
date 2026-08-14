<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreManualOrderRequest extends FormRequest
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
            'customer_mode' => ['required', Rule::in(['existing', 'new'])],
            'customer_id' => ['nullable', Rule::requiredIf($this->input('customer_mode') === 'existing'), 'exists:users,id'],
            'new_customer_name' => ['nullable', Rule::requiredIf($this->input('customer_mode') === 'new'), 'string', 'max:160'],
            'new_customer_email' => ['nullable', Rule::requiredIf($this->input('customer_mode') === 'new'), 'email', 'max:160', 'unique:users,email'],

            'items' => ['required', 'array'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],

            'carrier_id' => ['required', Rule::exists('carriers', 'id')->where('active', true)],

            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'line1' => ['required', 'string', 'max:120'],
            'line2' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['required', 'string', 'max:12'],
            'city' => ['required', 'string', 'max:80'],
            'country' => ['required', 'string', Rule::in(config('shop.countries'))],
            'phone' => ['required', 'string', 'max:30'],

            'billing_same_as_shipping' => ['nullable', 'boolean'],
            'billing_first_name' => ['nullable', Rule::requiredIf(! $this->boolean('billing_same_as_shipping')), 'string', 'max:80'],
            'billing_last_name' => ['nullable', Rule::requiredIf(! $this->boolean('billing_same_as_shipping')), 'string', 'max:80'],
            'billing_line1' => ['nullable', Rule::requiredIf(! $this->boolean('billing_same_as_shipping')), 'string', 'max:120'],
            'billing_line2' => ['nullable', 'string', 'max:120'],
            'billing_postal_code' => ['nullable', Rule::requiredIf(! $this->boolean('billing_same_as_shipping')), 'string', 'max:12'],
            'billing_city' => ['nullable', Rule::requiredIf(! $this->boolean('billing_same_as_shipping')), 'string', 'max:80'],
            'billing_country' => ['nullable', Rule::requiredIf(! $this->boolean('billing_same_as_shipping')), 'string', Rule::in(config('shop.countries'))],
            'billing_phone' => ['nullable', Rule::requiredIf(! $this->boolean('billing_same_as_shipping')), 'string', 'max:30'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $rows = collect($this->input('items', []))
                    ->filter(fn (array $row): bool => filled($row['product_id'] ?? null) && filled($row['quantity'] ?? null));

                if ($rows->isEmpty()) {
                    $validator->errors()->add('items', 'Add at least one product.');

                    return;
                }

                foreach ($rows as $index => $row) {
                    $product = Product::query()->find($row['product_id']);

                    if ($product === null || ! $product->is_active) {
                        $validator->errors()->add("items.{$index}.product_id", 'This product is not available.');

                        continue;
                    }

                    if ((int) $row['quantity'] > $product->quantity) {
                        $validator->errors()->add("items.{$index}.quantity", 'Only '.$product->quantity.' in stock.');
                    }
                }
            },
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{product_id: int, quantity: int}>
     */
    public function validItems(): \Illuminate\Support\Collection
    {
        return collect($this->input('items', []))
            ->filter(fn (array $row): bool => filled($row['product_id'] ?? null) && filled($row['quantity'] ?? null))
            ->map(fn (array $row): array => [
                'product_id' => (int) $row['product_id'],
                'quantity' => (int) $row['quantity'],
            ])
            ->values();
    }
}
