<?php

namespace App\Http\Requests\Admin;

class UpdateOrderBillingAddressRequest extends UpdateOrderAddressRequest
{
    protected $errorBag = 'billingAddress';
}
