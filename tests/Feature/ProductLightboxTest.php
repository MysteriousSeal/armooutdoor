<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The lightbox is built entirely by the script at runtime, so these tests
 * pin its two halves: the page ships the hooks the script reads and none
 * of the lightbox itself, and the script keeps the pieces the feature is
 * made of — the kind of markup-pointing-at-nothing fault nothing else
 * catches.
 */
class ProductLightboxTest extends TestCase
{
    use RefreshDatabase;

    private function productPage(): string
    {
        $product = Product::factory()->create(['is_active' => true]);
        ProductImage::create(['product_id' => $product->id, 'image' => 'products/second.webp', 'sort_order' => 0]);

        return $this->get('/products/'.$product->slug)->assertOk()->getContent();
    }

    public function test_the_page_ships_the_hooks_and_none_of_the_lightbox(): void
    {
        $html = $this->productPage();

        // What the script reads: the stage, the main image, the gallery
        // thumbs carrying their full-size sources, and the script itself.
        $this->assertStringContainsString('id="product-detail-main-image"', $html);
        $this->assertStringContainsString('product-detail-stage', $html);
        $this->assertStringContainsString('data-full-src=', $html);
        $this->assertStringContainsString('js/product-gallery.js', $html);

        // The no-JS guarantee: no dead controls in the server's markup.
        $this->assertStringNotContainsString('lightbox', $html);
    }

    public function test_the_script_keeps_the_pieces_the_lightbox_is_made_of(): void
    {
        $js = file_get_contents(public_path('js/product-gallery.js'));

        // A dialog, announced as one, with a way out for every input:
        // close button, Escape, arrow keys, swipe, and focus restored.
        $this->assertStringContainsString("setAttribute('aria-modal', 'true')", $js);
        $this->assertStringContainsString('lightbox-close', $js);
        $this->assertStringContainsString("'Escape'", $js);
        $this->assertStringContainsString("'ArrowLeft'", $js);
        $this->assertStringContainsString("'ArrowRight'", $js);
        $this->assertStringContainsString('touchstart', $js);
        $this->assertStringContainsString('returnFocusTo', $js);

        // Navigation and the strip: prev, next, counter, thumbnails.
        $this->assertStringContainsString('lightbox-prev', $js);
        $this->assertStringContainsString('lightbox-next', $js);
        $this->assertStringContainsString('lightbox-counter', $js);
        $this->assertStringContainsString('lightbox-thumb', $js);

        // The stage becomes a real keyboard stop when the script runs.
        $this->assertStringContainsString("setAttribute('role', 'button')", $js);
    }

    public function test_the_stylesheet_knows_every_class_the_script_writes(): void
    {
        // Markup pointing at rules that do not exist fails nothing on its
        // own — the FAQ once shipped a correct grid rendered unstyled.
        $css = file_get_contents(public_path('css/app.css'));

        foreach (['\.lightbox ', '\.lightbox-stage', '\.lightbox-image', '\.lightbox-close', '\.lightbox-nav', '\.lightbox-counter', '\.lightbox-thumb', 'body\.lightbox-open'] as $selector) {
            $this->assertMatchesRegularExpression('/'.$selector.'/', $css);
        }
    }
}
