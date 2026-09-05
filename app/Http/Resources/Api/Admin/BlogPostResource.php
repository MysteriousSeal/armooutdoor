<?php

namespace App\Http\Resources\Api\Admin;

use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * La forme d'un article dans l'API d'administration.
 *
 * `is_visible` et `is_scheduled` sont calculés plutôt que déduits par le
 * client : la règle de visibilité tient en trois conditions, et la laisser
 * réimplémenter ailleurs est le moyen le plus sûr de la voir diverger.
 *
 * @mixin BlogPost
 */
class BlogPostResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->localizedTitle(),
            'excerpt' => $this->localizedExcerpt(),
            'body' => $this->localizedBody(),
            'blog_category_id' => $this->blog_category_id,
            'category' => $this->whenLoaded('category', fn (): array => [
                'id' => $this->category->id,
                'slug' => $this->category->slug,
                'name' => $this->category->localizedName(),
            ]),
            'status' => $this->status,
            'published_at' => $this->published_at?->toIso8601String(),
            'is_visible' => $this->isVisible(),
            'is_scheduled' => $this->isScheduled(),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'sources' => $this->sources,
            'image' => $this->image,
            'image_credit' => $this->image_credit,
            'url' => $this->isVisible() ? route('blog.show', $this->slug) : null,
            'products' => $this->whenLoaded('products', fn (): array => $this->products
                ->map(fn ($product): array => [
                    'id' => $product->id,
                    'slug' => $product->slug,
                    'name' => $product->localizedName(),
                ])->all()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
