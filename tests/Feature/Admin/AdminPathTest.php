<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Le chemin du back-office se renomme par ADMIN_PATH, et la porte reste
 * fermée aux robots comme aux essais en rafale.
 */
class AdminPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_login_post_is_throttled(): void
    {
        $middleware = Route::getRoutes()->getByName('admin.login.store')->middleware();

        $this->assertContains('throttle:5,1', $middleware);
    }

    public function test_robots_names_the_admin_path_only_while_it_is_the_default(): void
    {
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /admin');

        // Un chemin renommé pour être introuvable ne s'imprime pas dans le
        // fichier que tout le monde lit en premier.
        config()->set('shop.admin_path', 'gestion-secrete');

        $response = $this->get('/robots.txt');
        $response->assertOk();
        $this->assertStringNotContainsString('admin', $response->getContent());
        $this->assertStringNotContainsString('gestion-secrete', $response->getContent());
    }

    public function test_admin_pages_carry_a_noindex_header_and_storefront_pages_do_not(): void
    {
        $this->get('/admin')->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->get('/')->assertOk()
            ->assertHeaderMissing('X-Robots-Tag');
    }
}
