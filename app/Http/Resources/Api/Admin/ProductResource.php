<?php

namespace App\Http\Resources\Api\Admin;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * La forme qu'un produit prend dans l'API d'administration.
 *
 * Le contrôleur renvoyait le modèle tel quel, donc toutes ses colonnes —
 * dont le prix d'achat et la marge. Le jeton d'API est unique et partagé :
 * ce qui sort ici sort pour tout ce qui le détient. Le prix d'achat et le
 * taux de marge restent donc en écriture seule, modifiables par l'API mais
 * jamais relus par elle.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->localizedName(),
            'description' => $this->localizedDescription(),
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn (): array => [
                'id' => $this->category->id,
                'slug' => $this->category->slug,
                'name' => $this->category->localizedName(),
            ]),
            'price_cents' => $this->price_cents,
            'quantity' => $this->quantity,
            'sku' => $this->sku,
            'gtin' => $this->gtin,
            'is_active' => (bool) $this->is_active,
            'age_restricted' => (bool) $this->age_restricted,
            'image_may_vary' => (bool) $this->image_may_vary,
            'featured' => (bool) $this->featured,
            'sort_order' => $this->sort_order,
            'weight_grams' => $this->weight_grams,
            'carrier_ids' => $this->carrier_ids ?? [],
            'characteristics' => $this->characteristics ?? [],
            'filter_attributes' => $this->filter_attributes ?? [],
            'supplier_id' => $this->supplier_id,
            'available_at_supplier' => (bool) $this->available_at_supplier,
            'supplier_reference' => $this->supplier_reference,
            'supplier_product_url' => $this->supplier_product_url,
            'image' => $this->image,
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'has_variants' => $this->whenLoaded('variants', fn (): bool => $this->variants->isNotEmpty()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
