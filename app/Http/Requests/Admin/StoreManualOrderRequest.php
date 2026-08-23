<?php

namespace App\Http\Requests\Admin;

use App\Models\Carrier;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
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
        $order = $this->route('order');
        $existingExternalUserId = $order?->user?->external === true ? $order->user_id : null;

        return [
            'action' => ['required', Rule::in(['draft', 'placed'])],
            'customer_mode' => ['required', Rule::in(['existing', 'new'])],
            'customer_id' => ['nullable', Rule::requiredIf($this->input('customer_mode') === 'existing'), 'exists:users,id'],
            'new_customer_first_name' => ['nullable', Rule::requiredIf($this->input('customer_mode') === 'new'), 'string', 'max:80'],
            'new_customer_last_name' => ['nullable', Rule::requiredIf($this->input('customer_mode') === 'new'), 'string', 'max:80'],
            'new_customer_email' => [
                'nullable',
                'email',
                'max:160',
                Rule::unique('users', 'email')->ignore($existingExternalUserId),
            ],

            'items' => ['required', 'array'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],

            'carrier_id' => ['required', Rule::exists('carriers', 'id')->where('active', true)],
            'relay.slug' => ['nullable', 'string', 'max:120'],
            'relay.name' => ['nullable', 'string', 'max:120'],
            'relay.line1' => ['nullable', 'string', 'max:120'],
            'relay.postal_code' => ['nullable', 'string', 'max:12'],
            'relay.city' => ['nullable', 'string', 'max:80'],
            'shipping_price' => ['nullable', 'numeric', 'min:0'],
            'marketplace_id' => ['nullable', 'exists:marketplaces,id'],

            'discount_type' => ['nullable', Rule::in(['percentage', 'fixed'])],
            'discount_value' => [
                'nullable',
                Rule::requiredIf(fn (): bool => filled($this->input('discount_type'))),
                'numeric',
                'min:0.01',
                $this->input('discount_type') === 'percentage' ? 'max:100' : 'max:99999.99',
            ],

            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'line1' => ['required', 'string', 'max:120'],
            'line2' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['required', 'string', 'max:12'],
            'city' => ['required', 'string', 'max:80'],
            'country' => ['required', 'string', Rule::in(config('shop.countries'))],
            'phone' => ['nullable', 'string', 'max:30'],

            'billing_first_name' => ['required', 'string', 'max:80'],
            'billing_last_name' => ['required', 'string', 'max:80'],
            'billing_line1' => ['required', 'string', 'max:120'],
            'billing_line2' => ['nullable', 'string', 'max:120'],
            'billing_postal_code' => ['required', 'string', 'max:12'],
            'billing_city' => ['required', 'string', 'max:80'],
            'billing_country' => ['required', 'string', Rule::in(config('shop.countries'))],
            'billing_phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function after(): array
    {
        return [
            // Un colis en point relais part sans savoir où il est retiré : le
            // brouillon peut rester incomplet, la commande finalisée non.
            function (Validator $validator): void {
                if ($this->input('action') !== 'placed') {
                    return;
                }

                $carrier = Carrier::query()->find($this->input('carrier_id'));

                if ($carrier?->isRelay() !== true) {
                    return;
                }

                foreach (['name', 'line1', 'postal_code', 'city'] as $field) {
                    if (blank($this->input('relay.'.$field))) {
                        $validator->errors()->add('relay.'.$field, 'Required for a pickup point delivery.');
                    }
                }
            },
            function (Validator $validator): void {
                $rows = collect($this->input('items', []))
                    ->filter(fn (array $row): bool => filled($row['product_id'] ?? null) && filled($row['quantity'] ?? null));

                if ($rows->isEmpty()) {
                    $validator->errors()->add('items', 'Add at least one product.');

                    return;
                }

                foreach ($rows as $index => $row) {
                    $product = Product::query()->with('variants')->find($row['product_id']);

                    if ($product === null || ! $product->is_active) {
                        $validator->errors()->add("items.{$index}.product_id", 'This product is not available.');

                        continue;
                    }

                    $variant = filled($row['variant_id'] ?? null)
                        ? $product->variants->firstWhere('id', (int) $row['variant_id'])
                        : null;

                    if ($product->hasVariants()) {
                        if (blank($row['variant_id'] ?? null)) {
                            $validator->errors()->add("items.{$index}.variant_id", 'Select a variant.');

                            continue;
                        }

                        if ($variant === null || ! $variant->is_active) {
                            $validator->errors()->add("items.{$index}.variant_id", 'This variant is not available.');

                            continue;
                        }
                    }

                    $availableQuantity = $variant?->quantity ?? $product->quantity;

                    if ((int) $row['quantity'] > $availableQuantity) {
                        $validator->errors()->add("items.{$index}.quantity", 'Only '.$availableQuantity.' in stock.');
                    }
                }
            },
        ];
    }

    /**
     * The pickup point as it will be frozen on the order, or null when nothing
     * was given. Kept as a snapshot rather than a link: a marketplace relay is
     * not in the carrier's own list, so there is no row to point at.
     *
     * @return array{slug: ?string, name: string, line1: string, postal_code: string, city: string, country: string, hours: null}|null
     */
    public function relaySnapshot(): ?array
    {
        if (blank($this->input('relay.name'))) {
            return null;
        }

        return [
            'slug' => $this->input('relay.slug') ?: null,
            'name' => (string) $this->input('relay.name'),
            'line1' => (string) $this->input('relay.line1'),
            'postal_code' => (string) $this->input('relay.postal_code'),
            'city' => (string) $this->input('relay.city'),
            'country' => (string) $this->input('country'),
            'hours' => null,
        ];
    }

    /**
     * @return Collection<int, array{product_id: int, variant_id: ?int, quantity: int, unit_price_cents: int}>
     */
    public function validItems(): Collection
    {
        return $this->rawItems()
            // Deux lignes du même article sont une seule ligne : les laisser
            // séparées ferait vérifier chacune contre le stock entier, puis
            // retrancher les deux — et le stock passerait sous zéro.
            ->groupBy(fn (array $item): string => $item['product_id'].':'.($item['variant_id'] ?? ''))
            ->map(fn (Collection $group): array => [
                ...$group->first(),
                'quantity' => $group->sum('quantity'),
            ])
            ->values();
    }

    /**
     * @return Collection<int, array{product_id: int, variant_id: ?int, quantity: int, unit_price_cents: int}>
     */
    private function rawItems(): Collection
    {
        return collect($this->input('items', []))
            ->filter(fn (array $row): bool => filled($row['product_id'] ?? null) && filled($row['quantity'] ?? null))
            ->map(function (array $row): array {
                $product = Product::query()->with('variants')->find($row['product_id']);
                $variant = filled($row['variant_id'] ?? null)
                    ? $product?->variants->firstWhere('id', (int) $row['variant_id'])
                    : null;

                $unitPriceCents = match (true) {
                    filled($row['price'] ?? null) => (int) round(((float) $row['price']) * 100),
                    $variant !== null => $variant->effectivePriceCents(),
                    default => (int) ($product?->price_cents ?? 0),
                };

                return [
                    'product_id' => (int) $row['product_id'],
                    'variant_id' => $variant?->id,
                    'quantity' => (int) $row['quantity'],
                    'unit_price_cents' => $unitPriceCents,
                ];
            })
            ->values();
    }
}
