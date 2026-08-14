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
            'is_active' => ['sometimes', 'boolean'],
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
            'image_file' => ['nullable', 'image', 'max:4096'],
            'gallery_images' => ['nullable', 'array'],
            'gallery_images.*' => ['image', 'max:4096'],
            'remove_gallery_images' => ['nullable', 'array'],
            'remove_gallery_images.*' => ['integer'],
            'remove_main' => ['sometimes', 'boolean'],
            'gallery_order' => ['nullable', 'string'],
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
        });
    }
}
