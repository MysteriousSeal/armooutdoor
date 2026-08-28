<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileCategoryMenuScrollTest extends TestCase
{
    use RefreshDatabase;


    public function test_the_menu_stays_below_the_subheader_with_no_js_measurement(): void
    {
        // position:absolute + top:100% (set on the base .site-cat-menu rule,
        // relative to its sticky positioned ancestor #site-subheader) is
        // correct by construction in every browser, unlike the previous
        // fixed-position + JS-measured-pixel approach, which mobile Safari's
        // independently-collapsing address bar could throw off enough to
        // cover the toggle button with no way left to close the menu.
        $css = (string) file_get_contents(public_path('css/app.css'));

        $this->assertStringContainsString('.site-cat-menu {
    position: absolute;
    left: 0;
    right: 0;
    top: 100%;', $css);

        $this->assertStringContainsString('.site-cat-menu {
        max-height: 80vh;
        overflow-y: auto;', $css);
    }

    public function test_the_toggle_script_has_no_position_measurement_left(): void
    {
        $js = (string) file_get_contents(public_path('js/site-menu-toggle.js'));

        $this->assertStringNotContainsString('getBoundingClientRect', $js);
        $this->assertStringNotContainsString('cat-menu-top', $js);
        $this->assertStringNotContainsString('has-menu-open', $js);
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
