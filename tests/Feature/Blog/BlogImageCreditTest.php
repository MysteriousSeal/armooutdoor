<?php

namespace Tests\Feature\Blog;

use App\Models\BlogPost;
use App\Models\User;
use Database\Seeders\BlogCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le crédit du visuel de bandeau.
 *
 * Facultatif, et lié à l'image : il n'a de sens que s'il y a quelque chose à
 * créditer, et il ne doit pas survivre au retrait du visuel.
 */
class BlogImageCreditTest extends TestCase
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

    public function test_a_credit_is_shown_on_the_article_when_there_is_a_cover(): void
    {
        $post = BlogPost::factory()->create([
            'image' => 'blog/x.webp',
            'image_credit' => 'Umarex',
        ]);

        // Le nom est saisi seul, le préfixe est ajouté à l'affichage.
        $this->get('/blog/'.$post->slug)
            ->assertOk()
            ->assertSee('Photo © Umarex', false);
    }

    /** Sans visuel, le crédit ne crédite rien : il ne s'affiche pas. */
    public function test_a_credit_without_a_cover_is_not_shown(): void
    {
        $post = BlogPost::factory()->create([
            'image' => null,
            'image_credit' => 'Umarex',
        ]);

        $this->get('/blog/'.$post->slug)
            ->assertOk()
            ->assertDontSee('Umarex', false);
    }

    public function test_an_article_without_a_credit_shows_no_credit_line(): void
    {
        $post = BlogPost::factory()->create(['image' => 'blog/x.webp', 'image_credit' => null]);

        $this->get('/blog/'.$post->slug)
            ->assertOk()
            ->assertDontSee('blog-article-credit', false);
    }

    public function test_an_admin_can_save_a_credit(): void
    {
        $post = BlogPost::factory()->create(['image' => 'blog/x.webp']);

        $this->actingAs($this->admin())->put('/admin/blog/'.$post->id, [
            'title' => $post->localizedTitle(),
            'blog_category_id' => $post->blog_category_id,
            'body' => '<p>x</p>',
            'status' => 'draft',
            'image_credit' => 'Armo Outdoor',
        ])->assertRedirect();

        $this->assertSame('Armo Outdoor', $post->fresh()->image_credit);
    }

    public function test_the_credit_is_optional(): void
    {
        $post = BlogPost::factory()->create(['image' => 'blog/x.webp']);

        $this->actingAs($this->admin())->put('/admin/blog/'.$post->id, [
            'title' => $post->localizedTitle(),
            'blog_category_id' => $post->blog_category_id,
            'body' => '<p>x</p>',
            'status' => 'draft',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNull($post->fresh()->image_credit);
    }

    /** Retirer l'image emporte son crédit. */
    public function test_removing_the_cover_clears_the_credit(): void
    {
        $post = BlogPost::factory()->create([
            'image' => 'blog/x.webp',
            'image_credit' => 'Umarex',
        ]);

        $this->actingAs($this->admin())->put('/admin/blog/'.$post->id, [
            'title' => $post->localizedTitle(),
            'blog_category_id' => $post->blog_category_id,
            'body' => '<p>x</p>',
            'status' => 'draft',
            'remove_image' => 1,
            'image_credit' => 'Umarex',
        ])->assertRedirect();

        $post->refresh();
        $this->assertNull($post->image);
        $this->assertNull($post->image_credit);
    }

    public function test_the_api_reads_and_writes_the_credit(): void
    {
        config(['services.admin_api.token' => 't']);
        $headers = ['Authorization' => 'Bearer t'];
        $post = BlogPost::factory()->create(['image' => 'blog/x.webp']);

        $this->patchJson('/api/admin/blog/posts/'.$post->id, [
            'image_credit' => 'Mechanix',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.image_credit', 'Mechanix');

        $this->getJson('/api/admin/blog/posts/'.$post->id, $headers)
            ->assertOk()
            ->assertJsonPath('data.image_credit', 'Mechanix');
    }

    /** Retaper « Photo © » ne doit pas le faire apparaître deux fois. */
    public function test_a_typed_prefix_is_not_doubled(): void
    {
        $post = BlogPost::factory()->create(['image' => 'blog/x.webp']);

        foreach (['Photo © Umarex', 'photo© Umarex', '© Umarex', '  Umarex '] as $typed) {
            $this->actingAs($this->admin())->put('/admin/blog/'.$post->id, [
                'title' => $post->localizedTitle(),
                'blog_category_id' => $post->blog_category_id,
                'body' => '<p>x</p>',
                'status' => 'draft',
                'image_credit' => $typed,
            ])->assertRedirect();

            $this->assertSame('Umarex', $post->fresh()->image_credit, "stored for: {$typed}");
            $this->assertSame('Photo © Umarex', $post->fresh()->imageCreditLine(), "shown for: {$typed}");
        }
    }

    public function test_the_api_also_strips_a_typed_prefix(): void
    {
        config(['services.admin_api.token' => 't']);
        $post = BlogPost::factory()->create(['image' => 'blog/x.webp']);

        $this->patchJson('/api/admin/blog/posts/'.$post->id, [
            'image_credit' => 'Photo © Mechanix',
        ], ['Authorization' => 'Bearer t'])
            ->assertOk()
            ->assertJsonPath('data.image_credit', 'Mechanix');
    }

    /** Une valeur qui se réduit à un préfixe seul ne vaut rien. */
    public function test_a_credit_made_only_of_the_prefix_is_stored_as_empty(): void
    {
        $post = BlogPost::factory()->create(['image' => 'blog/x.webp']);

        $this->actingAs($this->admin())->put('/admin/blog/'.$post->id, [
            'title' => $post->localizedTitle(),
            'blog_category_id' => $post->blog_category_id,
            'body' => '<p>x</p>',
            'status' => 'draft',
            'image_credit' => 'Photo ©',
        ])->assertRedirect();

        $this->assertNull($post->fresh()->image_credit);
    }

    public function test_the_api_rejects_an_overlong_credit(): void
    {
        config(['services.admin_api.token' => 't']);
        $post = BlogPost::factory()->create();

        $this->patchJson('/api/admin/blog/posts/'.$post->id, [
            'image_credit' => str_repeat('a', 181),
        ], ['Authorization' => 'Bearer t'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('image_credit');
    }
}
