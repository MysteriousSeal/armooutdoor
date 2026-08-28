<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeaderMobileNavTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_carries_mobile_only_shortcuts_inside_the_category_menu(): void
    {
        $response = $this->get('/')->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('sort-tab--in-menu-on-mobile', $html);
        $this->assertStringContainsString('site-cat-menu-shortcuts', $html);

        $shortcuts = \Illuminate\Support\Str::before(
            \Illuminate\Support\Str::after($html, 'site-cat-menu-shortcuts'),
            '</nav>'
        );

        $this->assertStringContainsString(route('products.new-arrivals'), $shortcuts);
        $this->assertStringContainsString(route('products.promotions'), $shortcuts);
        $this->assertStringContainsString(route('products.best-sellers'), $shortcuts);
        $this->assertStringContainsString(route('blog.index'), $shortcuts);
    }

    public function test_blog_moved_into_the_menu_and_is_hidden_next_to_the_hamburger_on_mobile(): void
    {
        $html = $this->get('/')->getContent();

        $subheaderNav = \Illuminate\Support\Str::before(
            \Illuminate\Support\Str::after($html, 'class="sort-tabs"'),
            '</nav>'
        );
        $blogLink = \Illuminate\Support\Str::before(
            \Illuminate\Support\Str::after($subheaderNav, route('blog.index')),
            '</a>'
        );

        $this->assertStringContainsString('sort-tab--in-menu-on-mobile', $blogLink);
    }

    public function test_cart_button_sits_left_of_the_contact_icon_on_mobile(): void
    {
        $html = $this->get('/')->getContent();

        $subheaderNav = \Illuminate\Support\Str::before(
            \Illuminate\Support\Str::after($html, 'class="sort-tabs"'),
            '</nav>'
        );

        $cartPos = strpos($subheaderNav, 'cart-btn--subheader-mobile');
        $contactPos = strpos($subheaderNav, 'sort-tab--contact');

        $this->assertNotFalse($cartPos);
        $this->assertNotFalse($contactPos);
        $this->assertLessThan($contactPos, $cartPos);
    }

    public function test_top_header_cart_button_is_hidden_below_640px(): void
    {
        $css = (string) file_get_contents(public_path('css/app.css'));

        // The 768px block shows .cart-btn--header; this rule must come after
        // it in the file to actually win at <=640px.
        $showRulePos = strpos($css, ".cart-btn--header {\n        display: inline-flex;");
        $hideRulePos = strrpos($css, ".cart-btn--header {\n        display: none;\n    }");

        $this->assertNotFalse($showRulePos);
        $this->assertNotFalse($hideRulePos);
        $this->assertGreaterThan($showRulePos, $hideRulePos);
    }

    public function test_subheader_mobile_cart_button_is_hidden_by_default_on_desktop(): void
    {
        $css = (string) file_get_contents(public_path('css/app.css'));

        // .cart-btn's own "display: inline-flex;" must come before this rule
        // in the file, or it wins instead (equal specificity, later wins).
        $cartBtnBasePos = strpos($css, ".cart-btn {\n    position: relative;\n    display: inline-flex;");
        $hideBasePos = strpos($css, ".cart-btn--subheader-mobile {\n    display: none;\n}");

        $this->assertNotFalse($cartBtnBasePos);
        $this->assertNotFalse($hideBasePos);
        $this->assertGreaterThan($cartBtnBasePos, $hideBasePos);
    }

    public function test_theme_toggle_sits_right_of_the_contact_icon_on_mobile(): void
    {
        $html = $this->get('/')->getContent();

        $subheaderNav = \Illuminate\Support\Str::before(
            \Illuminate\Support\Str::after($html, 'class="sort-tabs"'),
            '</nav>'
        );

        $this->assertStringContainsString('theme-toggle-btn--subheader-mobile', $subheaderNav);

        $contactPos = strpos($subheaderNav, 'sort-tab--contact');
        $themeTogglePos = strpos($subheaderNav, 'theme-toggle-btn--subheader-mobile');

        $this->assertNotFalse($contactPos);
        $this->assertNotFalse($themeTogglePos);
        $this->assertGreaterThan($contactPos, $themeTogglePos);
    }

    public function test_theme_toggle_is_hidden_by_default_on_desktop(): void
    {
        $css = (string) file_get_contents(public_path('css/app.css'));

        // base.css (which sets .theme-toggle-btn's own "display") loads
        // before app.css, so this hide rule wins regardless of its position
        // within app.css — but it still needs to exist at all.
        $this->assertStringContainsString('.theme-toggle-btn--subheader-mobile {
    display: none;
}', $css);
    }

    public function test_theme_toggle_script_supports_more_than_one_button(): void
    {
        $js = (string) file_get_contents(public_path('js/theme-toggle.js'));

        $this->assertStringContainsString("document.querySelectorAll('.theme-toggle-btn')", $js);
    }

    public function test_original_theme_toggle_is_hidden_below_640px(): void
    {
        $css = (string) file_get_contents(public_path('css/app.css'));

        $this->assertStringContainsString('.theme-toggle-btn:not(.theme-toggle-btn--subheader-mobile) {
        display: none;
    }', $css);
    }

    public function test_shortcuts_sit_above_the_category_groups(): void
    {
        $html = $this->get('/')->getContent();

        $shortcutsPos = strpos($html, 'site-cat-menu-shortcuts');
        $categoriesPos = strpos($html, 'site-cat-menu-inner');

        $this->assertNotFalse($shortcutsPos);
        $this->assertNotFalse($categoriesPos);
        $this->assertLessThan($categoriesPos, $shortcutsPos);
    }

    public function test_shortcuts_lay_out_two_per_row_on_mobile(): void
    {
        $css = (string) file_get_contents(public_path('css/app.css'));

        $rule = \Illuminate\Support\Str::before(
            \Illuminate\Support\Str::after($css, '.site-cat-menu-shortcuts {'),
            '}'
        );

        $this->assertStringContainsString('grid-template-columns: repeat(2, minmax(0, 1fr));', $rule);
    }
}
