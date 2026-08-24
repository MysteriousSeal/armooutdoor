<?php

namespace App\Http\Requests\Api\Admin;

/** Création d'un produit par l'API : le minimum vital est exigé. */
class StoreProductRequest extends ProductPayloadRequest
{
    protected function presence(): string
    {
        return 'required';
    }
}
