<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One h1 a page, and it names the page.
 *
 * The wordmark in the header was an h1, so all 268 product pages shared a
 * single heading that said "ArmoOutdoor" while the product's own name sat in
 * an h2 below it — the clearest signal a page has of what it is about, spent
 * on the brand instead of the product.
 */
class PageHeadingsTest extends TestCase
{
    use RefreshDatabase;

    private function headingsOn(string $url): array
    {
        preg_match_all(
            '/<h1[^>]*>(.*?)<\/h1>/s',
            $this->get($url)->assertOk()->getContent(),
            $matches,
        );

        return $matches[1];
    }

    public function test_a_product_page_is_headed_by_the_product(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $headings = $this->headingsOn('/products/'.$product->slug);

        $this->assertCount(1, $headings);
        $this->assertStringContainsString($product->localizedName(), $headings[0]);
    }

    public function test_a_category_page_is_headed_by_the_category(): void
    {
        $category = Category::factory()->create();

        $headings = $this->headingsOn('/categories/'.$category->slug);

        $this->assertCount(1, $headings);
        $this->assertStringContainsString($category->localizedName(), $headings[0]);
    }

    public function test_the_home_page_is_headed_once_despite_four_panels(): void
    {
        // Every carousel panel carries a title; only the leading one is the
        // heading of the page.
        $this->assertCount(1, $this->headingsOn('/'));
    }

    public function test_the_wordmark_is_no_longer_a_heading(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<p class="site-logo">', false)
            ->assertDontSee('<h1 class="site-logo">', false);
    }
}
