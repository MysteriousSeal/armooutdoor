<?php

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_it_keeps_allowed_tags(): void
    {
        $clean = HtmlSanitizer::clean('<p>Hello <strong>world</strong></p>');

        $this->assertSame('<p>Hello <strong>world</strong></p>', $clean);
    }

    public function test_it_strips_script_tags_entirely(): void
    {
        $clean = HtmlSanitizer::clean('<p>Hi</p><script>alert(1)</script>');

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('alert', $clean);
    }

    public function test_it_removes_javascript_urls_from_links(): void
    {
        $clean = HtmlSanitizer::clean('<a href="javascript:alert(1)">click</a>');

        $this->assertStringNotContainsString('javascript:', $clean);
    }

    public function test_it_removes_data_urls_from_links(): void
    {
        $clean = HtmlSanitizer::clean('<a href="data:text/html,evil">click</a>');

        $this->assertStringNotContainsString('data:', $clean);
    }

    public function test_it_keeps_safe_http_links_and_marks_them_noopener(): void
    {
        $clean = HtmlSanitizer::clean('<a href="https://example.com" target="_blank">link</a>');

        $this->assertStringContainsString('href="https://example.com"', $clean);
        $this->assertStringContainsString('rel="noopener noreferrer nofollow"', $clean);
    }

    public function test_it_unwraps_disallowed_tags_but_keeps_their_text(): void
    {
        $clean = HtmlSanitizer::clean('<table><tr><td>Kept text</td></tr></table>');

        $this->assertStringNotContainsString('<table', $clean);
        $this->assertStringContainsString('Kept text', $clean);
    }

    public function test_it_strips_disallowed_attributes(): void
    {
        $clean = HtmlSanitizer::clean('<p onclick="evil()" style="color:red">Text</p>');

        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('style', $clean);
    }

    public function test_it_removes_comments(): void
    {
        $clean = HtmlSanitizer::clean('<p>Text</p><!-- a comment -->');

        $this->assertStringNotContainsString('<!--', $clean ?? '');
    }

    public function test_empty_or_whitespace_only_html_returns_null(): void
    {
        $this->assertNull(HtmlSanitizer::clean(''));
        $this->assertNull(HtmlSanitizer::clean('   '));
        $this->assertNull(HtmlSanitizer::clean('<p>  </p>'));
        $this->assertNull(HtmlSanitizer::clean(null));
    }

    public function test_to_plain_text_strips_all_tags(): void
    {
        $this->assertSame('Hello world', HtmlSanitizer::toPlainText('<p>Hello <strong>world</strong></p>'));
    }

    public function test_to_plain_text_collapses_whitespace_and_nbsp(): void
    {
        $this->assertSame('Hello world', HtmlSanitizer::toPlainText("Hello\u{A0}\u{A0}world"));
    }

    public function test_is_empty_detects_html_with_no_visible_text(): void
    {
        $this->assertTrue(HtmlSanitizer::isEmpty('<p><br></p>'));
        $this->assertFalse(HtmlSanitizer::isEmpty('<p>Text</p>'));
    }

    public function test_for_display_removes_empty_anchor_and_span_tags(): void
    {
        $display = HtmlSanitizer::forDisplay('<p>Text</p><a href="https://x.com"></a><span class="ql-x"></span>');

        $this->assertStringNotContainsString('<a', $display);
        $this->assertStringNotContainsString('<span', $display);
        $this->assertStringContainsString('Text', $display);
    }

    public function test_for_display_on_empty_input_returns_empty_string(): void
    {
        $this->assertSame('', HtmlSanitizer::forDisplay(null));
        $this->assertSame('', HtmlSanitizer::forDisplay(''));
    }
}
