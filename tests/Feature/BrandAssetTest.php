<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The mark's source file, kept so the derived assets can be remade.
 */
class BrandAssetTest extends TestCase
{
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
        foreach (['M704 97l423 769H1013L704 304 395 866H281Z', 'x="43" y="605" width="1322" height="92"', 'cx="704" cy="1010.5" r="264.5"'] as $shape) {
            $this->assertStringContainsString($shape, $source);
            $this->assertStringContainsString($shape, $partial);
        }
    }
}
