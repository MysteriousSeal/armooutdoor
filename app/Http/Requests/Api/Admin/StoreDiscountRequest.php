<?php

namespace App\Http\Requests\Api\Admin;

/** Creating a discount: product, type and value are required. */
class StoreDiscountRequest extends DiscountPayloadRequest
{
    protected function presence(): string
    {
        return 'required';
    }
}
