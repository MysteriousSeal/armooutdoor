<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Support\ArticleSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What an article says about itself.
 *
 * The blog carried a breadcrumb trail and nothing else: seven pieces of three
 * thousand words apiece, each written to be found, none of them saying when it
 * was published, who published it, or what it is.
 */
class ArticleSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_article_declares_when_it_was_written_and_by_whom(): void
    {
        $post = BlogPost::factory()->create();

        $schema = ArticleSchema::for($post);

        $this->assertSame('BlogPosting', $schema['@type']);
        $this->assertNotEmpty($schema['datePublished']);
        $this->assertNotEmpty($schema['dateModified']);
        $this->assertSame('Armo Outdoor', $schema['publisher']['name']);
        $this->assertSame(route('blog.show', $post->slug), $schema['mainEntityOfPage']);
    }

    public function test_the_article_page_emits_it(): void
    {
        $post = BlogPost::factory()->create();

        $this->get('/blog/'.$post->slug)
            ->assertOk()
            ->assertSee('"@type":"BlogPosting"', false);
    }

    public function test_an_article_that_was_never_edited_still_dates_itself(): void
    {
        $post = BlogPost::factory()->create();
        $post->forceFill(['updated_at' => null])->save();

        $schema = ArticleSchema::for($post->fresh());

        $this->assertSame($schema['datePublished'], $schema['dateModified']);
    }
}
