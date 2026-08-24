<?php

namespace App\Http\Resources\Api\Admin;

use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Comme pour le produit, le prix d'achat n'a pas à ressortir ici.
 *
 * @mixin ProductVariant
 */
class ProductVariantResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'attributes' => $this->attribute_values ?? [],
            'sku' => $this->sku,
            'gtin' => $this->gtin,
            'price_cents' => $this->price_cents,
            'quantity' => $this->quantity,
            'is_active' => (bool) $this->is_active,
            'sort_order' => $this->sort_order,
            'image' => $this->image,
            'supplier_id' => $this->supplier_id,
            'available_at_supplier' => (bool) $this->available_at_supplier,
            'supplier_reference' => $this->supplier_reference,
            'supplier_product_url' => $this->supplier_product_url,
        ];
    }
}
