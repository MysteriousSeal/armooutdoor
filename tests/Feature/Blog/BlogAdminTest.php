<?php

namespace Tests\Feature\Blog;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\BlogCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** L'administration du blog. */
class BlogAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BlogCategorySeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function category(string $slug = 'conseils'): BlogCategory
    {
        return BlogCategory::query()->where('slug', $slug)->firstOrFail();
    }

    public function test_an_admin_can_create_a_post(): void
    {
        $this->actingAs($this->admin())->post('/admin/blog', [
            'title' => 'Comment choisir sa première réplique',
            'blog_category_id' => $this->category()->id,
            'excerpt' => 'Un guide court.',
            'body' => '<p>Le texte.</p>',
            'status' => 'published',
            'published_at' => now()->subHour()->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        $post = BlogPost::query()->firstOrFail();

        $this->assertSame('comment-choisir-sa-premiere-replique', $post->slug);
        $this->assertSame('Comment choisir sa première réplique', $post->localizedTitle());
        $this->assertTrue($post->isVisible());
    }

    /** Publier sans date laisserait l'article invisible pour toujours. */
    public function test_publishing_without_a_date_is_refused(): void
    {
        $this->actingAs($this->admin())->post('/admin/blog', [
            'title' => 'Sans date',
            'blog_category_id' => $this->category()->id,
            'body' => '<p>x</p>',
            'status' => 'published',
        ])->assertSessionHasErrors('published_at');

        $this->assertSame(0, BlogPost::query()->count());
    }

    public function test_the_body_is_sanitised_on_save(): void
    {
        $this->actingAs($this->admin())->post('/admin/blog', [
            'title' => 'Nettoyage',
            'blog_category_id' => $this->category()->id,
            'body' => '<p>ok</p><script>alert(1)</script>',
            'status' => 'draft',
        ])->assertRedirect();

        $body = BlogPost::query()->firstOrFail()->localizedBody();

        $this->assertStringNotContainsString('script', $body);
        $this->assertStringContainsString('ok', $body);
    }

    /** Le corps d'article garde ses images ; la fiche produit n'en a pas. */
    public function test_a_same_origin_image_survives_in_the_body(): void
    {
        config(['app.url' => 'https://armooutdoor.test']);

        $this->actingAs($this->admin())->post('/admin/blog', [
            'title' => 'Avec image',
            'blog_category_id' => $this->category()->id,
            'body' => '<p>a</p><img src="/images/blog/x.webp" alt="x"><img src="https://evil.example/x.jpg">',
            'status' => 'draft',
        ])->assertRedirect();

        $body = BlogPost::query()->firstOrFail()->localizedBody();

        $this->assertStringContainsString('/images/blog/x.webp', $body);
        $this->assertStringNotContainsString('evil.example', $body);
    }

    public function test_the_slug_survives_a_title_change(): void
    {
        $post = BlogPost::factory()->create(['slug' => 'un-titre-original']);

        $this->actingAs($this->admin())->put('/admin/blog/'.$post->id, [
            'title' => 'Un titre tout à fait différent',
            'blog_category_id' => $post->blog_category_id,
            'body' => '<p>x</p>',
            'status' => 'draft',
        ])->assertRedirect();

        $this->assertSame('un-titre-original', $post->fresh()->slug);
    }

    public function test_slugs_do_not_collide(): void
    {
        foreach (range(1, 2) as $ignored) {
            $this->actingAs($this->admin())->post('/admin/blog', [
                'title' => 'Même titre',
                'blog_category_id' => $this->category()->id,
                'body' => '<p>x</p>',
                'status' => 'draft',
            ])->assertRedirect();
        }

        $this->assertDatabaseHas('blog_posts', ['slug' => 'meme-titre']);
        $this->assertDatabaseHas('blog_posts', ['slug' => 'meme-titre-2']);
    }

    /**
     * Un article intitulé « Conseils » prendrait le slug d'une rubrique, et la
     * route rubrique le masquerait définitivement.
     */
    public function test_a_post_cannot_take_a_category_slug(): void
    {
        $this->actingAs($this->admin())->post('/admin/blog', [
            'title' => 'Conseils',
            'blog_category_id' => $this->category()->id,
            'body' => '<p>x</p>',
            'status' => 'draft',
        ])->assertRedirect();

        $slug = BlogPost::query()->firstOrFail()->slug;

        $this->assertNotSame('conseils', $slug);
        $this->assertSame('conseils-2', $slug);
    }

    public function test_mentioned_products_are_attached_and_ordered(): void
    {
        $post = BlogPost::factory()->create();
        $first = Product::factory()->create();
        $second = Product::factory()->create();

        $this->actingAs($this->admin())->put('/admin/blog/'.$post->id, [
            'title' => $post->localizedTitle(),
            'blog_category_id' => $post->blog_category_id,
            'body' => '<p>x</p>',
            'status' => 'draft',
            'product_ids' => [$second->id, $first->id],
        ])->assertRedirect();

        $this->assertSame([$second->id, $first->id], $post->fresh()->products->pluck('id')->all());
    }

    public function test_an_admin_can_delete_a_post(): void
    {
        $post = BlogPost::factory()->create();

        $this->actingAs($this->admin())->delete('/admin/blog/'.$post->id)->assertRedirect();

        $this->assertNull(BlogPost::query()->find($post->id));
    }

    public function test_the_scheduled_tab_shows_what_the_others_hide(): void
    {
        $scheduled = BlogPost::factory()->scheduled()->create();

        $this->actingAs($this->admin())
            ->get('/admin/blog?tab=scheduled')
            ->assertOk()
            ->assertSee($scheduled->localizedTitle(), false);

        $this->actingAs($this->admin())
            ->get('/admin/blog?tab=published')
            ->assertOk()
            ->assertDontSee($scheduled->localizedTitle(), false);
    }

    public function test_a_customer_cannot_reach_the_blog_admin(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/blog')
            ->assertRedirect();
    }
}
