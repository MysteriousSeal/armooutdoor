<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** La navigation de l'admin, regroupée en menus. */
class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function dashboard(): TestResponse
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/dashboard')
            ->assertOk();
    }

    public function test_every_section_is_still_reachable(): void
    {
        $content = $this->dashboard()->getContent();

        foreach ([
            '/admin/orders', '/admin/customers', '/admin/conversations', '/admin/discounts',
            '/admin/products', '/admin/categories', '/admin/purchase-orders', '/admin/marketplaces',
            '/admin/blog', '/admin/settings', '/admin/activity', '/admin/changelog',
        ] as $path) {
            $this->assertStringContainsString('href="'.url($path).'"', $content, $path.' a disparu de la navigation.');
        }
    }

    public function test_the_bar_holds_its_groups_and_no_more(): void
    {
        $content = $this->dashboard()->getContent();

        // Sales, Catalogue, Accounting and System for the owner.
        $this->assertSame(4, substr_count($content, 'data-nav-toggle'));
        $this->assertSame(4, substr_count($content, 'data-nav-menu'));
    }

    public function test_the_open_section_marks_its_group(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products')
            ->assertOk();

        // Le groupe replié doit dire où l'on est, sinon la barre n'indique plus rien.
        $this->assertMatchesRegularExpression(
            '#admin-nav-trigger active"[^>]*>\s*Catalogue#s',
            $response->getContent()
        );
        $this->assertMatchesRegularExpression(
            '#href="[^"]*/admin/products"[^>]*admin-nav-menu-item active#s',
            $response->getContent()
        );
    }

    public function test_a_group_carries_the_counts_of_what_it_hides(): void
    {
        // Deux clients jamais consultés : le compte vit sous « Sales ».
        User::factory()->count(2)->create(['admin_viewed_at' => null]);

        $content = $this->dashboard()->getContent();

        // Replié, un groupe doit encore signaler ce qui attend dedans.
        $this->assertStringContainsString('2 waiting in this section', $content);
    }
}
