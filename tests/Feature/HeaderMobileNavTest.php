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
