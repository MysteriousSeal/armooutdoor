<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Every JSON-LD block on the site is valid JSON, and says what it is.
 *
 * A stray Blade directive is enough to break one silently: `@context` written
 * bare compiles as a directive of its own and the key vanishes from the
 * output, leaving markup that still looks right in the page source and that no
 * search engine can read. The blocks are parsed here rather than trusted.
 */
class StructuredDataParsesTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<array<string, mixed>> */
    private function blocksOf(TestResponse $response): array
    {
        preg_match_all(
            '#<script type="application/ld\+json">(.*?)</script>#s',
            $response->assertOk()->getContent(),
            $matches,
        );

        $this->assertNotEmpty($matches[1], 'The page carries no structured data at all.');

        return array_map(function (string $raw): array {
            $decoded = json_decode(trim($raw), true);

            $this->assertIsArray($decoded, 'A JSON-LD block did not parse: '.trim($raw));
            $this->assertArrayHasKey('@context', $decoded, 'A JSON-LD block has no @context.');
            $this->assertArrayHasKey('@type', $decoded, 'A JSON-LD block has no @type.');

            return $decoded;
        }, $matches[1]);
    }

    /** @return list<string> */
    private function typesOn(string $url): array
    {
        return array_column($this->blocksOf($this->get($url)), '@type');
    }

    public function test_the_home_page_declares_the_site_and_the_business(): void
    {
        $this->assertEqualsCanonicalizing(
            ['WebSite', 'OnlineStore'],
            $this->typesOn('/'),
        );
    }

    public function test_a_category_declares_its_trail_and_its_contents(): void
    {
        $category = Category::factory()->create();
        Product::factory()->count(3)->create(['category_id' => $category->id, 'is_active' => true]);

        $this->assertEqualsCanonicalizing(
            ['BreadcrumbList', 'CollectionPage'],
            $this->typesOn('/categories/'.$category->slug),
        );
    }

    public function test_a_product_declares_itself_and_its_trail(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $this->assertEqualsCanonicalizing(
            ['Product', 'BreadcrumbList'],
            $this->typesOn('/products/'.$product->slug),
        );
    }

    public function test_the_blog_index_declares_its_shelf_and_its_trail(): void
    {
        BlogPost::factory()->create();

        $this->assertEqualsCanonicalizing(
            ['Blog', 'BreadcrumbList'],
            $this->typesOn('/blog'),
        );
    }

    public function test_an_article_declares_itself_and_its_trail(): void
    {
        $post = BlogPost::factory()->create();

        $this->assertEqualsCanonicalizing(
            ['BlogPosting', 'BreadcrumbList'],
            $this->typesOn('/blog/'.$post->slug),
        );
    }
}
