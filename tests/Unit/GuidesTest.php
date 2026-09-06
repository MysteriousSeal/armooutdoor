<?php

namespace Tests\Unit;

use App\Support\Guides;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The shop's shelf of guides, and which two of them the home page offers.
 */
class GuidesTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function topicsOn(string $date): string
    {
        Carbon::setTestNow($date.' 12:00:00');

        return Guides::ofTheDay()->pluck('topic')->implode(', ');
    }

    public function test_every_guide_is_named_priced_and_linked(): void
    {
        foreach (Guides::all() as $guide) {
            foreach (['topic', 'title', 'url', 'teaser', 'summary'] as $field) {
                $this->assertNotSame('', $guide[$field]);
            }

            $this->assertStringStartsWith(url('/guides/'), $guide['url']);
            // The home card's text is the short one.
            $this->assertLessThan(mb_strlen($guide['summary']), mb_strlen($guide['teaser']));
        }
    }

    public function test_the_pair_changes_every_day(): void
    {
        $seen = [];

        for ($day = 0; $day < 6; $day++) {
            $topics = $this->topicsOn(Carbon::parse('2026-09-06')->addDays($day)->toDateString());

            $this->assertNotSame(end($seen) ?: null, $topics);

            $seen[] = $topics;
        }
    }

    public function test_a_day_shows_every_visitor_the_same_pair(): void
    {
        // Both of these are the sixth of September in Paris.
        Carbon::setTestNow('2026-09-06 06:00:00');
        $morning = Guides::ofTheDay()->pluck('topic')->implode(', ');

        Carbon::setTestNow('2026-09-06 21:00:00');

        $this->assertSame($morning, Guides::ofTheDay()->pluck('topic')->implode(', '));
    }

    public function test_the_window_turns_over_at_french_midnight(): void
    {
        // Half past midnight in Paris, still the sixth by the clock the app
        // runs on: the shop's readers have already turned the page.
        Carbon::setTestNow('2026-09-06 21:00:00');
        $before = Guides::ofTheDay()->pluck('topic')->implode(', ');

        Carbon::setTestNow('2026-09-06 22:30:00');

        $this->assertNotSame($before, Guides::ofTheDay()->pluck('topic')->implode(', '));
    }

    public function test_every_guide_comes_up_within_a_cycle(): void
    {
        $shown = [];

        foreach (range(0, count(Guides::all()) - 1) as $day) {
            $date = Carbon::parse('2026-09-06')->addDays($day)->toDateString();

            $shown = array_merge($shown, explode(', ', $this->topicsOn($date)));
        }

        $this->assertEqualsCanonicalizing(
            array_column(Guides::all(), 'topic'),
            array_values(array_unique($shown)),
        );
    }

    public function test_asking_for_more_than_the_shelf_holds_returns_the_shelf(): void
    {
        $this->assertCount(count(Guides::all()), Guides::ofTheDay(99));
    }
}
