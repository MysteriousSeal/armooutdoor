<?php

namespace App\Http\Requests\Api\Admin;

/** Création d'une catégorie par l'API : nom et description exigés. */
class StoreCategoryRequest extends CategoryPayloadRequest
{
    protected function presence(): string
    {
        return 'required';
    }
}
