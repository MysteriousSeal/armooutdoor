<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCarrierSettingRequest extends FormRequest
{
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
            'carriers' => ['required', 'array'],
            'carriers.*.price' => ['required', 'numeric', 'min:0', 'max:9999.99'],
        ];
    }
}
