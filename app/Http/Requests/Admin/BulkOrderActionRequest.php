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

    /**
     * The app locale is French, so the default messages would arrive in
     * French on an English admin page — and naming the field would leak
     * "order_ids.0" at the reader.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'order_ids.required' => 'Select at least one order.',
            'order_ids.array' => 'Select at least one order.',
            'order_ids.min' => 'Select at least one order.',
            'order_ids.max' => 'Too many orders selected at once — :max at most.',
            'order_ids.*.integer' => 'One of the selected orders is not valid.',
            'order_ids.*.exists' => 'One of the selected orders no longer exists.',
        ];
    }
}
