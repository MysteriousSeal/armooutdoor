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

    public function test_the_hero_says_views_and_comments(): void
    {
        $post = BlogPost::factory()->create();
        BlogComment::query()->create(['blog_post_id' => $post->id, 'author_name' => 'A', 'body' => 'Un.']);
        \App\Models\SiteVisit::query()->create(['path' => '/blog/'.$post->slug, 'ip_address' => '10.0.0.1']);
        // A crawler's visit counts nowhere.
        \App\Models\SiteVisit::query()->create([
            'path' => '/blog/'.$post->slug, 'ip_address' => '10.0.0.2',
            'user_agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        ]);

        $this->get('/blog/'.$post->slug)->assertOk()
            ->assertSee('blog-article-stats', false)
            ->assertSee('1 vue')
            ->assertSee('1 commentaire');
    }

    public function test_the_schema_says_the_engagement(): void
    {
        $post = BlogPost::factory()->create();
        BlogComment::query()->create(['blog_post_id' => $post->id, 'author_name' => 'Lecteur', 'body' => 'Bien vu.']);
        \App\Models\SiteVisit::query()->create(['path' => '/blog/'.$post->slug, 'ip_address' => '10.0.0.1']);

        $this->get('/blog/'.$post->slug)->assertOk()
            ->assertSee('"commentCount":1', false)
            ->assertSee('"timeRequired":"PT1M"', false)
            ->assertSee('"interactionType":"https://schema.org/ReadAction"', false)
            ->assertSee('"userInteractionCount":1', false)
            ->assertSee('"@type":"Comment"', false)
            ->assertSee('Lecteur');
    }

    public function test_the_blog_cards_wear_the_comment_count(): void
    {
        $post = BlogPost::factory()->create();
        BlogComment::query()->create(['blog_post_id' => $post->id, 'author_name' => 'A', 'body' => 'Premier.']);
        BlogComment::query()->create(['blog_post_id' => $post->id, 'author_name' => 'B', 'body' => 'Second.']);

        $this->get('/blog')->assertOk()
            ->assertSee('has-comments', false)
            ->assertSee('2 commentaires');
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

    public function test_only_an_admin_replies(): void
    {
        $post = BlogPost::factory()->create();
        $comment = BlogComment::query()->create([
            'blog_post_id' => $post->id, 'author_name' => 'Client', 'body' => 'Question.',
        ]);

        $this->post(route('blog.comments.reply', $comment), ['body' => 'x'])->assertRedirect(route('admin.login'));
    }

    public function test_deleting_lives_in_the_back_office_not_on_the_post(): void
    {
        $post = BlogPost::factory()->create();
        $comment = BlogComment::query()->create([
            'blog_post_id' => $post->id, 'author_name' => 'Client', 'body' => 'À supprimer.',
        ]);
        $admin = User::factory()->admin()->create();

        // A guest is turned away - checked before actingAs, which sticks
        // to the test's session for every later request.
        $this->delete(route('admin.blog.comments.destroy', $comment))->assertRedirect(route('admin.login'));

        // The article page offers no delete, even to an admin.
        $this->actingAs($admin)->get('/blog/'.$post->slug)->assertOk()
            ->assertDontSee('blog.comments.destroy')
            ->assertDontSee('blog-comment-delete');

        // The back-office comments tab lists it and deletes it.
        $this->actingAs($admin)->get(route('admin.blog.comments.index'))->assertOk()
            ->assertSee('À supprimer.')
            ->assertSee($post->localizedTitle());

        $this->actingAs($admin)->delete(route('admin.blog.comments.destroy', $comment))->assertRedirect();

        $this->assertSame(0, BlogComment::query()->count());
    }
}
