<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['sometimes', 'string', 'max:50000'],
            'category_id' => ['sometimes', 'exists:categories,id'],
            'price' => ['sometimes', 'numeric', 'min:0', 'max:99999.99'],
            'quantity' => ['sometimes', 'integer', 'min:0', 'max:99999'],
            'is_active' => ['sometimes', 'boolean'],
            'age_restricted' => ['sometimes', 'boolean'],
            'sku' => [
                'sometimes',
                'nullable',
                'string',
                'max:64',
                Rule::unique('products', 'sku')->ignore($product),
            ],
            'gtin' => [
                'sometimes',
                'nullable',
                'string',
                'regex:/^(\d{8}|\d{12,14})$/',
                Rule::unique('products', 'gtin')->ignore($product),
            ],
            'characteristics' => ['sometimes', 'array'],
            'characteristics.*.label' => ['required', 'string', 'max:120'],
            'characteristics.*.value' => ['required', 'string', 'max:500'],
        ];
    }
}
