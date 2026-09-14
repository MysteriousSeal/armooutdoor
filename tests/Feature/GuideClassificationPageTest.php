<?php

namespace Tests\Feature;

use App\Support\Guides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The guide that places a weapon in category D, C, B or A.
 */
class GuideClassificationPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_hero_carries_its_picture_and_shares_it(): void
    {
        $html = $this->get('/guides/classer-son-arme')->assertOk()->getContent();
        $image = Guides::byRoute('guides.classification')['image'] ?? null;

        $this->assertNotNull($image);
        $this->assertFileExists(public_path($image));

        // The picture shown whole as a real image, described, loaded first,
        // and the same one on a shared link.
        $this->assertStringContainsString('cat-hero cat-hero--plate', $html);
        $this->assertStringContainsString('src="'.versioned_asset($image).'"', $html);
        $this->assertStringContainsString('alt="Illustration : quatre marches', $html);
        $this->assertStringContainsString('fetchpriority="high"', $html);
        $this->assertStringContainsString('<meta property="og:image" content="'.versioned_asset($image).'">', $html);
    }
}
