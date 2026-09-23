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

            // A line outside the catalogue: no product_id, a name of its own.
            'items.*.name' => ['nullable', 'string', 'max:255'],
            'items.*.sku' => ['nullable', 'string', 'max:100'],
            'items.*.variant_label' => ['nullable', 'string', 'max:255'],
            'items.*.weight_grams' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    protected function acceptsCustomLines(): bool
    {
        return true;
    }
}
