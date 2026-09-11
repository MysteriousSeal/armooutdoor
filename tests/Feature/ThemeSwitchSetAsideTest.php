<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The storefront's light/dark switch, set aside behind shop.theme_switch.
 */
class ThemeSwitchSetAsideTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_storefront_shows_no_theme_switch_by_default(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('theme-toggle-btn', $html);
        $this->assertStringNotContainsString('js/theme-toggle.js', $html);
    }

    public function test_a_visitor_who_chose_dark_before_gets_the_light_theme(): void
    {
        $html = $this->withCookie('theme', 'dark')->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<html[^>]*data-theme="light"/', $html);
        // Nor can the early script put the dark theme back from the cookie.
        $this->assertStringNotContainsString('document.cookie.match(/(?:^|; )theme=', $html);
    }

    public function test_turning_the_switch_back_on_restores_it(): void
    {
        config(['shop.theme_switch' => true]);

        $html = $this->withCookie('theme', 'dark')->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('id="theme-toggle"', $html);
        $this->assertStringContainsString('theme-toggle-btn--subheader-mobile', $html);
        $this->assertStringContainsString('js/theme-toggle.js', $html);
        $this->assertMatchesRegularExpression('/<html[^>]*data-theme="dark"/', $html);
    }

    public function test_the_back_office_keeps_its_own_switch(): void
    {
        $html = $this->withCookie('theme', 'dark')->get(route('admin.login'))->assertOk()->getContent();

        $this->assertStringContainsString('theme-toggle-btn', $html);
        $this->assertMatchesRegularExpression('/<html[^>]*data-theme="dark"/', $html);
    }
}
