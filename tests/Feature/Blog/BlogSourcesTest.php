<?php

namespace Tests\Feature\Blog;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The article's sources: saved from the admin form, shown at the foot of
 * the post, cited in its schema. A shop that shows its sources reads as a
 * shop that checked them.
 */
class BlogSourcesTest extends TestCase
{
    use RefreshDatabase;

    private function postPayload(array $overrides = []): array
    {
        return [
            'blog_category_id' => BlogCategory::query()->firstOrCreate(
                ['slug' => 'conseils'],
                ['name' => ['fr' => 'Conseils'], 'sort_order' => 0],
            )->id,
            'title' => 'Nettoyer son canon',
            'body' => '<p>Un corps d\'article.</p>',
            'status' => 'published',
            'published_at' => now()->subHour()->format('Y-m-d\TH:i'),
            ...$overrides,
        ];
    }

    public function test_sources_survive_the_save_and_blank_rows_do_not(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.blog.store'), $this->postPayload([
                'sources' => [
                    ['label' => 'Legifrance', 'url' => 'https://www.legifrance.gouv.fr/dossier'],
                    ['label' => '', 'url' => 'https://www.example.org/etude'],
                    ['label' => 'Une ligne vide', 'url' => ''],
                ],
            ]));

        $post = BlogPost::query()->firstOrFail();

        $this->assertCount(2, $post->sources);
        $this->assertSame('Legifrance', $post->sources[0]['label']);
    }

    public function test_the_post_shows_its_sources_with_host_as_fallback_label(): void
    {
        $post = BlogPost::factory()->create([
            'sources' => [
                ['label' => 'Legifrance', 'url' => 'https://www.legifrance.gouv.fr/dossier'],
                ['label' => '', 'url' => 'https://www.example.org/etude'],
            ],
        ]);

        $this->get('/blog/'.$post->slug)->assertOk()
            ->assertSee('Sources')
            ->assertSee('rel="noopener"', false)
            ->assertSee('Legifrance')
            // No label: the host stands in.
            ->assertSee('www.example.org')
            // And the schema cites the same links.
            ->assertSee('"citation"', false)
            ->assertSee('https://www.legifrance.gouv.fr/dossier');
    }

    public function test_a_post_without_sources_shows_no_empty_block(): void
    {
        $post = BlogPost::factory()->create(['sources' => null]);

        $this->get('/blog/'.$post->slug)->assertOk()
            ->assertDontSee('blog-article-sources')
            ->assertDontSee('"citation"', false);
    }

    public function test_a_source_that_is_not_a_url_is_refused(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.blog.store'), $this->postPayload([
                'sources' => [['label' => 'Pas un lien', 'url' => 'javascript:alert(1)']],
            ]))
            ->assertSessionHasErrors('sources.0.url');
    }
}
