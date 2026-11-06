<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * The catalogue as Google Merchant Center reads it.
 *
 * One RSS item per active product, or per active variant of a product
 * sold in variants, in the g: vocabulary the free listings
 * ingest: the SKU as the stable id, the price the page actually charges,
 * availability said in Google's three words, and the GTIN or brand when the
 * product knows them — with identifier_exists saying so honestly when it
 * knows neither, which spares the item a disapproval.
 */
class MerchantFeedController extends Controller
{
    public function google(): Response
    {
        $products = Product::query()
            ->where('is_active', true)
            // A category switched out of the feed keeps its products home,
            // and a parent switched out takes its subcategories along: the
            // switch exists for Google's per-aisle policy refusals.
            ->whereHas('category', fn ($category) => $category
                ->where('google_feed', true)
                ->where(fn ($query) => $query
                    ->whereNull('parent_id')
                    ->orWhereHas('parent', fn ($parent) => $parent->where('google_feed', true))))
            ->with(['discount', 'variants', 'images', 'category.parent'])
            ->orderBy('id')
            ->get();

        // A product sold in variants is listed as its variants, one line
        // each, grouped under the product; the product itself is left out,
        // as Google would otherwise count it twice.
        $items = $products->map(fn (Product $product): string => $product->hasVariants()
            ? $product->variants
                ->filter(fn (ProductVariant $variant): bool => (bool) $variant->is_active)
                ->sortBy('sort_order')
                ->map(fn (ProductVariant $variant): string => $this->variantItem($product, $variant))
                ->implode('')
            : $this->item($product))->implode('');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">'
            .'<channel>'
            .'<title>'.$this->text(config('app.name')).'</title>'
            .'<link>'.$this->text(localized_route('home')).'</link>'
            .'<description>'.$this->text(__('store.meta_home')).'</description>'
            .$items
            .'</channel></rss>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /** What Merchant Center accepts beside the main image. */
    private const MAX_ADDITIONAL_IMAGES = 10;

    private function item(Product $product): string
    {
        $fields = [
            'g:id' => $product->sku,
            'title' => Str::limit($product->localizedName(), 150, ''),
            'description' => Str::limit($product->localizedDescriptionText(), 5000, ''),
            'link' => localized_route('products.show', ['product' => $product->slug]),
            'g:image_link' => $product->imageUrl(),
            'g:price' => $this->price($product->effectivePriceCents()),
            'g:availability' => $this->availability($product),
            'g:condition' => 'new',
            'g:product_type' => $this->productType($product),
            'g:brand' => filled($product->brand) ? $product->brand : null,
            'g:gtin' => filled($product->gtin) ? $product->gtin : null,
            // Grams as the catalogue stores them; a product that has not
            // been weighed says nothing rather than 0 g.
            'g:shipping_weight' => $product->weight_grams > 0 ? $product->weight_grams.' g' : null,
        ];

        return $this->render($fields, $this->additionalImages($product));
    }

    /**
     * One variant as its own line: its SKU, GTIN, price, stock and photos,
     * the product's text and aisle, and g:item_group_id tying it to its
     * siblings. The link opens the product page on this very variant.
     */
    private function variantItem(Product $product, ProductVariant $variant): string
    {
        $variant->setRelation('product', $product);
        $label = $variant->label();
        $main = $variant->imageUrl();

        $fields = [
            'g:id' => $variant->sku,
            'g:item_group_id' => 'product-'.$product->id,
            'title' => Str::limit($label !== '' ? $product->localizedName().' - '.$label : $product->localizedName(), 150, ''),
            'description' => Str::limit($product->localizedDescriptionText(), 5000, ''),
            'link' => localized_route('products.show', ['product' => $product->slug]).'?variant='.$variant->id,
            'g:image_link' => $main,
            'g:price' => $this->price($variant->effectivePriceCents()),
            'g:availability' => $this->variantAvailability($variant),
            'g:condition' => 'new',
            'g:product_type' => $this->productType($product),
            'g:brand' => filled($product->brand) ? $product->brand : null,
            'g:gtin' => filled($variant->gtin) ? $variant->gtin : null,
            'g:size' => $this->attribute($variant, self::SIZE_LABELS),
            'g:color' => $this->attribute($variant, self::COLOR_LABELS),
            'g:shipping_weight' => $product->weight_grams > 0 ? $product->weight_grams.' g' : null,
        ];

        // The variant's own photos first, then the product's, as the page
        // shows them; never the main image twice.
        $additional = collect($variant->gallerySlides())->pluck('full')
            ->concat($product->image !== '' ? [$product->imageUrl()] : [])
            ->concat($product->images->map(fn ($image): string => $image->imageUrl()))
            ->filter(fn (string $url): bool => $url !== '' && $url !== $main)
            ->unique()
            ->take(self::MAX_ADDITIONAL_IMAGES)
            ->values()
            ->all();

        return $this->render($fields, $additional);
    }

    /**
     * @param  array<string, ?string>  $fields
     * @param  list<string>  $additionalImages
     */
    private function render(array $fields, array $additionalImages): string
    {
        // Google refuses an item that stays silent about missing codes; an
        // item that says it has none is listed on title and image instead.
        if (blank($fields['g:gtin']) && blank($fields['g:brand'])) {
            $fields['g:identifier_exists'] = 'no';
        }

        $xml = '<item>';

        foreach ($fields as $tag => $value) {
            if ($value !== null && $value !== '') {
                $xml .= '<'.$tag.'>'.$this->text($value).'</'.$tag.'>';
            }
        }

        // g:image_link holds one image and one only; the rest of the gallery
        // goes in a repeated g:additional_image_link, which Merchant Center
        // takes ten of. An item with an empty gallery adds nothing.
        foreach ($additionalImages as $url) {
            $xml .= '<g:additional_image_link>'.$this->text($url).'</g:additional_image_link>';
        }

        return $xml.'</item>';
    }

    /** Attribute names, lowercased, read as Google's g:size and g:color. */
    private const SIZE_LABELS = ['size', 'taille', 'shoe size', 'pointure'];

    private const COLOR_LABELS = ['color', 'colour', 'couleur', 'coloris'];

    /** @param  list<string>  $labels */
    private function attribute(ProductVariant $variant, array $labels): ?string
    {
        $value = collect($variant->attribute_values ?? [])
            ->first(fn (array $attribute): bool => in_array(mb_strtolower(trim($attribute['label'] ?? '')), $labels, true))['value'] ?? null;

        return filled($value) ? Str::limit(trim($value), 100, '') : null;
    }

    /** The variant's own stock, read as availability() reads a product's. */
    private function variantAvailability(ProductVariant $variant): string
    {
        return match (true) {
            $variant->inStock() => 'in_stock',
            // A size already on order from the supplier is not reordered.
            $variant->isRestocking() => 'out_of_stock',
            $variant->isBackorderable() => 'backorder',
            default => 'out_of_stock',
        };
    }

    /**
     * The shop's own aisle, parent first, the way Google reads a path. It is
     * what lets an Ads campaign follow a whole category instead of a list
     * of products kept by hand.
     */
    private function productType(Product $product): ?string
    {
        $category = $product->category;

        if (! $category) {
            return null;
        }

        return collect([$category->parent?->localizedName(), $category->localizedName()])
            ->filter()
            ->implode(' > ');
    }

    /**
     * The gallery, minus whatever is already the main image: repeating it
     * costs a slot and shows the shopper the same photograph twice.
     *
     * @return list<string>
     */
    private function additionalImages(Product $product): array
    {
        $main = $product->imageUrl();

        return $product->images
            ->map(fn ($image): string => $image->imageUrl())
            ->filter(fn (string $url): bool => $url !== '' && $url !== $main)
            ->unique()
            ->take(self::MAX_ADDITIONAL_IMAGES)
            ->values()
            ->all();
    }

    /** The price the landing page charges, the variant's own on a variant line. */
    private function price(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '').' EUR';
    }

    private function availability(Product $product): string
    {
        return match ($product->availabilityState()) {
            'in_stock', 'low_stock' => 'in_stock',
            // Orderable from the supplier's shelf: Google's definition of a
            // backorder, not of an item gone.
            'at_supplier' => 'backorder',
            default => 'out_of_stock',
        };
    }

    private function text(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
