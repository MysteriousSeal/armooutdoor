<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Middleware\EnsureAdminApiToken;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->get(EnsureAdminApiToken::VERIFIED_ATTRIBUTE) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:80'],
            'last_name' => ['sometimes', 'string', 'max:80'],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('admin'))],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'role' => ['sometimes', Rule::in(['owner', 'staff'])],
        ];
    }
}
