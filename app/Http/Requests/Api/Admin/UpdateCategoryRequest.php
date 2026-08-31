<?php

namespace App\Http\Requests\Api\Admin;

/** Modification : chaque champ est optionnel, seuls les présents changent. */
class UpdateCategoryRequest extends CategoryPayloadRequest
{
    protected function presence(): string
    {
        return 'sometimes';
    }
}
