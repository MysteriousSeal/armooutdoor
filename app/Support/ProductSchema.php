<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use Illuminate\Support\Str;

/**
 * La fiche produit, telle que la lisent les moteurs de recherche.
 *
 * Toutes les autres pages de la boutique déclaraient leur contenu en
 * JSON-LD ; la fiche produit, seule, ne disait rien. C'est pourtant la
 * seule qui a un prix, une disponibilité et une note à annoncer, les trois
 * choses qui s'affichent dans un résultat de recherche.
 */
class ProductSchema
{
    /** Assez pour décrire, pas assez pour recopier la page entière. */
    private const MAX_DESCRIPTION = 5000;

    /** Les avis cités dans la fiche. Le reste vit sur la page. */
    private const MAX_REVIEWS = 5;

    /** The characteristic a product's maker is recorded under, where one is. */
    private const BRAND_LABEL = 'Marque';

    /**
     * The statutory French withdrawal period, in days.
     *
     * Also written into the droit de rétractation page, which is the text that
     * governs; this is the same figure said in a form a search engine reads.
     */
    private const RETURN_DAYS = 14;

    /** @return array<string, mixed> */
    public static function for(Product $product): array
    {
        $schema = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->localizedName(),
            'description' => Str::limit($product->localizedDescriptionText(), self::MAX_DESCRIPTION),
            'sku' => $product->sku,
            'gtin' => $product->gtin,
            'category' => $product->category?->localizedName(),
            'brand' => self::brand($product),
            'image' => self::images($product),
            'offers' => self::offers($product),
        ], fn ($value): bool => $value !== null && $value !== '' && $value !== []);

        // Sans avis, `ratingCount` vaudrait zéro : Google refuse le bloc
        // plutôt que de l'ignorer, et la fiche entière part avec.
        if ($product->reviewsCount() > 0 && $product->averageRating() !== null) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) $product->averageRating(),
                'reviewCount' => $product->reviewsCount(),
                'bestRating' => '5',
                'worstRating' => '1',
            ];

            $reviews = self::reviews($product);

            if ($reviews !== []) {
                $schema['review'] = $reviews;
            }
        }

        return $schema;
    }

    /**
     * The maker, where the catalogue records one.
     *
     * Thirty-eight products carry a « Marque » characteristic — Umarex,
     * Mechanix, Specna Arms — and the rest carry none. Those publish no brand
     * at all rather than the shop's own name: Armo Outdoor did not make the
     * Mechanix gloves it sells, and a brand is one of the fields Google reads
     * back against merchant feeds. A gap it forgives; a wrong answer it does
     * not.
     *
     * @return array<string, string>|null
     */
    private static function brand(Product $product): ?array
    {
        $sources = array_merge(
            $product->characteristics ?? [],
            $product->filter_attributes ?? [],
        );

        foreach ($sources as $entry) {
            if (($entry['label'] ?? '') !== self::BRAND_LABEL) {
                continue;
            }

            $name = trim((string) ($entry['value'] ?? ''));

            if ($name !== '') {
                return ['@type' => 'Brand', 'name' => $name];
            }
        }

        return null;
    }

    /**
     * The right to change one's mind, said where a search result can show it.
     *
     * Fourteen days from delivery, return by post, return postage at the
     * customer's charge — the terms of the droit de rétractation page, which
     * is where they are actually promised. Google prints the window beside
     * free listings, and a shop that says nothing is read as a shop with no
     * returns.
     *
     * @return array<string, mixed>
     */
    private static function returnPolicy(): array
    {
        return [
            '@type' => 'MerchantReturnPolicy',
            'applicableCountry' => 'FR',
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays' => self::RETURN_DAYS,
            'returnMethod' => 'https://schema.org/ReturnByMail',
            // The customer bears the postage, so the amount is theirs and not
            // ours to state: this says who pays without inventing a figure.
            'returnFees' => 'https://schema.org/ReturnFeesCustomerResponsibility',
        ];
    }

    /** @return array<int, string> */
    private static function images(Product $product): array
    {
        return collect([$product->imageUrl()])
            ->concat($product->images->map(fn ($image): string => $image->imageUrl()))
            ->filter(fn (string $url): bool => $url !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Le prix et la disponibilité.
     *
     * Un produit à déclinaisons n'a pas un prix mais une fourchette : c'est
     * `AggregateOffer` qui la dit, et annoncer le prix de la première taille
     * ferait afficher un prix qu'on ne pratique pas.
     *
     * @return array<string, mixed>
     */
    private static function offers(Product $product): array
    {
        $common = [
            'priceCurrency' => 'EUR',
            'availability' => self::availability($product),
            'itemCondition' => 'https://schema.org/NewCondition',
            'url' => localized_route('products.show', ['product' => $product->slug]),
            'hasMerchantReturnPolicy' => self::returnPolicy(),
        ];

        $variantPrices = $product->hasVariants()
            ? $product->variants
                ->filter(fn (ProductVariant $variant): bool => (bool) $variant->is_active)
                ->map(fn (ProductVariant $variant): int => $variant->effectivePriceCents())
                ->values()
            : collect();

        if ($variantPrices->count() > 1 && $variantPrices->min() !== $variantPrices->max()) {
            return $common + [
                '@type' => 'AggregateOffer',
                'lowPrice' => self::amount($variantPrices->min()),
                'highPrice' => self::amount($variantPrices->max()),
                'offerCount' => $variantPrices->count(),
            ];
        }

        return array_filter($common + [
            '@type' => 'Offer',
            'price' => self::amount($variantPrices->min() ?? $product->effectivePriceCents()),
            // Une remise a une fin : passée cette date, le prix annoncé
            // n'est plus celui de la page. Une remise sans fin n'écrit rien
            // plutôt qu'une clé vide.
            'priceValidUntil' => $product->hasDiscount()
                ? $product->discount->ends_at?->toDateString()
                : null,
        ], fn ($value): bool => $value !== null);
    }

    /**
     * `restocking` compte comme épuisé, et non comme commande différée :
     * la boutique refuse l'achat tant que le réassort n'est pas arrivé.
     */
    private static function availability(Product $product): string
    {
        return 'https://schema.org/'.match ($product->availabilityState()) {
            'in_stock', 'low_stock' => 'InStock',
            'at_supplier' => 'BackOrder',
            default => 'OutOfStock',
        };
    }

    /** @return array<int, array<string, mixed>> */
    private static function reviews(Product $product): array
    {
        return $product->reviews
            ->take(self::MAX_REVIEWS)
            ->map(fn (ProductReview $review): array => array_filter([
                '@type' => 'Review',
                'reviewRating' => [
                    '@type' => 'Rating',
                    'ratingValue' => (string) $review->rating,
                    'bestRating' => '5',
                    'worstRating' => '1',
                ],
                'author' => ['@type' => 'Person', 'name' => $review->reviewerName()],
                'datePublished' => $review->created_at?->toDateString(),
                'reviewBody' => $review->comment,
            ], fn ($value): bool => $value !== null && $value !== ''))
            ->values()
            ->all();
    }

    private static function amount(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
