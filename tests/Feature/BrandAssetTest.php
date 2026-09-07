<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The mark's source file, kept so the derived assets can be remade.
 */
class BrandAssetTest extends TestCase
{
    /** The three shapes every copy of the mark must draw. */
    private const SHAPES = [
        'M704 97l423 769H1013L704 304 395 866H281Z',
        'x="43" y="605" width="1322" height="92"',
        'cx="704" cy="1010.5" r="264.5"',
    ];

    public function test_the_source_is_kept_and_intact(): void
    {
        $path = resource_path('brand/armo-mark.svg');

        $this->assertFileExists($path);

        $svg = file_get_contents($path);

        // The three shapes, and the two inks everything else is derived from.
        $this->assertStringContainsString('M704 97l423 769H1013L704 304 395 866H281Z', $svg);
        $this->assertStringContainsString('#282828', $svg);
        $this->assertStringContainsString('#887868', $svg);
    }

    public function test_the_header_mark_still_draws_the_same_shapes(): void
    {
        $source = file_get_contents(resource_path('brand/armo-mark.svg'));
        $partial = file_get_contents(resource_path('views/partials/armo-mark.blade.php'));

        // The partial recolours the mark, it does not redraw it: if the
        // geometry drifts from the source, one of the two is stale.
        foreach (self::SHAPES as $shape) {
            $this->assertStringContainsString($shape, $source);
            $this->assertStringContainsString($shape, $partial);
        }
    }

    public function test_the_print_variant_draws_the_same_mark(): void
    {
        $print = file_get_contents(resource_path('brand/armo-mark-print.svg'));

        // Same three shapes and the same two inks: the print file differs
        // from the source only in its box, cropped to the drawing because
        // the PDF renderer sizes an offset viewBox wrongly.
        foreach (self::SHAPES as $shape) {
            $this->assertStringContainsString($shape, $print);
        }

        $this->assertStringContainsString('#282828', $print);
        $this->assertStringContainsString('#887868', $print);
        $this->assertStringContainsString('viewBox="43 97 1322 1210"', $print);
    }
}
