<?php

namespace App\Http\Requests\Admin;

use App\Models\BlogPost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBlogPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $post = $this->route('post');

        return [
            'title' => ['required', 'string', 'max:180'],
            'blog_category_id' => ['required', 'exists:blog_categories,id'],
            // C'est aussi la méta-description : au-delà, Google coupe.
            'excerpt' => ['nullable', 'string', 'max:300'],
            'body' => ['required', 'string', 'max:200000'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'published_at' => ['nullable', 'date'],
            'meta_title' => ['nullable', 'string', 'max:180'],
            'meta_description' => ['nullable', 'string', 'max:300'],
            'image_file' => ['nullable', 'image', 'max:8192'],
            'image_credit' => ['nullable', 'string', 'max:180'],
            'remove_image' => ['sometimes', 'boolean'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'slug' => [
                'nullable',
                'string',
                'max:200',
                Rule::unique('blog_posts', 'slug')->ignore($post instanceof BlogPost ? $post->id : null),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            // Publier sans dire quand laisse l'article invisible pour toujours :
            // le formulaire doit le refuser plutôt que de le ranger ainsi.
            if ($this->input('status') === 'published' && ! $this->filled('published_at')) {
                $validator->errors()->add('published_at', 'A published post needs a publication date.');
            }
        });
    }
}
