<?php

namespace Tests\Feature\Api;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Product;
use Database\Seeders\BlogCategorySeeder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * L'API blog de l'administration.
 *
 * Mêmes garde-fous que l'API produits — jeton, débit, enveloppe — et les
 * règles propres au blog : un article publié doit dire quand, son adresse ne
 * bouge plus, et son corps garde les images de la boutique seulement.
 */
class AdminBlogApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.admin_api.token' => 'test-admin-api-token']);
        $this->seed(BlogCategorySeeder::class);
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer test-admin-api-token'];
    }

    private function categoryId(string $slug = 'conseils'): int
    {
        return BlogCategory::query()->where('slug', $slug)->value('id');
    }

    // ------------------------------------------------------------- auth

    public function test_a_request_without_a_token_is_rejected(): void
    {
        $this->getJson('/api/admin/blog/posts')->assertStatus(401);
    }

    public function test_the_api_is_rate_limited(): void
    {
        RateLimiter::for('admin-api', fn (): Limit => Limit::perMinute(2)->by('blog-throttle'));

        $this->getJson('/api/admin/blog/posts', $this->headers())->assertOk();
        $this->getJson('/api/admin/blog/posts', $this->headers())->assertOk();
        $this->getJson('/api/admin/blog/posts', $this->headers())->assertStatus(429);
    }

    // ----------------------------------------------------------- create

    public function test_a_post_can_be_created(): void
    {
        $response = $this->postJson('/api/admin/blog/posts', [
            'title' => 'Comment choisir sa première réplique',
            'body' => '<p>Le texte.</p>',
            'blog_category_id' => $this->categoryId(),
            'excerpt' => 'Un guide court.',
            'status' => 'published',
            'published_at' => now()->subHour()->toIso8601String(),
        ], $this->headers())->assertCreated();

        $response->assertJsonPath('data.slug', 'comment-choisir-sa-premiere-replique');
        $response->assertJsonPath('data.is_visible', true);
        $this->assertSame(1, BlogPost::query()->count());
    }

    public function test_creating_requires_the_essentials(): void
    {
        $this->postJson('/api/admin/blog/posts', [], $this->headers())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'body', 'blog_category_id']);
    }

    public function test_a_new_post_defaults_to_draft(): void
    {
        $this->postJson('/api/admin/blog/posts', [
            'title' => 'Sans statut',
            'body' => '<p>x</p>',
            'blog_category_id' => $this->categoryId(),
        ], $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.is_visible', false);
    }

    public function test_slugs_do_not_collide(): void
    {
        $payload = ['title' => 'Même titre', 'body' => '<p>x</p>', 'blog_category_id' => $this->categoryId()];

        $this->postJson('/api/admin/blog/posts', $payload, $this->headers())->assertCreated();
        $this->postJson('/api/admin/blog/posts', $payload, $this->headers())->assertCreated();

        $this->assertDatabaseHas('blog_posts', ['slug' => 'meme-titre']);
        $this->assertDatabaseHas('blog_posts', ['slug' => 'meme-titre-2']);
    }

    /** Même garde-fou côté API : le slug d'une rubrique est réservé. */
    public function test_a_created_post_cannot_take_a_category_slug(): void
    {
        $this->postJson('/api/admin/blog/posts', [
            'title' => 'Réglementation',
            'body' => '<p>x</p>',
            'blog_category_id' => $this->categoryId(),
        ], $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.slug', 'reglementation-2');
    }

    // ------------------------------------------------------- publication

    /** Publier sans date donne un article que rien ne montrera jamais. */
    public function test_publishing_without_a_date_is_refused(): void
    {
        $this->postJson('/api/admin/blog/posts', [
            'title' => 'Sans date',
            'body' => '<p>x</p>',
            'blog_category_id' => $this->categoryId(),
            'status' => 'published',
        ], $this->headers())
            ->assertStatus(422)
            ->assertJsonValidationErrors('published_at');

        $this->assertSame(0, BlogPost::query()->count());
    }

    /** Passer un brouillon sans date en « publié » se refuse aussi. */
    public function test_switching_a_dateless_draft_to_published_is_refused(): void
    {
        $post = BlogPost::factory()->draft()->create();

        $this->patchJson('/api/admin/blog/posts/'.$post->id, ['status' => 'published'], $this->headers())
            ->assertStatus(422)
            ->assertJsonValidationErrors('published_at');

        $this->assertSame('draft', $post->fresh()->status);
    }

    public function test_a_dated_post_reports_scheduled_until_its_hour(): void
    {
        $post = BlogPost::factory()->create(['published_at' => now()->addHours(2)]);

        $this->getJson('/api/admin/blog/posts/'.$post->id, $this->headers())
            ->assertOk()
            ->assertJsonPath('data.is_visible', false)
            ->assertJsonPath('data.is_scheduled', true)
            ->assertJsonPath('data.url', null);

        Carbon::setTestNow(now()->addHours(3));

        $this->getJson('/api/admin/blog/posts/'.$post->id, $this->headers())
            ->assertOk()
            ->assertJsonPath('data.is_visible', true);

        Carbon::setTestNow();
    }

    // ------------------------------------------------------------ update

    public function test_a_partial_update_leaves_other_fields_alone(): void
    {
        $post = BlogPost::factory()->create(['meta_title' => 'KEEP']);

        $this->patchJson('/api/admin/blog/posts/'.$post->id, ['excerpt' => 'Nouveau chapô'], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.excerpt', 'Nouveau chapô')
            ->assertJsonPath('data.meta_title', 'KEEP');
    }

    public function test_the_slug_survives_a_title_change(): void
    {
        $post = BlogPost::factory()->create(['slug' => 'un-titre-original']);

        $this->patchJson('/api/admin/blog/posts/'.$post->id, ['title' => 'Tout autre chose'], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.slug', 'un-titre-original');
    }

    public function test_a_post_can_be_deleted(): void
    {
        $post = BlogPost::factory()->create();

        $this->deleteJson('/api/admin/blog/posts/'.$post->id, [], $this->headers())->assertNoContent();

        $this->assertNull(BlogPost::query()->find($post->id));
    }

    // ----------------------------------------------------------- content

    public function test_the_body_is_sanitised(): void
    {
        $response = $this->postJson('/api/admin/blog/posts', [
            'title' => 'Nettoyage',
            'body' => '<p>ok</p><script>alert(1)</script>',
            'blog_category_id' => $this->categoryId(),
        ], $this->headers())->assertCreated();

        $body = $response->json('data.body');
        $this->assertStringNotContainsString('script', $body);
        $this->assertStringContainsString('ok', $body);
    }

    public function test_only_same_origin_images_survive_the_body(): void
    {
        config(['app.url' => 'https://armooutdoor.test']);

        $response = $this->postJson('/api/admin/blog/posts', [
            'title' => 'Avec images',
            'body' => '<p>a</p><img src="/images/blog/x.webp" alt="x"><img src="//evil.example/y.jpg">',
            'blog_category_id' => $this->categoryId(),
        ], $this->headers())->assertCreated();

        $body = $response->json('data.body');
        $this->assertStringContainsString('/images/blog/x.webp', $body);
        $this->assertStringNotContainsString('evil.example', $body);
    }

    public function test_products_can_be_attached_in_order(): void
    {
        $post = BlogPost::factory()->create();
        $first = Product::factory()->create();
        $second = Product::factory()->create();

        $this->patchJson('/api/admin/blog/posts/'.$post->id, [
            'product_ids' => [$second->id, $first->id],
        ], $this->headers())->assertOk();

        $this->assertSame([$second->id, $first->id], $post->fresh()->products->pluck('id')->all());
    }

    public function test_omitting_product_ids_leaves_the_attachments_alone(): void
    {
        $post = BlogPost::factory()->create();
        $product = Product::factory()->create();
        $post->products()->attach($product->id, ['sort_order' => 0]);

        $this->patchJson('/api/admin/blog/posts/'.$post->id, ['excerpt' => 'x'], $this->headers())->assertOk();

        $this->assertSame([$product->id], $post->fresh()->products->pluck('id')->all());
    }

    /** Envoyer un tableau vide détache tout — c'est ce que la doc annonce. */
    public function test_an_empty_product_list_detaches_everything(): void
    {
        $post = BlogPost::factory()->create();
        $post->products()->attach(Product::factory()->create()->id, ['sort_order' => 0]);

        $this->patchJson('/api/admin/blog/posts/'.$post->id, ['product_ids' => []], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.products', []);

        $this->assertSame(0, $post->fresh()->products()->count());
    }

    public function test_an_unknown_post_is_a_404(): void
    {
        $this->getJson('/api/admin/blog/posts/999999', $this->headers())->assertNotFound();
        $this->patchJson('/api/admin/blog/posts/999999', ['excerpt' => 'x'], $this->headers())->assertNotFound();
        $this->deleteJson('/api/admin/blog/posts/999999', [], $this->headers())->assertNotFound();
    }

    // ----------------------------------------------------------- listing

    public function test_the_list_can_be_filtered_by_state(): void
    {
        $visible = BlogPost::factory()->create();
        $draft = BlogPost::factory()->draft()->create();
        $scheduled = BlogPost::factory()->scheduled()->create();

        $this->getJson('/api/admin/blog/posts?status=visible', $this->headers())
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $visible->id);

        $this->getJson('/api/admin/blog/posts?status=draft', $this->headers())
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $draft->id);

        $this->getJson('/api/admin/blog/posts?status=scheduled', $this->headers())
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $scheduled->id);
    }

    public function test_the_list_can_be_filtered_by_category_slug(): void
    {
        $guide = BlogPost::factory()->create(['blog_category_id' => $this->categoryId('conseils')]);
        BlogPost::factory()->create(['blog_category_id' => $this->categoryId('actualites')]);

        $this->getJson('/api/admin/blog/posts?category=conseils', $this->headers())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $guide->id);
    }

    /** Un brouillon reste listé ici : l'API sert l'administration. */
    public function test_the_list_shows_drafts_too(): void
    {
        BlogPost::factory()->draft()->create();

        $this->getJson('/api/admin/blog/posts', $this->headers())
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_the_page_size_is_capped(): void
    {
        BlogPost::factory()->count(3)->create();

        $this->getJson('/api/admin/blog/posts?per_page=5000', $this->headers())
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_the_categories_endpoint_lists_them_with_counts(): void
    {
        BlogPost::factory()->create(['blog_category_id' => $this->categoryId('conseils')]);
        BlogPost::factory()->draft()->create(['blog_category_id' => $this->categoryId('conseils')]);

        $response = $this->getJson('/api/admin/blog/categories', $this->headers())->assertOk();

        $conseils = collect($response->json('data'))->firstWhere('slug', 'conseils');
        // Le compte suit le périmètre public : le brouillon n'y entre pas.
        $this->assertSame(1, $conseils['posts_count']);
    }
}
