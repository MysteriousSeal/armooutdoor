<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePurchaseOrderRequest extends FormRequest
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
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'reference' => ['nullable', 'string', 'max:120'],
            'expected_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'shipping_price' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'discount_price' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'additional_costs_price' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            // Les prix saisis sont HT par défaut ; un taux non nul signifie
            // « ce que j'ai tapé est TTC à ce taux » et tout est converti.
            'vat_rate' => ['nullable', Rule::in(['0', '5.5', '10', '20'])],

            'items' => ['required', 'array'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'items.*.cost' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->filledItems()->isEmpty()) {
                    $validator->errors()->add('items', 'Add at least one product.');
                }
            },
        ];
    }

    /**
     * @return Collection<int, array{product_id: int, variant_id: ?int, quantity: int, cost: ?string}>
     */
    public function filledItems(): Collection
    {
        return collect($this->input('items', []))
            ->filter(fn (array $row): bool => filled($row['product_id'] ?? null) && filled($row['quantity'] ?? null))
            ->map(fn (array $row): array => [
                'product_id' => (int) $row['product_id'],
                'variant_id' => filled($row['variant_id'] ?? null) ? (int) $row['variant_id'] : null,
                'quantity' => (int) $row['quantity'],
                'cost' => $row['cost'] ?? null,
            ])
            ->values();
    }

    public function shippingCents(): int
    {
        $price = $this->input('shipping_price');

        return filled($price) ? $this->toExVatCents((string) $price) : 0;
    }

    public function discountCents(): int
    {
        $price = $this->input('discount_price');

        return filled($price) ? $this->toExVatCents((string) $price) : 0;
    }

    public function additionalCostsCents(): int
    {
        $price = $this->input('additional_costs_price');

        return filled($price) ? $this->toExVatCents((string) $price) : 0;
    }

    public function vatRateBasisPoints(): int
    {
        return (int) round(((float) ($this->input('vat_rate') ?: 0)) * 100);
    }

    /**
     * A price typed as the supplier displays it, brought back to excl. VAT.
     * The rate is a typing aid: nothing about VAT is stored on the order.
     */
    public function toExVatCents(string $price): int
    {
        $rate = (float) ($this->input('vat_rate') ?: 0);

        return (int) round(((float) $price) * 100 / (1 + $rate / 100));
    }
}
