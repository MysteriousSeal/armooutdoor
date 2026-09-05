<?php

namespace Tests\Feature\Blog;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The draft preview: an unreferenced address only a signed-in admin can
 * open, showing the post as the page it will become - banner and noindex
 * on, everyone else sent to the back-office login.
 */
class BlogPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function draft(): BlogPost
    {
        return BlogPost::factory()->create(['status' => 'draft', 'published_at' => null]);
    }

    public function test_an_admin_sees_the_draft_with_banner_and_noindex(): void
    {
        $post = $this->draft();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('blog.preview', $post))
            ->assertOk()
            ->assertSee($post->localizedTitle())
            ->assertSee('Aperçu')
            ->assertSee('noindex, nofollow', false);
    }

    public function test_the_draft_stays_a_404_at_its_public_address(): void
    {
        $this->get('/blog/'.$this->draft()->slug)->assertNotFound();
    }

    public function test_guests_and_customers_are_sent_to_the_admin_login(): void
    {
        $post = $this->draft();

        $this->get(route('blog.preview', $post))->assertRedirect(route('admin.login'));

        $this->actingAs(User::factory()->create())
            ->get(route('blog.preview', $post))
            ->assertRedirect(route('admin.login'));
    }

    public function test_the_edit_page_offers_the_preview_button_to_drafts_only(): void
    {
        $admin = User::factory()->admin()->create();
        $draft = $this->draft();
        $published = BlogPost::factory()->create();

        $this->actingAs($admin)->get(route('admin.blog.edit', $draft))
            ->assertOk()->assertSee('Preview')->assertSee('blog/apercu/'.$draft->id);

        // A published post trades the preview for the real address.
        $this->actingAs($admin)->get(route('admin.blog.edit', $published))
            ->assertOk()->assertDontSee('blog/apercu/')->assertSee('>View</a>', false);
    }

    public function test_a_published_post_shows_no_preview_banner(): void
    {
        $post = BlogPost::factory()->create();

        $this->get('/blog/'.$post->slug)->assertOk()->assertDontSee('blog-preview-banner');
    }
}
