<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileCategoryMenuScrollTest extends TestCase
{
    use RefreshDatabase;


    public function test_the_menu_is_pinned_to_the_viewport_and_scrollable_on_mobile(): void
    {
        $css = (string) file_get_contents(public_path('css/app.css'));

        $this->assertStringContainsString('.site-cat-menu {
        position: fixed;
        top: var(--cat-menu-top, 4.5rem);
        bottom: 0;
        overflow-y: auto;', $css);
    }

    public function test_the_toggle_script_locks_body_scroll_while_open(): void
    {
        $js = (string) file_get_contents(public_path('js/site-menu-toggle.js'));

        $this->assertStringContainsString("document.body.classList.toggle('has-menu-open', isOpen);", $js);
    }

    public function test_the_toggle_script_measures_the_subheader_before_opening(): void
    {
        $js = (string) file_get_contents(public_path('js/site-menu-toggle.js'));

        $this->assertStringContainsString("subheader.getBoundingClientRect().bottom", $js);
        $this->assertStringContainsString("panel.style.setProperty('--cat-menu-top'", $js);
    }

    public function test_contact_link_shows_an_icon_instead_of_its_label_on_mobile(): void
    {
        $html = $this->get('/')->getContent();
        $css = (string) file_get_contents(public_path('css/app.css'));

        $this->assertStringContainsString('sort-tab--contact', $html);
        $this->assertStringContainsString('sort-tab-icon', $html);
        $this->assertStringContainsString('sort-tab-label', $html);

        $this->assertStringContainsString('.sort-tab--contact .sort-tab-icon {
        display: inline-flex;', $css);
        $this->assertStringContainsString('.sort-tab--contact .sort-tab-label {
        display: none;', $css);
    }

    public function test_menu_has_a_dedicated_close_button_independent_of_the_toggle(): void
    {
        $html = $this->get('/')->getContent();
        $css = (string) file_get_contents(public_path('css/app.css'));
        $js = (string) file_get_contents(public_path('js/site-menu-toggle.js'));

        $this->assertStringContainsString('id="site-cat-menu-close"', $html);

        // Fixed to the viewport rather than the scrolling panel, so it
        // stays reachable even if the panel mispositions itself and covers
        // the hamburger button (the iOS Safari bug this guards against).
        $rule = \Illuminate\Support\Str::before(
            \Illuminate\Support\Str::after($css, 'html.is-safari-ios .site-cat-menu-close {
        display: inline-flex;'),
            '}'
        );
        $this->assertStringContainsString('position: fixed;', $rule);

        $this->assertStringContainsString("getElementById('site-cat-menu-close')", $js);
        $this->assertStringContainsString('closeBtn.addEventListener', $js);
    }

    public function test_close_button_is_only_shown_to_ios_safari(): void
    {
        $html = $this->get('/')->getContent();
        $css = (string) file_get_contents(public_path('css/app.css'));

        // Base rule: hidden for every browser by default.
        $this->assertStringContainsString('.site-cat-menu-close {
    display: none;
}', $css);

        // The mobile media query only reveals it under html.is-safari-ios,
        // a class set in the <head> after sniffing the user agent — the
        // one thing CSS media queries can't do on their own.
        $this->assertStringContainsString('html.is-safari-ios .site-cat-menu-close {
        display: inline-flex;', $css);

        $this->assertStringContainsString("classList.add('is-safari-ios')", $html);
        $this->assertStringContainsString('/iPad|iPhone|iPod/', $html);
        $this->assertStringContainsString('CriOS|FxiOS|EdgiOS|OPiOS', $html);
    }

    public function test_menu_position_is_remeasured_next_frame(): void
    {
        $js = (string) file_get_contents(public_path('js/site-menu-toggle.js'));

        $this->assertStringContainsString('requestAnimationFrame(measureTop)', $js);
    }

    public function test_blog_and_contact_align_to_the_far_right_on_mobile(): void
    {
        $css = (string) file_get_contents(public_path('css/app.css'));

        // The 768px block sets justify-content: flex-start on .sort-tabs; this
        // rule must come after it in the file to actually win at <=640px.
        $lastFlexStart = strrpos($css, "justify-content: flex-start;\n        flex-wrap: wrap;");
        $flexEndRulePos = strpos($css, ".sort-tabs {\n        justify-content: flex-end;");

        $this->assertNotFalse($lastFlexStart);
        $this->assertNotFalse($flexEndRulePos);
        $this->assertGreaterThan($lastFlexStart, $flexEndRulePos);
    }
}
