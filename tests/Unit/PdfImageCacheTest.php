<?php

namespace Tests\Unit;

use App\Support\PdfImageCache;
use Tests\TestCase;

/** Les images réduites pour l'impression. */
class PdfImageCacheTest extends TestCase
{
    private string $source = '';

    protected function setUp(): void
    {
        parent::setUp();

        $image = imagecreatetruecolor(1000, 1000);
        imagefill($image, 0, 0, imagecolorallocate($image, 40, 90, 140));
        $this->source = sys_get_temp_dir().'/pdf-image-cache-'.uniqid().'.png';
        imagepng($image, $this->source);
        imagedestroy($image);
    }

    protected function tearDown(): void
    {
        @unlink($this->source);

        parent::tearDown();
    }

    public function test_a_large_image_comes_back_small(): void
    {
        $cached = PdfImageCache::pathFor($this->source);

        $this->assertNotNull($cached);
        [$width, $height] = getimagesize($cached);
        $this->assertSame(PdfImageCache::SIZE, $width);
        $this->assertSame(PdfImageCache::SIZE, $height);
        $this->assertLessThan(filesize($this->source), filesize($cached));
    }

    public function test_the_second_call_reuses_the_file(): void
    {
        $first = PdfImageCache::pathFor($this->source);
        $stamp = filemtime($first);

        $this->assertSame($first, PdfImageCache::pathFor($this->source));
        $this->assertSame($stamp, filemtime($first));
    }

    public function test_a_replaced_image_gets_a_new_file(): void
    {
        $first = PdfImageCache::pathFor($this->source);

        // La clé porte la date du fichier source : une photo remplacée doit
        // regénérer la sienne, sans quoi le PDF garderait l'ancienne.
        touch($this->source, time() + 60);
        clearstatcache();

        $this->assertNotSame($first, PdfImageCache::pathFor($this->source));
    }

    public function test_a_small_image_is_not_blown_up(): void
    {
        $image = imagecreatetruecolor(24, 24);
        $small = sys_get_temp_dir().'/pdf-image-cache-small-'.uniqid().'.png';
        imagepng($image, $small);
        imagedestroy($image);

        [$width] = getimagesize((string) PdfImageCache::pathFor($small));

        $this->assertSame(24, $width);

        @unlink($small);
    }

    public function test_nothing_comes_back_for_a_missing_file(): void
    {
        $this->assertNull(PdfImageCache::pathFor(sys_get_temp_dir().'/parti-en-fumee.png'));
        $this->assertNull(PdfImageCache::pathFor(null));
        $this->assertNull(PdfImageCache::pathFor(''));
    }
}
