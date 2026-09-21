<?php

namespace App\Support\Octopia;

use App\Models\CdiscountListing;
use App\Models\OctopiaTemplate;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Str;

/**
 * The shop's products as the lines Octopia would receive.
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
     * Every line the category's listings would produce, ready or not.
     *
     * @param  iterable<CdiscountListing>  $listings
     * @return list<array{key: string, product: Product, variant: ?ProductVariant, reference: string, gtin: string, title: string, missing: list<string>, payload: array<string, mixed>, offer: array{payload: array<string, mixed>, missing: list<string>, price_cents: int, stock: int}}>
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
     * @return array{key: string, product: Product, variant: ?ProductVariant, reference: string, gtin: string, title: string, missing: list<string>, payload: array<string, mixed>, offer: array{payload: array<string, mixed>, missing: list<string>, price_cents: int, stock: int}}
     */
    private function line(CdiscountListing $listing, Product $product, ?ProductVariant $variant): array
    {
        $gtin = (string) ($variant?->gtin ?: $product->gtin);
        $reference = (string) ($variant?->sku ?: $product->sku);
        $values = $listing->answersFor($variant);
        $missing = [];

        // The EAN is what Octopia identifies an offer by, and no category
        // lists it among its own attributes.
        if ($gtin === '') {
            $missing[] = 'EAN';
        }

        // What Octopia refuses a product sheet without, whatever the category.
        if ($this->description($product, $listing) === '') {
            $missing[] = 'Description';
        }

        if ($this->images($product, $variant) === []) {
            $missing[] = 'Photo';
        }

        foreach ($this->template->attributeFields() as $field) {
            if ($field['required'] && $this->cell($field, $product, $variant, $gtin, $reference, $values) === '') {
                $missing[] = $field['label'];
            }
        }

        return [
            'key' => $product->id.':'.($variant?->id ?? 0),
            'product' => $product,
            'variant' => $variant,
            'reference' => $reference,
            'gtin' => $gtin,
            'title' => $this->title($product, $variant, $listing),
            'missing' => $missing,
            'payload' => $this->payload($listing, $product, $variant, $gtin, $reference, $values),
            'offer' => $this->offer($listing, $product, $variant, $gtin, $reference),
        ];
    }

    /**
     * The offer for the line: what it is sold at, in what quantity, delivered
     * how, in the shape Octopia's offer packages take.
     *
     * The price is the shop's, the variant's own when it has one and the
     * promotional one when there is a promotion, raised by the markup the
     * seller set for Cdiscount's commission. Where a promotion is running the
     * undiscounted price goes with it, struck through. The stock is the
     * shop's stock: an offer is only ever as large as what can be shipped.
     *
     * @return array{payload: array<string, mixed>, missing: list<string>, price_cents: int, stock: int}
     */
    private function offer(CdiscountListing $listing, Product $product, ?ProductVariant $variant, string $gtin, string $reference): array
    {
        $settings = $listing->offerSettings();
        $missing = $listing->offerMissing();

        $effective = $variant?->effectivePriceCents() ?? $product->effectivePriceCents();
        $original = $variant?->price_cents ?? $product->price_cents;
        $stock = max(0, (int) ($variant?->quantity ?? $product->quantity));

        if ($effective <= 0) {
            $missing[] = 'Price';
        }

        $marked = fn (int $cents): float => round($cents * (1 + $settings['markup'] / 100)) / 100;

        // Every tax is sent, and at zero: the shop charges no VAT, and its
        // articles carry no eco-tax and no D3E (waste electrical equipment).
        // Cdiscount France refuses an offer without the last two, though
        // Octopia's documentation lists them as optional.
        $price = ['price' => $marked($effective), 'taxes' => [
            ['code' => 'VAT', 'value' => 0.0],
            ['code' => 'Ecotax', 'value' => 0.0],
            ['code' => 'Deatax', 'value' => 0.0],
        ]];

        if ($original > $effective) {
            $price['originPrice'] = $marked($original);
        }

        $modes = [];

        // The tracked one first, as Octopia lists them.
        foreach (array_keys(CdiscountListing::DELIVERY_MODES) as $code) {
            $mode = $settings['delivery'][$code] ?? null;

            if (isset($mode['cost'])) {
                $modes[] = array_filter([
                    'code' => $code,
                    'cost' => (float) $mode['cost'],
                    'additionalCost' => isset($mode['additional']) ? (float) $mode['additional'] : null,
                ], fn (mixed $value): bool => $value !== null);
            }
        }

        return [
            'payload' => [
                'sellerExternalReference' => Str::limit($reference, 100, ''),
                'product' => array_filter(['gtin' => $gtin, 'reference' => $this->reference($reference)]),
                'condition' => $settings['condition'],
                'price' => $price,
                'deliveryModes' => $modes,
                'preparationTime' => $settings['preparation_days'] ?? 0,
                'quantity' => $stock,
            ],
            'missing' => $missing,
            'price_cents' => (int) round($effective * (1 + $settings['markup'] / 100)),
            'stock' => $stock,
        ];
    }

    /**
     * The description Octopia is given: the one written for Cdiscount when
     * there is one, else the product's meta description, else its long one.
     *
     * Octopia files a product by reading its description, and a long one,
     * full of the uses and the materials of the article, sent it to another
     * category than the one chosen. The meta description says what the article
     * is in a couple of sentences, which is what the category is meant to be
     * read from.
     */
    private function description(Product $product, CdiscountListing $listing): string
    {
        if (filled($listing->description)) {
            return trim($listing->description);
        }

        $meta = trim((string) $product->meta_description);

        return $meta !== '' ? $meta : trim($product->localizedDescriptionText());
    }

    /** ASCII 33 to 127, the pipe apart: the rest is refused. */
    private function reference(string $reference): string
    {
        return Str::limit((string) preg_replace('/[^\x21-\x7B\x7D-\x7F]/', '', $reference), 50, '');
    }

    /**
     * The product as Octopia's products-integration endpoint takes it.
     *
     * @param  array<string, string>  $values  the category's own attributes, answered
     * @return array<string, mixed>
     */
    private function payload(CdiscountListing $listing, Product $product, ?ProductVariant $variant, string $gtin, string $reference, array $values): array
    {
        $payload = [
            // An integer, though a GTIN is written as text: Octopia says so.
            'gtin' => (int) preg_replace('/\D/', '', $gtin),
            'sellerProductReference' => $this->reference($reference),
            'title' => $this->title($product, $variant, $listing),
            'description' => Str::limit($this->description($product, $listing), self::DESCRIPTION_LIMIT, ''),
            'brand' => Str::limit((string) $product->brandName(), 50, ''),
            'categoryCode' => $this->template->code,
            'sellerPictureUrls' => collect($this->images($product, $variant))
                ->map(fn (string $url, int $i): array => ['index' => $i + 1, 'url' => $url])
                ->values()
                ->all(),
            'attributes' => $this->attributes($values),
        ];

        // The rich description is the shop's long one, in HTML: with a
        // description of its own written for the category, it is not sent, or
        // it would carry back what that one leaves out.
        $marketing = filled($listing->description)
            ? ''
            : Str::limit($product->localizedDescription(), self::MARKETING_LIMIT, '');

        if (trim(strip_tags($marketing)) !== '') {
            $payload['richMarketingDescription'] = $marketing;
        }

        // What ties a product's variants together on Cdiscount: the product's
        // own reference, the same on each of its lines. A variant category
        // wants it on every product, one without variants included: Octopia
        // refuses the sheet without it.
        if ($variant !== null || $this->template->is_variant) {
            $payload['variantGroupReference'] = Str::limit((string) ($product->sku ?: $product->slug), 50, '');
        }

        return array_filter($payload, fn (mixed $value): bool => $value !== '');
    }

    /**
     * The answers, as the property/values pairs the endpoint takes. A
     * property that takes several values is answered with them split on
     * semicolons.
     *
     * @param  array<string, string>  $values
     * @return list<array{propertyReference: string, values: list<string>}>
     */
    private function attributes(array $values): array
    {
        $attributes = [];

        foreach ($this->template->attributeFields() as $field) {
            $answer = trim((string) ($values[$field['code']] ?? ''));

            if ($answer === '') {
                continue;
            }

            $attributes[] = [
                'propertyReference' => (string) $field['code'],
                'values' => str_starts_with((string) ($field['kind'] ?? ''), 'multi')
                    ? array_values(array_filter(array_map('trim', explode(';', $answer)), fn (string $v): bool => $v !== ''))
                    : [$answer],
            ];
        }

        return $attributes;
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

    /**
     * The title Octopia is given: the one written for Cdiscount when there is
     * one, else the product's name, with the variant's own wording after it
     * when it has one. A long title is cut before the variant's wording, not
     * through it: each variant is a sheet of its own and the wording tells
     * them apart.
     */
    private function title(Product $product, ?ProductVariant $variant, ?CdiscountListing $listing = null): string
    {
        $label = $variant?->label() ?? '';
        $suffix = $label !== '' ? ' '.$label : '';
        $base = filled($listing?->title) ? trim($listing->title) : $product->localizedName();

        return Str::limit($base, max(1, self::TITLE_LIMIT - mb_strlen($suffix)), '').$suffix;
    }

    /**
     * The photographs, the variant's own first: on Cdiscount the line is the
     * variant, and its own colour should be the first thing shown.
     *
     * Octopia takes https addresses only, which is the site's own business:
     * the Cdiscount page says so rather than dropping the pictures here.
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
