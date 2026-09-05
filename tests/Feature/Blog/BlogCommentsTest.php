<?php

namespace Tests\Feature\Blog;

use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\User;
use App\Notifications\AdminBlogCommentReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The article's comment thread: anyone may write, everything publishes
 * at once, the shop hears about each comment and answers or prunes from
 * the page itself.
 */
class BlogCommentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['shop.admin_notification_email' => 'shop@armooutdoor.test']);
        Notification::fake();
    }

    public function test_a_guest_signs_with_a_pseudonym_and_publishes_at_once(): void
    {
        $post = BlogPost::factory()->create();

        $this->post(route('blog.comments.store', $post->slug), [
            'author_name' => 'TireurDuDimanche',
            'body' => "Très utile, merci.\nJe reviendrai.",
        ])->assertRedirect();

        $this->get('/blog/'.$post->slug)->assertOk()
            ->assertSee('TireurDuDimanche')
            ->assertSee('Très utile, merci.');

        Notification::assertSentTo(new AnonymousNotifiable, AdminBlogCommentReceived::class);
    }

    public function test_an_account_signs_first_name_and_last_initial(): void
    {
        $post = BlogPost::factory()->create();
        $user = User::factory()->create(['first_name' => 'Jean', 'last_name' => 'martin']);

        $this->actingAs($user)->post(route('blog.comments.store', $post->slug), [
            'body' => 'Bien vu.',
        ]);

        $this->get('/blog/'.$post->slug)->assertOk()->assertSee('Jean M.');
    }

    public function test_a_filled_honeypot_is_refused(): void
    {
        $post = BlogPost::factory()->create();

        $this->from('/blog/'.$post->slug)->post(route('blog.comments.store', $post->slug), [
            'author_name' => 'Bot',
            'body' => 'Spam.',
            'website' => 'https://spam.example',
        ])->assertSessionHasErrors('website');

        $this->assertSame(0, BlogComment::query()->count());
    }

    public function test_a_draft_accepts_no_comments(): void
    {
        $draft = BlogPost::factory()->create(['status' => 'draft', 'published_at' => null]);

        $this->post(route('blog.comments.store', $draft->slug), [
            'author_name' => 'X', 'body' => 'Y',
        ])->assertNotFound();
    }

    public function test_the_shop_replies_one_level_with_its_badge(): void
    {
        $post = BlogPost::factory()->create();
        $comment = BlogComment::query()->create([
            'blog_post_id' => $post->id, 'author_name' => 'Client', 'body' => 'Une question.',
        ]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('blog.comments.reply', $comment), ['body' => 'Notre réponse.'])
            ->assertRedirect();

        $this->get('/blog/'.$post->slug)->assertOk()
            ->assertSee('Notre réponse.')
            ->assertSee('blog-comment-card--shop', false);

        // One level only: replying to the reply is refused.
        $reply = BlogComment::query()->where('parent_id', $comment->id)->firstOrFail();
        $this->actingAs($admin)->post(route('blog.comments.reply', $reply), ['body' => 'Trop profond.'])
            ->assertStatus(422);
    }

    public function test_only_an_admin_replies_or_deletes(): void
    {
        $post = BlogPost::factory()->create();
        $comment = BlogComment::query()->create([
            'blog_post_id' => $post->id, 'author_name' => 'Client', 'body' => 'À supprimer.',
        ]);

        $this->post(route('blog.comments.reply', $comment), ['body' => 'x'])->assertRedirect(route('admin.login'));
        $this->delete(route('blog.comments.destroy', $comment))->assertRedirect(route('admin.login'));

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('blog.comments.destroy', $comment))
            ->assertRedirect();

        $this->assertSame(0, BlogComment::query()->count());
    }
}
