<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** A Vinted Go order's locker, typed by hand once it is known. */
class UpdateOrderRelayPointRequest extends FormRequest
{
    protected $errorBag = 'relayPoint';

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'relay_name' => ['required', 'string', 'max:120'],
            'relay_line1' => ['required', 'string', 'max:120'],
            'relay_postal_code' => ['required', 'string', 'max:12'],
            'relay_city' => ['required', 'string', 'max:80'],
        ];
    }

    public function attributes(): array
    {
        return [
            'relay_name' => 'name',
            'relay_line1' => 'address',
            'relay_postal_code' => 'postal code',
            'relay_city' => 'city',
        ];
    }
}
