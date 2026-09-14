<?php

namespace App\Http\Requests\Api\Admin;

/** Editing a discount: every field is optional, only those sent change. */
class UpdateDiscountRequest extends DiscountPayloadRequest
{
    protected function presence(): string
    {
        return 'sometimes';
    }
}
