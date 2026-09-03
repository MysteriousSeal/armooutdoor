<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * The catalogue as Google Merchant Center reads it.
 *
 * One RSS item per active product, in the g: vocabulary the free listings
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
            ->with(['discount', 'variants'])
            ->orderBy('id')
            ->get();

        $items = $products->map(fn (Product $product): string => $this->item($product))->implode('');

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

    private function item(Product $product): string
    {
        $fields = [
            'g:id' => $product->sku,
            'title' => Str::limit($product->localizedName(), 150, ''),
            'description' => Str::limit($product->localizedDescriptionText(), 5000, ''),
            'link' => localized_route('products.show', ['product' => $product->slug]),
            'g:image_link' => $product->imageUrl(),
            'g:price' => $this->price($product),
            'g:availability' => $this->availability($product),
            'g:condition' => 'new',
            'g:brand' => filled($product->brand) ? $product->brand : null,
            'g:gtin' => filled($product->gtin) ? $product->gtin : null,
        ];

        // Google refuses an item that stays silent about missing codes; an
        // item that says it has none is listed on title and image instead.
        if (blank($product->gtin) && blank($product->brand)) {
            $fields['g:identifier_exists'] = 'no';
        }

        $xml = '<item>';

        foreach ($fields as $tag => $value) {
            if ($value !== null && $value !== '') {
                $xml .= '<'.$tag.'>'.$this->text($value).'</'.$tag.'>';
            }
        }

        return $xml.'</item>';
    }

    /**
     * The price the landing page charges. A product sold in variants shows
     * its cheapest buyable declination — the « from » price the page leads
     * with — rather than a base price nobody is offered.
     */
    private function price(Product $product): string
    {
        $cents = $product->hasVariants()
            ? $product->variants
                ->filter(fn (ProductVariant $variant): bool => (bool) $variant->is_active)
                ->map(fn (ProductVariant $variant): int => $variant->effectivePriceCents())
                ->min() ?? $product->effectivePriceCents()
            : $product->effectivePriceCents();

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
