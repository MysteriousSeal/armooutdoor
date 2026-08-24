<?php

namespace App\Http\Requests\Api\Admin;

/** Création : le minimum vital est exigé. */
class StoreBlogPostRequest extends BlogPostPayloadRequest
{
    protected function presence(): string
    {
        return 'required';
    }
}
