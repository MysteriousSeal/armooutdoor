<?php

namespace Tests\Feature\Blog;

use App\Models\BlogPost;
use App\Models\SiteVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The blog list's view counter: readers per article, read off the same
 * visit log the product relevance uses.
 */
class BlogViewCounterTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_list_counts_each_articles_visits(): void
    {
        $post = BlogPost::factory()->create([
            'sources' => [['label' => 'Legifrance', 'url' => 'https://www.legifrance.gouv.fr/x']],
        ]);
        $other = BlogPost::factory()->create();

        foreach (range(1, 3) as $i) {
            SiteVisit::query()->create(['path' => '/blog/'.$post->slug, 'ip_address' => '10.0.0.'.$i]);
        }
        // A visit to another page must not leak into the count.
        SiteVisit::query()->create(['path' => '/blog', 'ip_address' => '10.0.0.9']);
        // Neither does a crawler, nor a visit older than the window.
        SiteVisit::query()->create([
            'path' => '/blog/'.$post->slug, 'ip_address' => '10.0.0.8',
            'user_agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        ]);
        SiteVisit::query()->create(['path' => '/blog/'.$post->slug, 'ip_address' => '10.0.0.7'])
            ->forceFill(['created_at' => now()->subDays(45)])->save();

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.blog.index'))
            ->assertOk()
            ->assertSee('Views')
            ->assertSee('Sources')
            ->getContent();

        // 3 in the window, 4 lifetime (the 45-day-old visit counts there;
        // the crawler counts nowhere), the sibling at 0 / 0.
        $this->assertMatchesRegularExpression('#'.preg_quote($post->localizedTitle(), '#').'.*?3\s*<span[^>]*>/ 4</span>#s', $html);
        $this->assertMatchesRegularExpression('#'.preg_quote($other->localizedTitle(), '#').'.*?0\s*<span[^>]*>/ 0</span>#s', $html);
    }
}
