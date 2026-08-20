<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
            'order_id' => [
                'nullable',
                Rule::exists('orders', 'id')->where(function ($query) {
                    $query->where('user_id', $this->user()?->id)
                        ->whereNull('archived_at')
                        ->where('status', '!=', 'draft');
                }),
            ],
            'website' => ['prohibited'],
        ];
    }
}
