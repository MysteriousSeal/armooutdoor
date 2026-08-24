<?php

namespace App\Http\Resources\Api\Admin;

use App\Models\BlogCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BlogCategory */
class BlogCategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->localizedName(),
            'description' => $this->localizedDescription(),
            'sort_order' => $this->sort_order,
            'posts_count' => $this->whenCounted('posts'),
        ];
    }
}
