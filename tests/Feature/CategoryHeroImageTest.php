<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The photograph beside a category's heading fills the panel it sits in.
 *
 * The hero is two columns, and the copy is the taller of them whenever the
 * title wraps: measured on the clothing category, the panel stood 330 pixels
 * tall and the picture 296, leaving seventeen pixels of white above it and
 * seventeen below. The picture is stretched to the row now, which is what
 * closes those bands.
 */
class CategoryHeroImageTest extends TestCase
{
    private function heroImageRule(): string
    {
        $css = file_get_contents(public_path('css/categories.css'));
        $selector = '.cat-hero.has-image .cat-hero-overlay';

        $this->assertNotFalse(
            strpos($css, $selector.' {'),
            $selector.' is gone, so the hero picture is styled somewhere else now.'
        );

        $start = strpos($css, $selector.' {');

        return substr($css, $start, strpos($css, '}', $start) - $start);
    }

    public function test_the_picture_fills_the_height_of_its_panel(): void
    {
        // Centred, the picture floated in a row the copy had made taller than
        // it, which is the whole of the bug.
        $this->assertMatchesRegularExpression(
            '/align-self:\s*stretch\s*;/',
            $this->heroImageRule(),
        );
    }

    public function test_the_picture_keeps_the_width_of_its_column(): void
    {
        // Stretched, the height is definite, and the ratio below works
        // backwards from it: without this the picture came out 765 pixels
        // wide in a 691 pixel column and the panel clipped the right of it.
        $this->assertMatchesRegularExpression(
            '/width:\s*100%\s*;/',
            $this->heroImageRule(),
        );
    }

    public function test_the_ratio_stays_as_the_floor(): void
    {
        // A row is never shorter than the item that sizes it, so the ratio
        // still governs wherever the picture is the taller column, and on a
        // phone where the two are stacked and there is nothing to stretch to.
        $this->assertMatchesRegularExpression(
            '/aspect-ratio:\s*21\s*\/\s*9\s*;/',
            $this->heroImageRule(),
        );
    }

    public function test_the_panel_still_lets_the_copy_sit_centred(): void
    {
        // Only the picture was told to stretch. Were the whole grid switched
        // to stretch instead, the copy would leave the middle of the panel
        // and ride at the top of it.
        $css = file_get_contents(public_path('css/categories.css'));
        $start = strpos($css, '.cat-hero.has-image {');
        $rule = substr($css, $start, strpos($css, '}', $start) - $start);

        $this->assertMatchesRegularExpression('/align-items:\s*center\s*;/', $rule);
    }
}
