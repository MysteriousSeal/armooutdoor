<?php

namespace Tests\Feature\Blog;

use Tests\TestCase;

/**
 * The article banner carries the picture at the shape the picture is.
 */
class BlogHeroRatioTest extends TestCase
{
    private function css(): string
    {
        return file_get_contents(public_path('css/blog.css'));
    }

    public function test_the_banner_takes_the_pictures_own_proportion(): void
    {
        $css = $this->css();

        // A fixed height cut the top and bottom off every hero. The picture
        // has a box of its own now, at the shape of the file, so cover
        // crops nothing.
        $this->assertMatchesRegularExpression(
            '/\.blog-article-banner\.has-image \.blog-article-banner-overlay\s*\{[^}]*aspect-ratio:\s*16\s*\/\s*9/s',
            $css
        );

        // And the banner stops forcing a height of its own, or the picture
        // would be stretched to fill whatever was left.
        $this->assertMatchesRegularExpression(
            '/\.blog-article-banner\.has-image\s*\{[^}]*min-height:\s*0/s',
            $css
        );

        // The copy is beside the picture rather than over it, which is what
        // keeps the banner down to the height of one column.
        $this->assertMatchesRegularExpression(
            '/\.blog-article-banner\.has-image\s*\{[^}]*grid-template-columns/s',
            $css
        );
    }

    public function test_a_banner_without_a_picture_keeps_a_height(): void
    {
        // The ratio belongs to the picture. A banner with none still has to
        // be tall enough to hold a title.
        $this->assertMatchesRegularExpression(
            '/\.blog-article-banner\s*\{[^}]*min-height:\s*22rem/s',
            $this->css()
        );
    }

    public function test_every_hero_on_disk_is_the_shape_the_banner_expects(): void
    {
        $files = glob(public_path('images/blog/*.webp'));

        $this->assertNotEmpty($files, 'No blog heroes to check.');

        foreach ($files as $file) {
            [$width, $height] = getimagesize($file);

            // Anything else and the banner starts cropping again, quietly.
            $this->assertEqualsWithDelta(
                16 / 9,
                $width / $height,
                0.01,
                basename($file).' is '.$width.'x'.$height.', which the 16:9 banner will crop.'
            );
        }
    }
}
