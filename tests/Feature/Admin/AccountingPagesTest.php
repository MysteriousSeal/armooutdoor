<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Les deux pages de comptabilité, encore vides. */
class AccountingPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_reaches_both_pages(): void
    {
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)->get('/admin/accounting/sales')
            ->assertOk()
            ->assertSee('Sales')
            ->assertSee('Accounting')
            ->assertSee('Nothing here yet.');

        $this->actingAs($owner)->get('/admin/accounting/purchases')
            ->assertOk()
            ->assertSee('Purchases')
            ->assertSee('Nothing here yet.');
    }

    public function test_a_staff_admin_is_turned_away(): void
    {
        $staff = User::factory()->staffAdmin()->create();

        // Ces pages porteront le chiffre d'affaires et les coûts : même
        // porte que le reste de ce qui touche à l'argent.
        $this->actingAs($staff)->get('/admin/accounting/sales')->assertForbidden();
        $this->actingAs($staff)->get('/admin/accounting/purchases')->assertForbidden();
    }

    public function test_the_menu_shows_only_for_the_owner(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Accounting')
            ->assertSee('/admin/accounting/sales', false)
            ->assertSee('/admin/accounting/purchases', false);

        $this->actingAs(User::factory()->staffAdmin()->create())
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertDontSee('Accounting')
            ->assertDontSee('/admin/accounting/sales', false);
    }

    public function test_the_open_page_marks_its_group_and_its_item(): void
    {
        $content = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/purchases')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('#admin-nav-trigger active"[^>]*>\s*Accounting#s', $content);
        $this->assertMatchesRegularExpression('#/admin/accounting/purchases"[^>]*admin-nav-menu-item active#s', $content);
    }

    public function test_accounting_opens_the_right_hand_block_just_left_of_system(): void
    {
        $content = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/dashboard')
            ->assertOk()
            ->getContent();

        // C'est Accounting qui pousse vers la droite, et System le suit.
        $this->assertMatchesRegularExpression(
            '#admin-nav-group admin-nav-group--end".*?Accounting.*?admin-nav-group ".*?System#s',
            $content
        );
        $this->assertSame(1, substr_count($content, 'admin-nav-group--end'));
    }

    public function test_system_pushes_alone_when_accounting_is_hidden(): void
    {
        $content = $this->actingAs(User::factory()->staffAdmin()->create())
            ->get('/admin/dashboard')
            ->assertOk()
            ->getContent();

        // Sans Accounting, la barre doit garder System collé à droite.
        $this->assertSame(1, substr_count($content, 'admin-nav-group--end'));
        $this->assertMatchesRegularExpression('#admin-nav-group--end".*?System#s', $content);
    }
}
