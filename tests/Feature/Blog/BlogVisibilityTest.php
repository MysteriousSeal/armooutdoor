<?php

namespace Tests\Feature\Blog;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Qui a le droit de voir quoi.
 *
 * Trois états se ressemblent en base et se distinguent ici : le brouillon, le
 * programmé, le publié. Toute la façade publique passe par `visible()` — si ce
 * périmètre se relâche, un article non fini se retrouve en ligne sans que rien
 * ne le signale. Ces tests sont écrits avant le reste, exprès.
 */
class BlogVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_published_post_dated_in_the_past_is_visible(): void
    {
        $post = BlogPost::factory()->create(['published_at' => now()->subDay()]);

        $this->assertTrue(BlogPost::query()->visible()->whereKey($post->id)->exists());
        $this->assertTrue($post->isVisible());
    }

    public function test_a_draft_is_not_visible(): void
    {
        $post = BlogPost::factory()->draft()->create();

        $this->assertFalse(BlogPost::query()->visible()->whereKey($post->id)->exists());
        $this->assertFalse($post->isVisible());
    }

    /** Publié mais daté de demain : invisible aujourd'hui. */
    public function test_a_scheduled_post_is_not_visible_yet(): void
    {
        $post = BlogPost::factory()->scheduled()->create();

        $this->assertFalse(BlogPost::query()->visible()->whereKey($post->id)->exists());
        $this->assertFalse($post->isVisible());
        $this->assertTrue($post->isScheduled());
    }

    public function test_a_scheduled_post_becomes_visible_when_its_hour_comes(): void
    {
        $post = BlogPost::factory()->create(['published_at' => now()->addHours(2)]);

        $this->assertFalse(BlogPost::query()->visible()->whereKey($post->id)->exists());

        Carbon::setTestNow(now()->addHours(3));

        $this->assertTrue(BlogPost::query()->visible()->whereKey($post->id)->exists());

        Carbon::setTestNow();
    }

    /** Un statut publié sans date ne dit pas quand : il reste invisible. */
    public function test_published_without_a_date_is_not_visible(): void
    {
        $post = BlogPost::factory()->create(['status' => 'published', 'published_at' => null]);

        $this->assertFalse(BlogPost::query()->visible()->whereKey($post->id)->exists());
    }
}
