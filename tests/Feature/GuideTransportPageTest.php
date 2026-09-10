<?php

namespace Tests\Feature;

use App\Support\Guides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The guide on carrying a weapon and its ammunition between home, the club
 * and the field: the one text that actually decides (R315-4), and the
 * shipping rule the web mistakes for a car-trip rule.
 */
class GuideTransportPageTest extends TestCase
{
    use RefreshDatabase;

    private function guide(): string
    {
        $html = $this->get('/guides/transporter-son-arme')->assertOk()->getContent();

        preg_match('/<main.*?<\/main>/s', $html, $main);

        return $main[0] ?? '';
    }

    public function test_the_page_answers_the_question_it_is_named_after(): void
    {
        $guide = $this->guide();

        foreach (['motif légitime', 'immédiatement utilisable', 'R315-1', 'R315-4'] as $expected) {
            $this->assertStringContainsString($expected, $guide);
        }
    }

    public function test_it_names_the_texts_that_actually_govern(): void
    {
        $guide = $this->guide();

        foreach (['R315-1', 'R315-2', 'R315-3', 'R315-4', 'R315-12', 'R315-13', '222-54'] as $article) {
            $this->assertStringContainsString($article, $guide);
        }
    }

    public function test_it_corrects_the_confusion_on_the_two_envois(): void
    {
        $guide = $this->guide();

        // R315-13's two 24-hour-apart shipments govern expéditions to a
        // carrier, not a personal car trip to the club, a mix-up common
        // enough on the French web to earn its own myth card.
        $this->assertStringContainsString('deux envois séparés de 24 heures', $guide);
        $this->assertStringContainsString('R315-12', $guide);
        $this->assertStringContainsString('transporteur', $guide);
        $this->assertSame(1, substr_count($guide, 'glab-myth-claim'));
    }

    public function test_it_says_what_r315_4_does_not_say(): void
    {
        $guide = $this->guide();

        // The article covers the weapon, not the ammunition. The
        // separation habit comes from club rules and insurance, not the law.
        $this->assertStringContainsString('Ce que R315-4 ne dit pas', $guide);
        $this->assertSame(2, substr_count($guide, 'class="glab-warning"'));
    }

    public function test_it_says_where_each_category_stands(): void
    {
        $guide = $this->guide();

        foreach (['Catégorie D', 'Catégorie C', 'Catégorie B'] as $category) {
            $this->assertStringContainsString($category, $guide);
        }

        $this->assertStringContainsString('permis de chasser', $guide);
        $this->assertStringContainsString('licence de tir sportif', $guide);
    }

    public function test_it_shows_its_receipts(): void
    {
        $html = $this->get('/guides/transporter-son-arme')->assertOk()->getContent();

        $this->assertSame(7, substr_count($html, 'glab-source-num'));
        $this->assertSame(7, substr_count($html, 'rel="noopener nofollow"'));
        $this->assertStringContainsString('legifrance.gouv.fr', $html);
        $this->assertStringContainsString('"citation"', $html);
    }

    public function test_it_recommends_the_categories_it_names(): void
    {
        $guide = $this->guide();

        foreach (['poches-etuis', 'etuis-a-munitions', 'boites-munitions'] as $category) {
            $this->assertStringContainsString(route('categories.show', $category), $guide);
        }
    }

    public function test_it_does_not_re_explain_what_the_other_guides_own(): void
    {
        $guide = $this->guide();

        // « Classer son arme » owns the categories and the joule
        // thresholds; « Où tirer légalement » owns the place. This page
        // owns the transport, and links out for the rest.
        foreach (['2 à 20 joules', 'supérieure ou égale à 20 joules', 'compte SIA', 'R. 312-40', 'L. 422-10', '150 mètres'] as $owned) {
            $this->assertStringNotContainsString($owned, $guide);
        }

        $this->assertStringContainsString(route('guides.classification'), $guide);
    }

    public function test_the_page_declares_itself_to_search_engines(): void
    {
        $html = $this->get('/guides/transporter-son-arme')->assertOk()->getContent();

        foreach (['"@type":"Article"', '"@type":"FAQPage"', '"@type":"BreadcrumbList"'] as $type) {
            $this->assertStringContainsString($type, $html);
        }

        $this->assertStringContainsString('<link rel="canonical" href="'.route('guides.transport').'">', $html);
        $this->assertStringContainsString('<meta property="og:type" content="article">', $html);
    }

    public function test_the_guide_joins_the_shelf_the_index_and_the_sitemap(): void
    {
        $this->assertContains(route('guides.transport'), array_column(Guides::all(), 'url'));

        $this->get('/guides')->assertOk()->assertSee(route('guides.transport'), false);
        $this->get('/sitemap-guides.xml')->assertOk()->assertSee(route('guides.transport'), false);
    }
}
