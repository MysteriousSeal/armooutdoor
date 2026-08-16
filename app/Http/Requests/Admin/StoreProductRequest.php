<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
        $product = $this->route('product');

        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:50000'],
            'category_id' => ['required', 'exists:categories,id'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'quantity' => ['required', 'integer', 'min:0', 'max:99999'],
            'weight_grams' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'carrier_ids' => ['nullable', 'array'],
            'carrier_ids.*' => ['integer', 'exists:carriers,id'],
            'is_active' => ['sometimes', 'boolean'],
            'age_restricted' => ['sometimes', 'boolean'],
            'sku' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('products', 'sku')->ignore($product),
            ],
            'gtin' => [
                'nullable',
                'string',
                'regex:/^(\d{8}|\d{12,14})$/',
                Rule::unique('products', 'gtin')->ignore($product),
            ],
            'characteristic_label' => ['nullable', 'array'],
            'characteristic_label.*' => ['nullable', 'string', 'max:120'],
            'characteristic_value' => ['nullable', 'array'],
            'characteristic_value.*' => ['nullable', 'string', 'max:500'],
            'filter_label' => ['nullable', 'array'],
            'filter_label.*' => ['nullable', 'string', 'max:120'],
            'filter_value' => ['nullable', 'array'],
            'filter_value.*' => ['nullable', 'string', 'max:500'],
            'image_file' => ['nullable', 'image', 'max:4096'],
            'gallery_images' => ['nullable', 'array'],
            'gallery_images.*' => ['image', 'max:4096'],
            'remove_gallery_images' => ['nullable', 'array'],
            'remove_gallery_images.*' => ['integer'],
            'remove_main' => ['sometimes', 'boolean'],
            'gallery_order' => ['nullable', 'string'],

            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*._delete' => ['nullable', 'boolean'],
            'variants.*.attributes_text' => ['nullable', 'string', 'max:500'],
            'variants.*.sku' => ['nullable', 'string', 'max:64'],
            'variants.*.gtin' => ['nullable', 'string', 'regex:/^(\d{8}|\d{12,14})$/'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'variants.*.quantity' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'variants.*.is_active' => ['nullable', 'boolean'],
            'variants.*.remove_image' => ['nullable', 'boolean'],
            'variant_images' => ['nullable', 'array'],
            'variant_images.*' => ['nullable', 'image', 'max:4096'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $product = $this->route('product');

            if ($product === null
                && ! $this->hasFile('image_file')
                && ! $this->hasFile('gallery_images')) {
                $validator->errors()->add('gallery_images', 'Add at least one product photo.');
            }

            $this->validateVariantUniqueness($validator, $product);
        });
    }

    private function validateVariantUniqueness($validator, ?\App\Models\Product $product): void
    {
        $rows = (array) $this->input('variants', []);

        foreach (['sku', 'gtin'] as $field) {
            $seen = [];

            foreach ($rows as $index => $row) {
                if (! empty($row['_delete'])) {
                    continue;
                }

                $variantId = filled($row['id'] ?? null) ? (int) $row['id'] : null;

                $value = trim((string) ($row[$field] ?? ''));

                if ($value === '') {
                    continue;
                }

                if (isset($seen[$value])) {
                    $validator->errors()->add("variants.{$index}.{$field}", 'This '.strtoupper($field).' is already used by another variant below.');

                    continue;
                }

                $seen[$value] = true;

                $exists = \App\Models\ProductVariant::query()
                    ->where($field, $value)
                    ->when($variantId, fn ($query) => $query->where('id', '!=', $variantId))
                    ->exists();

                if ($exists) {
                    $validator->errors()->add("variants.{$index}.{$field}", 'This '.strtoupper($field).' is already used by another variant.');
                }
            }
        }
    }
}
