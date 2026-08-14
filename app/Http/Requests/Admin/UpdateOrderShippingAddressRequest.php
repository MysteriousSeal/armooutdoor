<?php

namespace App\Http\Requests\Admin;

class UpdateOrderShippingAddressRequest extends UpdateOrderAddressRequest
{
    protected $errorBag = 'shippingAddress';
}
