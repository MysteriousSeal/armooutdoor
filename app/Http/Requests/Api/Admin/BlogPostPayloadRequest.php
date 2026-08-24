<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Middleware\EnsureAdminApiToken;
use App\Models\BlogPost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Les règles communes à la création et à la modification d'un article.
 *
 * Une seule différence entre les deux : ce qui est obligatoire.
 */
abstract class BlogPostPayloadRequest extends FormRequest
{
    /** L'accès vient du middleware, pas de la requête elle-même. */
    public function authorize(): bool
    {
        return $this->attributes->get(EnsureAdminApiToken::VERIFIED_ATTRIBUTE) === true;
    }

    abstract protected function presence(): string;

    protected function editedPost(): ?BlogPost
    {
        $post = $this->route('post');

        return $post instanceof BlogPost ? $post : null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $required = $this->presence();

        return [
            'title' => [$required, 'string', 'max:180'],
            'body' => [$required, 'string', 'max:200000'],
            'blog_category_id' => [$required, 'integer', 'exists:blog_categories,id'],

            'excerpt' => ['sometimes', 'nullable', 'string', 'max:300'],
            'status' => ['sometimes', Rule::in(['draft', 'published'])],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:180'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:300'],
            'image' => ['sometimes', 'nullable', 'string', 'max:2048'],

            'product_ids' => ['sometimes', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $this->rejectPublishedWithoutADate($validator);
        });
    }

    /**
     * Publié sans date : un article qui se lit comme en ligne et que le
     * périmètre public écartera toujours. Mieux vaut refuser franchement.
     */
    private function rejectPublishedWithoutADate($validator): void
    {
        $post = $this->editedPost();

        $status = $this->has('status') ? $this->input('status') : $post?->status;

        if ($status !== 'published') {
            return;
        }

        $date = $this->has('published_at')
            ? $this->input('published_at')
            : $post?->published_at;

        if (blank($date)) {
            $validator->errors()->add(
                'published_at',
                'A published post needs a publication date. Send published_at, or keep the status as draft.',
            );
        }
    }
}
