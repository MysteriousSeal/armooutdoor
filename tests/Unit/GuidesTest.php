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

    /** @return list<string> */
    private function titlesAt(string $moment): array
    {
        Carbon::setTestNow($moment);

        return Guides::ofTheHour()->pluck('title')->all();
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

    public function test_the_pair_is_two_different_guides(): void
    {
        $titles = $this->titlesAt('2026-09-14 14:10:00');

        $this->assertCount(2, $titles);
        $this->assertCount(2, array_unique($titles));
    }

    public function test_an_hour_shows_every_visitor_the_same_pair(): void
    {
        $this->assertSame(
            $this->titlesAt('2026-09-14 14:02:00'),
            $this->titlesAt('2026-09-14 14:58:00'),
        );
    }

    public function test_the_pair_is_drawn_again_on_the_hour(): void
    {
        $this->assertNotSame(
            $this->titlesAt('2026-09-14 14:59:00'),
            $this->titlesAt('2026-09-14 15:01:00'),
        );
    }

    public function test_a_day_brings_many_different_pairs_not_a_sliding_window(): void
    {
        $pairs = [];
        $slides = 0;
        $order = array_column(Guides::all(), 'title');

        for ($hour = 0; $hour < 24; $hour++) {
            $titles = $this->titlesAt(Carbon::parse('2026-09-14 00:30:00')->addHours($hour)->toDateTimeString());
            $pairs[] = implode(' | ', $titles);

            // A daily-style window shows two guides that neighbour each other
            // on the shelf; a draw only does so now and then.
            $positions = array_map(fn (string $title): int => array_search($title, $order, true), $titles);
            $slides += abs($positions[0] - $positions[1]) === 1 ? 1 : 0;
        }

        $this->assertGreaterThanOrEqual(12, count(array_unique($pairs)));
        $this->assertLessThan(12, $slides);
    }

    public function test_every_guide_comes_up_within_two_days(): void
    {
        $shown = [];

        for ($hour = 0; $hour < 48; $hour++) {
            $shown = array_merge($shown, $this->titlesAt(Carbon::parse('2026-09-14 00:30:00')->addHours($hour)->toDateTimeString()));
        }

        // Compared on the title, which is unique: two guides may well cover
        // the same rayon.
        $this->assertEqualsCanonicalizing(
            array_column(Guides::all(), 'title'),
            array_values(array_unique($shown)),
        );
    }

    public function test_asking_for_more_than_the_shelf_holds_returns_the_shelf(): void
    {
        $this->assertCount(count(Guides::all()), Guides::ofTheHour(99));
    }
}
