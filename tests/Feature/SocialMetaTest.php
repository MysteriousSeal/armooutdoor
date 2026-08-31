<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The card a shared link draws.
 *
 * The shop published no og: tags at all, so every link posted to a forum, a
 * group or a chat arrived as a bare URL with nothing to show for itself.
 */
class SocialMetaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_page_describes_itself_to_whoever_shares_it(): void
    {
        $page = $this->get('/')->assertOk();

        $page->assertSee('<meta property="og:site_name" content="Armo Outdoor">', false);
        $page->assertSee('<meta property="og:locale" content="fr_FR">', false);
        $page->assertSee('<meta property="og:type" content="website">', false);
        $page->assertSee('<meta property="og:url" content="'.url('/').'">', false);
        $page->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
    }

    public function test_the_brand_is_not_printed_twice_in_one_card(): void
    {
        // og:site_name already carries it, so the title suffix comes off.
        $category = Category::factory()->create(['name' => 'Cibles']);

        $this->get('/categories/'.$category->slug)
            ->assertOk()
            ->assertSee('<meta property="og:title" content="Cibles">', false);
    }

    public function test_a_product_shares_its_own_photograph(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertSee('<meta property="og:type" content="product">', false)
            ->assertSee('<meta property="og:image" content="'.$product->imageUrl().'">', false);
    }

    public function test_an_article_is_shared_as_an_article(): void
    {
        $post = BlogPost::factory()->create();

        $this->get('/blog/'.$post->slug)
            ->assertOk()
            ->assertSee('<meta property="og:type" content="article">', false);
    }

    public function test_a_page_without_a_picture_of_its_own_falls_back_to_the_hero(): void
    {
        $category = Category::factory()->create(['image' => null]);

        $this->get('/categories/'.$category->slug)
            ->assertOk()
            ->assertSee('<meta property="og:image" content="'.asset('images/hero.webp').'">', false);
    }
}
