<?php

namespace App\Http\Requests\Api\Admin;

/** Modification partielle : on ne touche qu'aux champs envoyés. */
class UpdateBlogPostRequest extends BlogPostPayloadRequest
{
    protected function presence(): string
    {
        return 'sometimes';
    }
}
