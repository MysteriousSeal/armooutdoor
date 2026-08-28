<?php

namespace Tests\Feature;

use Tests\TestCase;

class MobileCategoryMenuScrollTest extends TestCase
{
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
