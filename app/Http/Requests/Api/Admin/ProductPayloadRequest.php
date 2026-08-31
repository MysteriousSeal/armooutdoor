<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Middleware\EnsureAdminApiToken;
use App\Models\Product;
use App\Rules\UniqueCatalogIdentifier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Les règles communes à la création et à la modification d'un produit.
 *
 * Une seule différence sépare les deux : ce qui est obligatoire. Le reste —
 * les déclinaisons, les identifiants uniques, la cohérence entre stock et
 * déclinaisons — se dit une fois ici.
 */
abstract class ProductPayloadRequest extends FormRequest
{
    /**
     * L'accès est donné par le middleware, pas par la requête.
     *
     * Retourner `true` sans condition laissait l'autorisation à la seule
     * route : montée ailleurs, la requête se serait autorisée elle-même. Le
     * middleware marque le passage, et c'est cette marque qu'on vérifie.
     */
    public function authorize(): bool
    {
        return $this->attributes->get(EnsureAdminApiToken::VERIFIED_ATTRIBUTE) === true;
    }

    /** `sometimes` en modification, `required` en création. */
    abstract protected function presence(): string;

    protected function editedProduct(): ?Product
    {
        $product = $this->route('product');

        return $product instanceof Product ? $product : null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $product = $this->editedProduct();
        $required = $this->presence();

        return [
            'name' => [$required, 'string', 'max:120'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:70'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:160'],
            'description' => [$required, 'string', 'max:50000'],
            'category_id' => [$required, 'integer', 'exists:categories,id'],
            'price' => [$required, 'numeric', 'min:0', 'max:99999.99'],

            // Le slug est l'adresse publique du produit : le changer casse
            // les liens déjà en circulation, mais il faut pouvoir le corriger.
            // Même forme que ce que fabrique `Str::slug`, et unique.
            'slug' => [
                'sometimes', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('products', 'slug')->ignore($product?->id),
                // Y compris les adresses abandonnées d'un autre produit : la
                // vieille URL redirige encore, et la reprendre l'enverrait sur
                // le mauvais article.
                Rule::unique('product_slugs', 'slug')->where(
                    fn ($query) => $product === null ? $query : $query->where('product_id', '!=', $product->id),
                ),
            ],

            'quantity' => ['sometimes', 'integer', 'min:0', 'max:99999'],
            'is_active' => ['sometimes', 'boolean'],
            'ai_validated' => ['sometimes', 'boolean'],
            'age_restricted' => ['sometimes', 'boolean'],
            'image_may_vary' => ['sometimes', 'boolean'],
            'featured' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:99999'],
            // La colonne est NOT NULL ; `null` et chaîne vide sont ramenés à
            // '' par le contrôleur plutôt que de finir en 500.
            'image' => ['sometimes', 'nullable', 'string', 'max:2048'],

            // Le poids décide du palier de frais de port : absent, il compte
            // pour zéro et la commande part sous-facturée.
            'weight_grams' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:99999'],
            'carrier_ids' => ['sometimes', 'array'],
            'carrier_ids.*' => ['integer', 'exists:carriers,id'],

            'supplier_id' => ['sometimes', 'nullable', 'integer', 'exists:suppliers,id'],
            'available_at_supplier' => ['sometimes', 'boolean'],
            'supplier_reference' => ['sometimes', 'nullable', 'string', 'max:120'],
            'supplier_product_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'supplier_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999.99'],
            'markup_percent' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1000'],

            'sku' => [
                'sometimes', 'nullable', 'string', 'max:64',
                new UniqueCatalogIdentifier('sku', $product?->id),
            ],
            'gtin' => [
                'sometimes', 'nullable', 'string', 'regex:/^(\d{8}|\d{12,14})$/',
                new UniqueCatalogIdentifier('gtin', $product?->id),
            ],

            'characteristics' => ['sometimes', 'array'],
            'characteristics.*.label' => ['required', 'string', 'max:120'],
            'characteristics.*.value' => ['required', 'string', 'max:500'],
            'filter_attributes' => ['sometimes', 'array'],
            'filter_attributes.*.label' => ['required', 'string', 'max:120'],
            'filter_attributes.*.value' => ['required', 'string', 'max:500'],

            'variants' => ['sometimes', 'array'],
            'variants.*.id' => ['sometimes', 'integer'],
            'variants.*._delete' => ['sometimes', 'boolean'],
            'variants.*.attributes' => ['sometimes', 'array'],
            'variants.*.attributes.*.label' => ['required', 'string', 'max:120'],
            'variants.*.attributes.*.value' => ['required', 'string', 'max:500'],
            'variants.*.sku' => ['sometimes', 'nullable', 'string', 'max:64'],
            'variants.*.gtin' => ['sometimes', 'nullable', 'string', 'regex:/^(\d{8}|\d{12,14})$/'],
            'variants.*.price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999.99'],
            'variants.*.quantity' => ['sometimes', 'integer', 'min:0', 'max:99999'],
            'variants.*.is_active' => ['sometimes', 'boolean'],
            'variants.*.sort_order' => ['sometimes', 'integer', 'min:0', 'max:99999'],
            'variants.*.supplier_id' => ['sometimes', 'nullable', 'integer', 'exists:suppliers,id'],
            'variants.*.available_at_supplier' => ['sometimes', 'boolean'],
            'variants.*.supplier_reference' => ['sometimes', 'nullable', 'string', 'max:120'],
            'variants.*.supplier_product_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $this->rejectQuantityOnVariantProduct($validator);
            $this->validateVariantIdentifiers($validator);
        });
    }

    /**
     * Sur un produit à déclinaisons, le stock est la somme des déclinaisons.
     *
     * L'API écrivait la valeur reçue par-dessus ce total. Elle tenait
     * jusqu'au prochain calcul, puis disparaissait sans rien dire. Mieux vaut
     * refuser franchement que ranger un chiffre qui ne survivra pas.
     */
    private function rejectQuantityOnVariantProduct($validator): void
    {
        if (! $this->has('quantity')) {
            return;
        }

        $product = $this->editedProduct();
        $keepsVariants = $product !== null && $product->variants()->exists()
            && ! $this->deletesEveryExistingVariant($product);

        $addsVariants = collect($this->input('variants', []))
            ->contains(fn ($row): bool => empty($row['_delete']));

        if ($keepsVariants || $addsVariants) {
            $validator->errors()->add(
                'quantity',
                'A product with variants takes its stock from its variants. Set the quantity on each variant instead.',
            );
        }
    }

    private function deletesEveryExistingVariant(Product $product): bool
    {
        $deleted = collect($this->input('variants', []))
            ->filter(fn ($row): bool => ! empty($row['_delete']) && filled($row['id'] ?? null))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);

        if ($deleted->isEmpty()) {
            return false;
        }

        return $product->variants()->pluck('id')
            ->diff($deleted)
            ->isEmpty();
    }

    /** Un SKU ou un GTIN de déclinaison est unique dans tout le catalogue. */
    private function validateVariantIdentifiers($validator): void
    {
        $rows = (array) $this->input('variants', []);

        foreach (['sku', 'gtin'] as $field) {
            $seen = [];

            foreach ($rows as $index => $row) {
                if (! empty($row['_delete'])) {
                    continue;
                }

                $value = trim((string) ($row[$field] ?? ''));

                if ($value === '') {
                    continue;
                }

                if (isset($seen[$value])) {
                    $validator->errors()->add(
                        "variants.{$index}.{$field}",
                        'This '.strtoupper($field).' is repeated in this request.',
                    );

                    continue;
                }

                $seen[$value] = true;

                $rule = new UniqueCatalogIdentifier(
                    $field,
                    $this->editedProduct()?->id,
                    filled($row['id'] ?? null) ? (int) $row['id'] : null,
                );

                $rule->validate(
                    "variants.{$index}.{$field}",
                    $value,
                    fn (string $message) => $validator->errors()->add(
                        "variants.{$index}.{$field}",
                        str_replace(':attribute', strtoupper($field), $message),
                    ),
                );
            }
        }
    }
}
