<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * The cache-busting stamp behind every stylesheet and script tag.
 *
 * What matters: a file that changed gets a new stamp, a file that did not
 * keeps the old one, and nothing ever goes out without a stamp at all.
 */
class VersionedAssetTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        parent::setUp();

        $this->file = public_path('css/versioned-asset-test.css');
        file_put_contents($this->file, '/* fixture */');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);

        parent::tearDown();
    }

    public function test_the_stamp_is_the_file_own_timestamp(): void
    {
        touch($this->file, 1_700_000_000);
        clearstatcache(true, $this->file);

        $this->assertStringEndsWith(
            '?v=1700000000',
            versioned_asset('css/versioned-asset-test.css')
        );
    }

    public function test_editing_the_file_changes_the_stamp(): void
    {
        touch($this->file, 1_700_000_000);
        clearstatcache(true, $this->file);
        $before = versioned_asset('css/versioned-asset-test.css');

        // A visitor holding the old copy has to be sent to a new URL, or the
        // fix never reaches them.
        touch($this->file, 1_800_000_000);
        clearstatcache(true, $this->file);

        $this->assertNotSame($before, versioned_asset('css/versioned-asset-test.css'));
    }

    public function test_an_untouched_file_keeps_its_stamp(): void
    {
        // The point of stamping per file: shipping one fix must not throw
        // every other asset out of every visitor's cache.
        $first = versioned_asset('css/base.css');
        $second = versioned_asset('css/base.css');

        $this->assertSame($first, $second);
        $this->assertNotSame(
            versioned_asset('css/base.css'),
            versioned_asset('css/versioned-asset-test.css')
        );
    }

    public function test_a_missing_file_falls_back_to_the_site_version(): void
    {
        // Never worse than the old behaviour, and never an empty stamp.
        $this->assertStringEndsWith(
            '?v='.config('shop.version'),
            versioned_asset('css/does-not-exist.css')
        );
    }

    public function test_a_leading_slash_still_resolves(): void
    {
        touch($this->file, 1_700_000_000);
        clearstatcache(true, $this->file);

        $this->assertStringEndsWith(
            '?v=1700000000',
            versioned_asset('/css/versioned-asset-test.css')
        );
    }
}
