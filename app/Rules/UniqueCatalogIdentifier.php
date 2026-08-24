<?php

namespace App\Rules;

use App\Models\Product;
use App\Models\ProductVariant;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Un SKU ou un GTIN ne doit désigner qu'une seule chose à vendre.
 *
 * Les deux vivent dans deux tables — le produit simple porte le sien, le
 * produit à déclinaisons le porte sur chaque taille — et chaque table
 * vérifiait son unicité de son côté. Un produit et une déclinaison pouvaient
 * donc annoncer le même code-barres, ce qu'un flux Shopify ou Google refuse.
 * Cette règle regarde les deux tables à la fois.
 */
class UniqueCatalogIdentifier implements ValidationRule
{
    /**
     * @param  'sku'|'gtin'  $field
     * @param  int|null  $ignoreProductId  Le produit en cours d'édition.
     * @param  int|null  $ignoreVariantId  La déclinaison en cours d'édition.
     */
    public function __construct(
        private readonly string $field,
        private readonly ?int $ignoreProductId = null,
        private readonly ?int $ignoreVariantId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = trim((string) $value);

        if ($value === '') {
            return;
        }

        $onProduct = Product::query()
            ->where($this->field, $value)
            ->when($this->ignoreProductId, fn ($query) => $query->whereKeyNot($this->ignoreProductId))
            ->exists();

        if ($onProduct) {
            $fail('The :attribute is already used by another product.');

            return;
        }

        $onVariant = ProductVariant::query()
            ->where($this->field, $value)
            ->when($this->ignoreVariantId, fn ($query) => $query->whereKeyNot($this->ignoreVariantId))
            ->exists();

        if ($onVariant) {
            $fail('The :attribute is already used by a product variant.');
        }
    }
}
