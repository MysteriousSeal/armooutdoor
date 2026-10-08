<?php

namespace Tests\Unit;

use App\Support\ImageThumbnailer;
use Tests\TestCase;

/** The square a product photo is normalized to. */
class ImageThumbnailerMainSizeTest extends TestCase
{
    /** @var list<string> */
    private array $created = [];

    protected function tearDown(): void
    {
        foreach ($this->created as $path) {
            @unlink(public_path('images/'.$path));
        }

        parent::tearDown();
    }

    private function image(int $width, int $height, string $extension = 'png'): string
    {
        $relative = 'products/main-size-test-'.uniqid().'.'.$extension;
        $image = imagecreatetruecolor($width, $height);
        $extension === 'webp'
            ? imagewebp($image, public_path('images/'.$relative))
            : imagepng($image, public_path('images/'.$relative));
        imagedestroy($image);
        $this->created[] = $relative;

        return $relative;
    }

    private function normalizedSize(string $relative): array
    {
        $path = ImageThumbnailer::normalizeMain($relative);
        $this->created[] = $path;

        return array_slice(getimagesize(public_path('images/'.$path)), 0, 2);
    }

    public function test_a_large_photo_is_capped_above_googles_high_resolution_line(): void
    {
        $this->assertSame([1600, 1600], $this->normalizedSize($this->image(3000, 2000)));
    }

    public function test_a_mid_size_photo_keeps_its_own_resolution(): void
    {
        $this->assertSame([1200, 1200], $this->normalizedSize($this->image(1200, 900)));
    }

    public function test_a_small_photo_is_still_brought_up_to_the_old_floor(): void
    {
        $this->assertSame([1000, 1000], $this->normalizedSize($this->image(600, 400)));
    }

    public function test_an_existing_1000_square_is_not_upscaled(): void
    {
        $relative = $this->image(1000, 1000, 'webp');

        $this->assertSame($relative, ImageThumbnailer::normalizeMain($relative));
        $this->assertSame([1000, 1000], array_slice(getimagesize(public_path('images/'.$relative)), 0, 2));
    }
}
