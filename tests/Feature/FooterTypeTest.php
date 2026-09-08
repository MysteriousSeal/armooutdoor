<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The bottom line of the footer and the legal links beside it are set at
 * one size, and arrive at one size on a phone too.
 */
class FooterTypeTest extends TestCase
{
    private function rule(string $selector): string
    {
        $css = file_get_contents(public_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/^'.preg_quote($selector, '/').'\s*\{/m',
            $css,
            $selector.' is gone.'
        );

        $start = preg_match('/^'.preg_quote($selector, '/').'\s*\{/m', $css, $m, PREG_OFFSET_CAPTURE)
            ? $m[0][1]
            : 0;

        return substr($css, $start, strpos($css, '}', $start) - $start);
    }

    public function test_the_copyright_line_matches_the_legal_links(): void
    {
        preg_match('/font-size:\s*([^;]+);/', $this->rule('.site-footer-copy'), $copy);
        preg_match('/font-size:\s*([^;]+);/', $this->rule('.site-footer-legal a'), $links);

        $this->assertNotEmpty($copy);
        $this->assertSame(trim($copy[1]), trim($links[1]));
    }

    public function test_the_copyright_line_declines_the_phone_text_boost(): void
    {
        $rule = $this->rule('.site-footer-copy');

        // Both browsers inflate a wide block of text on their own. This line
        // is one long paragraph and the links beside it are short items in a
        // flex row, so the paragraph was boosted and they were not, and the
        // two sizes above were equal everywhere except where it showed.
        $this->assertStringContainsString('-webkit-text-size-adjust: 100%', $rule);
        $this->assertStringContainsString('text-size-adjust: 100%', $rule);
    }
}
