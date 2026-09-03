<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
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
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:2000'],
            'guide' => ['nullable', 'string', 'max:30000'],
            'parent_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('parent_id', null),
                Rule::notIn([$category?->id]),
            ],
            'slug' => [
                'nullable',
                'string',
                'max:80',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('categories', 'slug')->ignore($category),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'image' => ['nullable', 'image', 'max:8192'],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $category = $this->route('category');

            if ($category !== null
                && $this->filled('parent_id')
                && (int) $this->input('parent_id') !== 0
                && $category->children()->exists()) {
                $validator->errors()->add('parent_id', 'This category has subcategories, so it cannot become a subcategory itself.');
            }
        });
    }
}
