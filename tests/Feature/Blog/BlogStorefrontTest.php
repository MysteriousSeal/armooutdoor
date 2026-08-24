<?php

namespace Tests\Feature\Blog;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Product;
use Database\Seeders\BlogCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** La façade publique du blog. */
class BlogStorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BlogCategorySeeder::class);
    }

    public function test_the_index_lists_a_visible_post(): void
    {
        $post = BlogPost::factory()->create();

        $this->get('/blog')
            ->assertOk()
            ->assertSee($post->localizedTitle(), false);
    }

    public function test_a_visible_post_has_its_own_page(): void
    {
        $post = BlogPost::factory()->create();

        $this->get('/blog/'.$post->slug)
            ->assertOk()
            ->assertSee($post->localizedTitle(), false);
    }

    /** Le défaut que ce test garde : une adresse devinée ouvre un brouillon. */
    public function test_a_draft_is_a_404_at_its_own_address(): void
    {
        $post = BlogPost::factory()->draft()->create();

        $this->get('/blog/'.$post->slug)->assertNotFound();
        $this->get('/blog')->assertDontSee($post->localizedTitle(), false);
    }

    public function test_a_scheduled_post_is_a_404_until_its_hour(): void
    {
        $post = BlogPost::factory()->create(['published_at' => now()->addHours(2)]);

        $this->get('/blog/'.$post->slug)->assertNotFound();

        Carbon::setTestNow(now()->addHours(3));
        $this->get('/blog/'.$post->slug)->assertOk();
        Carbon::setTestNow();
    }

    public function test_the_category_filter_narrows_the_list(): void
    {
        $conseils = BlogCategory::query()->where('slug', 'conseils')->firstOrFail();
        $actualites = BlogCategory::query()->where('slug', 'actualites')->firstOrFail();

        $guide = BlogPost::factory()->create(['blog_category_id' => $conseils->id]);
        $news = BlogPost::factory()->create(['blog_category_id' => $actualites->id]);

        $this->get('/blog/conseils')
            ->assertOk()
            ->assertSee($guide->localizedTitle(), false)
            ->assertDontSee($news->localizedTitle(), false);
    }

    /**
     * Une rubrique et un article partagent la forme `/blog/{slug}`.
     *
     * La route rubrique passe en premier mais n'est contrainte qu'aux slugs
     * existants : un slug inconnu doit donc retomber sur la route article et
     * donner un 404 franc, pas une liste complète déguisée en rubrique.
     */
    public function test_an_unknown_slug_is_a_404(): void
    {
        BlogPost::factory()->create();

        $this->get('/blog/nexiste-pas')->assertNotFound();
    }

    public function test_every_category_has_its_own_route(): void
    {
        foreach (['conseils', 'actualites', 'essais', 'reglementation'] as $slug) {
            $this->get('/blog/'.$slug)->assertOk();
        }
    }

    /** Les deux routes cohabitent sur la même forme d'URL. */
    public function test_a_post_still_resolves_beside_the_category_routes(): void
    {
        $post = BlogPost::factory()->create();

        $this->get('/blog/'.$post->slug)
            ->assertOk()
            ->assertSee($post->localizedTitle(), false);
    }

    public function test_the_sitemap_lists_the_index_and_every_used_category(): void
    {
        BlogPost::factory()->create();

        $response = $this->get('/sitemap-blog.xml')->assertOk();

        $response->assertSee(route('blog.index'), false);
        $response->assertSee(route('blog.category', 'conseils'), false);
        // Une rubrique sans article visible n'a rien à indexer.
        $response->assertDontSee(route('blog.category', 'actualites'), false);
    }

    public function test_mentioned_products_are_shown_on_the_post(): void
    {
        $post = BlogPost::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);
        $post->products()->attach($product->id, ['sort_order' => 0]);

        $this->get('/blog/'.$post->slug)
            ->assertOk()
            ->assertSee(__('store.blog_related_products'), false)
            ->assertSee($product->localizedName(), false);
    }

    public function test_the_products_block_is_absent_when_nothing_is_attached(): void
    {
        $post = BlogPost::factory()->create();

        $this->get('/blog/'.$post->slug)
            ->assertOk()
            ->assertDontSee(__('store.blog_related_products'), false);
    }

    public function test_a_product_page_links_back_to_a_post_that_mentions_it(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $post = BlogPost::factory()->create();
        $post->products()->attach($product->id, ['sort_order' => 0]);

        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertSee(__('store.product_blog_posts'), false)
            ->assertSee($post->localizedTitle(), false);
    }

    /** Un brouillon citant un produit ne doit pas se trahir depuis sa fiche. */
    public function test_a_product_page_does_not_reveal_a_draft(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $draft = BlogPost::factory()->draft()->create();
        $draft->products()->attach($product->id, ['sort_order' => 0]);

        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertDontSee($draft->localizedTitle(), false);
    }

    public function test_the_sitemap_lists_only_visible_posts(): void
    {
        $visible = BlogPost::factory()->create();
        $draft = BlogPost::factory()->draft()->create();
        $scheduled = BlogPost::factory()->scheduled()->create();

        $response = $this->get('/sitemap-blog.xml')->assertOk();

        $response->assertSee($visible->slug, false);
        $response->assertDontSee($draft->slug, false);
        $response->assertDontSee($scheduled->slug, false);
    }

    public function test_the_blog_is_in_the_sitemap_index_and_the_nav(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertSee('sitemap-blog.xml', false);
        $this->get('/blog')->assertOk()->assertSee(__('store.nav_blog'), false);
    }
}
