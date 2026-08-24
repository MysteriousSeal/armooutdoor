<?php

namespace App\Http\Requests\Api\Admin;

/** Modification partielle : on ne touche qu'aux champs envoyés. */
class UpdateProductRequest extends ProductPayloadRequest
{
    protected function presence(): string
    {
        return 'sometimes';
    }
}
