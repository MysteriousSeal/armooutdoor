<?php

namespace App\Support\Octopia;

use App\Models\CdiscountListing;
use App\Models\OctopiaTemplate;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Str;

/**
 * The shop's products as lines of an Octopia product template.
 *
 * One line per offer: a product without variants is one line, a product with
 * variants is one line per active variant, since each is bought on its own on
 * Cdiscount and carries its own EAN. A line is only worth sending once it has
 * an EAN and the category's required attributes; what is missing is named
 * rather than left to be discovered by a refused import.
 */
class Exporter
{
    /** Octopia takes six photographs per product sheet. */
    private const MAX_IMAGES = 6;

    private const TITLE_LIMIT = 132;

    private const DESCRIPTION_LIMIT = 2000;

    private const MARKETING_LIMIT = 5000;

    public function __construct(private readonly OctopiaTemplate $template) {}

    /**
     * Every line the template's listings would produce, ready or not.
     *
     * @param  iterable<CdiscountListing>  $listings
     * @return list<array{key: string, product: Product, variant: ?ProductVariant, reference: string, gtin: string, title: string, missing: list<string>, cells: array<string, string>}>
     */
    public function lines(iterable $listings): array
    {
        $lines = [];

        foreach ($listings as $listing) {
            $product = $listing->product;

            if ($product === null) {
                continue;
            }

            $variants = $product->variants->where('is_active', true);

            if ($variants->isEmpty()) {
                $lines[] = $this->line($listing, $product, null);

                continue;
            }

            foreach ($variants as $variant) {
                $lines[] = $this->line($listing, $product, $variant);
            }
        }

        return $lines;
    }

    /**
     * The cells of the lines whose keys were chosen, in the order given.
     *
     * @param  list<array<string, mixed>>  $lines
     * @param  list<string>  $keys
     * @return list<array<string, string>>
     */
    public function rows(array $lines, array $keys): array
    {
        $chosen = array_flip($keys);

        return array_values(array_map(
            fn (array $line): array => $line['cells'],
            array_filter($lines, fn (array $line): bool => isset($chosen[$line['key']])),
        ));
    }

    /**
     * @return array{key: string, product: Product, variant: ?ProductVariant, reference: string, gtin: string, title: string, missing: list<string>, cells: array<string, string>}
     */
    private function line(CdiscountListing $listing, Product $product, ?ProductVariant $variant): array
    {
        $gtin = (string) ($variant?->gtin ?: $product->gtin);
        $reference = (string) ($variant?->sku ?: $product->sku);
        $values = $listing->answersFor($variant);
        $cells = [];
        $missing = [];

        foreach ($this->template->fields as $field) {
            $value = $this->cell($field, $product, $variant, $gtin, $reference, $values);

            if ($value !== '') {
                $cells[$field['column']] = $value;
            } elseif ($field['required']) {
                $missing[] = $field['label'];
            }
        }

        return [
            'key' => $product->id.':'.($variant?->id ?? 0),
            'product' => $product,
            'variant' => $variant,
            'reference' => $reference,
            'gtin' => $gtin,
            'title' => $this->title($product, $variant),
            'missing' => $missing,
            'cells' => $cells,
        ];
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, string>  $values
     */
    private function cell(array $field, Product $product, ?ProductVariant $variant, string $gtin, string $reference, array $values): string
    {
        $code = (string) $field['code'];

        if (str_starts_with($code, 'sellerPictureUrls_')) {
            $rank = (int) Str::afterLast($code, '_');

            return $this->images($product, $variant)[$rank - 1] ?? '';
        }

        return match ($code) {
            'gtin' => $gtin,
            'sellerProductReference' => Str::limit($reference, 50, ''),
            'title' => $this->title($product, $variant),
            'description' => Str::limit($product->localizedDescriptionText(), self::DESCRIPTION_LIMIT, ''),
            'richMarketingDescription' => Str::limit($product->localizedDescription(), self::MARKETING_LIMIT, ''),
            'brand' => Str::limit((string) $product->brandName(), 30, ''),
            // What ties a product's variants together on Cdiscount: the
            // product's own reference, the same on each of its lines.
            'gtinReference' => Str::limit((string) ($product->sku ?: $product->slug), 50, ''),
            default => trim((string) ($values[$code] ?? '')),
        };
    }

    /** The product's name, with the variant's own wording when it has one. */
    private function title(Product $product, ?ProductVariant $variant): string
    {
        $label = $variant?->label() ?? '';
        $title = $product->localizedName().($label !== '' ? ' '.$label : '');

        return Str::limit($title, self::TITLE_LIMIT, '');
    }

    /**
     * The photographs, the variant's own first: on Cdiscount the line is the
     * variant, and its own colour should be the first thing shown.
     *
     * Octopia takes https addresses only, which is the site's own business:
     * the export page says so rather than dropping the pictures here.
     *
     * @return list<string>
     */
    private function images(Product $product, ?ProductVariant $variant): array
    {
        $urls = collect($variant?->photos() ?? [])
            ->map(fn (string $path): string => asset('images/'.$path))
            ->concat($product->image !== '' ? [$product->imageUrl()] : [])
            ->concat($product->images->map(fn ($image): string => $image->imageUrl()))
            ->filter(fn (string $url): bool => $url !== '')
            ->unique()
            ->take(self::MAX_IMAGES)
            ->values();

        return $urls->all();
    }
}
