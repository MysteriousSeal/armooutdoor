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
}
