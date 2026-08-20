<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BulkOrderActionRequest extends FormRequest
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
            // Selection is per page, so nothing legitimate exceeds the page
            // size. A larger payload is hand-made and should be refused
            // rather than run.
            'order_ids' => ['required', 'array', 'min:1', 'max:100'],
            'order_ids.*' => ['integer', 'exists:orders,id'],
        ];
    }
}
