<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "picture is not contractual" line under a product's gallery.
 *
 * It only belongs on products whose delivered item can differ from the
 * photo, so it follows the product's flag: shown when it is set, absent
 * everywhere else.
 */
class ProductImageMayVaryNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_when_the_product_is_flagged(): void
    {
        $product = Product::factory()->create(['image_may_vary' => true]);

        $html = $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertSee(__('store.image_may_vary_notice'), false)
            ->getContent();

        // A note for assistive tech, whose icon is decoration only.
        $this->assertMatchesRegularExpression('/<p class="image-may-vary-notice" role="note">\s*<svg[^>]*aria-hidden="true"/', $html);
    }

    public function test_it_is_absent_from_a_product_without_the_flag(): void
    {
        $product = Product::factory()->create(['image_may_vary' => false]);

        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertDontSee('image-may-vary-notice', false)
            ->assertDontSee(__('store.image_may_vary_notice'), false);
    }

    public function test_it_sits_under_the_gallery_and_not_in_the_buy_column(): void
    {
        $product = Product::factory()->create(['image_may_vary' => true]);

        $html = $this->get('/products/'.$product->slug)->assertOk()->getContent();

        // It speaks about the picture, so it stays with the picture.
        $notice = strpos($html, 'image-may-vary-notice');
        $this->assertGreaterThan(strpos($html, 'product-detail-gallery'), $notice);
        $this->assertLessThan(strpos($html, 'product-detail-buy'), $notice);
    }

    public function test_its_icon_is_the_warning_triangle_from_the_registry(): void
    {
        // A name missing from the registry falls back to the default square.
        $registry = (string) file_get_contents(resource_path('views/partials/icon.blade.php'));
        $this->assertStringContainsString("'triangle-exclamation' =>", $registry);

        preg_match('/<path d="([^"]+)"/', view('partials.icon', ['name' => 'triangle-exclamation'])->render(), $icon);

        $product = Product::factory()->create(['image_may_vary' => true]);
        $html = $this->get('/products/'.$product->slug)->assertOk()->getContent();

        // The notice draws the same path the shared partial renders.
        $this->assertMatchesRegularExpression('/<p class="image-may-vary-notice"[^>]*>.*?<\/p>/s', $html);
        preg_match('/<p class="image-may-vary-notice"[^>]*>.*?<\/p>/s', $html, $notice);
        $this->assertStringContainsString('d="'.$icon[1].'"', $notice[0]);
    }

    public function test_the_notice_has_a_style_in_both_themes(): void
    {
        $css = file_get_contents(public_path('css/app.css'));

        $this->assertMatchesRegularExpression('/\.image-may-vary-notice\s*\{/', $css);
        $this->assertMatchesRegularExpression("/\\[data-theme='dark'\\] \\.image-may-vary-notice\\s*\\{/", $css);
    }
}
