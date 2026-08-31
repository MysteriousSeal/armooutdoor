<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Everything inside the hero copy card starts at the same left edge.
 *
 * The mirrored panel moves the card itself; it must not re-align the items
 * within it. A `margin-left: auto` on the paragraph used to push it right,
 * leaving it visibly out of step with the kicker, the title and the buttons.
 */
class HomeHeroCopyAlignmentTest extends TestCase
{
    private function css(): string
    {
        return file_get_contents(__DIR__.'/../../public/css/home.css');
    }

    public function test_the_mirrored_paragraph_is_not_pushed_off_the_left_edge(): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/\.home-hero--mirrored\s+\.home-hero-text\s*\{[^}]*margin-left:\s*auto/',
            $this->css(),
            'the mirrored paragraph must stay flush with the rest of the card'
        );
    }

    public function test_the_paragraph_keeps_a_readable_measure(): void
    {
        // Dropping the indent should not turn into dropping the line-length
        // cap: full-width prose in the card would read worse, not better.
        $this->assertMatchesRegularExpression(
            '/\.home\s+\.home-hero-text\s*\{[^}]*max-width:/',
            $this->css()
        );
    }
}
