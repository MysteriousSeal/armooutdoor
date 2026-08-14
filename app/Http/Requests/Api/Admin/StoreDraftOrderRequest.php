<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Admin\StoreManualOrderRequest;

class StoreDraftOrderRequest extends StoreManualOrderRequest
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
            ...parent::rules(),
            // This API only ever creates/updates drafts — the "action" field
            // from the admin form (draft vs finalize) doesn't apply here.
            'action' => ['sometimes', 'string'],
        ];
    }
}
