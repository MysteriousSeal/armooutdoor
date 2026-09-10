<?php

namespace Tests\Feature\Blog;

use App\Models\BlogPost;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The article's plan: every h2 gets an id at render time, and a long read
 * opens with a strip of links to them. The comments sit between the
 * sources and the "any question?" aside, so a reader who finished the
 * article meets the discussion before the sales pitch.
 */
class BlogArticlePlanTest extends TestCase
{
    use RefreshDatabase;

    private function bodyWithThreeHeadings(): string
    {
        return '<p>Intro.</p>'
            .'<h2>Réglage de la lunette</h2><p>Un.</p>'
            .'<h2>À quelle distance</h2><p>Deux.</p>'
            .'<h2>Cœur de cible</h2><p>Trois.</p>';
    }

    public function test_the_plan_links_to_the_ids_written_on_the_headings(): void
    {
        $post = BlogPost::factory()->create(['body' => ['fr' => $this->bodyWithThreeHeadings()]]);

        $html = $this->get('/blog/'.$post->slug)->assertOk()->getContent();

        [$nav, $navEnd] = $this->planNav($html);

        preg_match_all('/href="#([^"]+)"/', $nav, $links);
        $this->assertSame(
            ['reglage-de-la-lunette', 'a-quelle-distance', 'coeur-de-cible'],
            $links[1]
        );

        // Every link resolves to an id on an h2 of the body, once.
        foreach ($links[1] as $id) {
            $this->assertSame(1, substr_count($html, '<h2 id="'.$id.'">'), $id);
        }

        // The heading text comes through with its accents, in the nav and in
        // the body alike, with no Latin-1 misreading of the fragment. Each
        // pill opens with its zero-padded rank, in reading order, hidden from
        // the link's accessible name; the heading itself carries no number,
        // the section counter is CSS.
        foreach (['Réglage de la lunette', 'À quelle distance', 'Cœur de cible'] as $i => $text) {
            $this->assertStringContainsString('>0'.($i + 1).'</span>'.$text.'</a>', $nav);
            $this->assertStringContainsString('">'.$text.'</h2>', $html);
        }
        preg_match_all('/<span class="blog-article-plan-num" aria-hidden="true">(\d+)<\/span>/', $nav, $numbers);
        $this->assertSame(['01', '02', '03'], $numbers[1]);
        $this->assertStringNotContainsString('Ã', $html);
        $this->assertStringNotContainsString('&Atilde;', $html);
        $this->assertStringNotContainsString('&Aring;', $html);
    }

    public function test_inline_links_in_the_body_are_left_exactly_as_stored(): void
    {
        $anchor = '<a href="/products/x" rel="noopener noreferrer nofollow" target="_blank">cibles rondes</a>';
        $post = BlogPost::factory()->create(['body' => ['fr' =>
            '<h2>Un</h2><p>Voir les '.$anchor.' avant tout.</p><h2>Deux</h2><p>Fin.</p>',
        ]]);

        $this->get('/blog/'.$post->slug)->assertOk()
            ->assertSee($anchor, false);

        $this->assertStringContainsString($anchor, $post->annotatedBody()['html']);
    }

    public function test_two_headings_with_the_same_text_get_distinct_ids(): void
    {
        $post = BlogPost::factory()->create(['body' => ['fr' =>
            '<h2>Même titre</h2><p>Un.</p><h2>Même titre</h2><p>Deux.</p>',
        ]]);

        $headings = $post->annotatedBody()['headings'];

        $this->assertSame(['meme-titre', 'meme-titre-2'], array_column($headings, 'id'));

        $html = $this->get('/blog/'.$post->slug)->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h2 id="meme-titre">'));
        $this->assertSame(1, substr_count($html, '<h2 id="meme-titre-2">'));
        // Two links in the plan, one per heading, no id used twice.
        $this->assertSame(1, substr_count($html, 'href="#meme-titre"'));
        $this->assertSame(1, substr_count($html, 'href="#meme-titre-2"'));
    }

    public function test_a_heading_cannot_take_the_comments_anchor(): void
    {
        $post = BlogPost::factory()->create(['body' => ['fr' =>
            '<h2>Commentaires</h2><p>Un.</p><h2>Suite</h2><p>Deux.</p>',
        ]]);

        $this->assertSame(
            ['commentaires-2', 'suite'],
            array_column($post->annotatedBody()['headings'], 'id')
        );

        $html = $this->get('/blog/'.$post->slug)->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'id="commentaires"'));
    }

    public function test_one_heading_or_none_draws_no_plan(): void
    {
        $one = BlogPost::factory()->create(['body' => ['fr' => '<h2>Seule</h2><p>Un.</p>']]);
        $none = BlogPost::factory()->create(['body' => ['fr' => '<p>Un paragraphe et rien d\'autre.</p>']]);

        $this->get('/blog/'.$one->slug)->assertOk()
            ->assertDontSee('blog-article-plan')
            // The id is still there: a link from elsewhere can target it.
            ->assertSee('<h2 id="seule">', false);

        $this->get('/blog/'.$none->slug)->assertOk()
            ->assertDontSee('blog-article-plan');

        $this->assertSame([], $none->annotatedBody()['headings']);
    }

    public function test_an_empty_body_annotates_to_nothing(): void
    {
        $post = BlogPost::factory()->make(['body' => ['fr' => '   ']]);

        $this->assertSame(['html' => '   ', 'headings' => []], $post->annotatedBody());
    }

    public function test_comments_sit_between_the_sources_and_the_ask_aside(): void
    {
        $post = BlogPost::factory()->create([
            'body' => ['fr' => $this->bodyWithThreeHeadings()],
            'sources' => [['label' => 'Legifrance', 'url' => 'https://www.legifrance.gouv.fr/dossier']],
        ]);
        $post->products()->attach(Product::factory()->create(['is_active' => true])->id, ['sort_order' => 0]);
        // A second post in the same category: the related block renders too.
        BlogPost::factory()->create(['blog_category_id' => $post->blog_category_id]);

        $html = $this->get('/blog/'.$post->slug)->assertOk()->getContent();

        $body = strpos($html, 'class="blog-article-body"');
        $sources = strpos($html, 'blog-article-sources');
        $comments = strpos($html, 'id="commentaires"');
        $ask = strpos($html, 'blog-article-ask');
        $products = strpos($html, 'blog-article-products');
        $related = strpos($html, 'blog-article-related');

        foreach (compact('body', 'sources', 'comments', 'ask', 'products', 'related') as $name => $offset) {
            $this->assertNotFalse($offset, $name.' is missing from the page');
        }

        $this->assertLessThan($sources, $body);
        $this->assertLessThan($comments, $sources);
        $this->assertLessThan($ask, $comments);
        $this->assertLessThan($products, $ask);
        $this->assertLessThan($related, $products);
    }

    public function test_reading_time_still_counts_the_words_of_the_stored_body(): void
    {
        $post = BlogPost::factory()->make([
            'body' => ['fr' => '<h2>Titre</h2><p>'.implode(' ', array_fill(0, 401, 'mot')).'</p>'],
        ]);

        // 402 words, "Titre" included, at 200 a minute: three, ceiling taken.
        $this->assertSame(3, $post->readingMinutes());
        // Annotating the body does not change what is counted.
        $post->annotatedBody();
        $this->assertSame(3, $post->readingMinutes());

        $this->assertSame(1, BlogPost::factory()->make(['body' => ['fr' => '<p>Trois mots seulement.</p>']])->readingMinutes());
    }

    /** The plan nav's markup and the offset just past it. */
    private function planNav(string $html): array
    {
        $start = strpos($html, '<nav class="blog-article-plan"');
        $this->assertNotFalse($start, 'No plan nav on the page.');
        $end = strpos($html, '</nav>', $start);

        return [substr($html, $start, $end - $start), $end];
    }
}
