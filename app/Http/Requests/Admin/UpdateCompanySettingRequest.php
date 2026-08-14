<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanySettingRequest extends FormRequest
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
            'company_name' => ['nullable', 'string', 'max:160'],
            'legal_form' => ['nullable', 'string', 'max:80'],
            'share_capital' => ['nullable', 'string', 'max:80'],
            'siret' => ['nullable', 'string', 'max:20'],
            'vat_number' => ['nullable', 'string', 'max:30'],
            'vat_exempt' => ['sometimes', 'boolean'],
            'address' => ['nullable', 'string', 'max:255'],
            'publication_director' => ['nullable', 'string', 'max:160'],
            'host_name' => ['nullable', 'string', 'max:160'],
            'host_address' => ['nullable', 'string', 'max:255'],
            'host_phone' => ['nullable', 'string', 'max:30'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:30'],
            'return_address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
