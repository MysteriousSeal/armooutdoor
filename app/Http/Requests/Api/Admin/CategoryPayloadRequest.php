<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Middleware\EnsureAdminApiToken;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Les règles communes à la création et à la modification d'une catégorie :
 * seule la présence obligatoire des champs sépare les deux.
 */
abstract class CategoryPayloadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->get(EnsureAdminApiToken::VERIFIED_ATTRIBUTE) === true;
    }

    /** `sometimes` en modification, `required` en création. */
    abstract protected function presence(): string;

    protected function editedCategory(): ?Category
    {
        $category = $this->route('category');

        return $category instanceof Category ? $category : null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $category = $this->editedCategory();

        return [
            'name' => [$this->presence(), 'string', 'max:120'],
            'description' => [$this->presence(), 'string', 'max:2000'],
            // Seules les catégories racines peuvent être parentes — pas
            // d'imbrication au-delà de deux niveaux, comme au back-office.
            'parent_id' => [
                'sometimes',
                'nullable',
                Rule::exists('categories', 'id')->where('parent_id', null),
                Rule::notIn([$category?->id]),
            ],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:80',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('categories', 'slug')->ignore($category),
            ],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            // Un chemin relatif à public/images ou une URL — le téléversement
            // de fichier reste le travail du back-office web.
            'image' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $category = $this->editedCategory();

            if ($category !== null
                && $this->filled('parent_id')
                && $category->children()->exists()) {
                $validator->errors()->add('parent_id', 'This category has subcategories, so it cannot become a subcategory itself.');
            }
        });
    }
}
