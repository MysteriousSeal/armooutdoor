<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Customers nav badge counts shop customers whose profile no admin has
 * opened yet. Scoped the same way the customers list is: externals come from
 * manual orders and never appear there, so they must not appear in its badge.
 */
class AdminCustomerBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_customer_badges_the_customers_nav_item(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create();

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('1 not looked at yet', false);
    }

    public function test_no_badge_when_there_are_no_customers(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertDontSee('not looked at yet', false);
    }

    public function test_opening_a_profile_clears_that_customer_from_the_count(): void
    {
        $admin = User::factory()->admin()->create();
        $seen = User::factory()->create();
        User::factory()->create();

        $this->actingAs($admin)->get('/admin/customers/'.$seen->id)->assertOk();

        $this->assertNotNull($seen->fresh()->admin_viewed_at);

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('1 not looked at yet', false);
    }

    public function test_the_badge_disappears_once_every_profile_has_been_opened(): void
    {
        $admin = User::factory()->admin()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($admin)->get('/admin/customers/'.$a->id)->assertOk();
        $this->actingAs($admin)->get('/admin/customers/'.$b->id)->assertOk();

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertDontSee('not looked at yet', false);
    }

    public function test_reopening_a_profile_does_not_move_the_first_viewed_time(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();

        $this->actingAs($admin)->get('/admin/customers/'.$customer->id)->assertOk();
        $first = $customer->fresh()->admin_viewed_at;

        $this->travel(1)->hours();
        $this->actingAs($admin)->get('/admin/customers/'.$customer->id)->assertOk();

        $this->assertTrue($first->equalTo($customer->fresh()->admin_viewed_at));
    }

    public function test_external_customers_are_not_counted(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['external' => true]);

        // Manual-order accounts never show on the customers list, so counting
        // them in its badge would send the admin looking for rows that aren't
        // there.
        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertDontSee('not looked at yet', false);
    }

    public function test_admins_are_not_counted_as_customers(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertDontSee('not looked at yet', false);
    }

    public function test_the_badge_shows_on_every_admin_page(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create();

        foreach (['/admin/dashboard', '/admin/customers', '/admin/orders', '/admin/conversations'] as $path) {
            $this->actingAs($admin)
                ->get($path)
                ->assertOk()
                ->assertSee('not looked at yet', false);
        }
    }

    public function test_the_list_marks_a_customer_nobody_has_opened(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create();

        $this->actingAs($admin)
            ->get('/admin/customers')
            ->assertOk()
            ->assertSee('admin-new-chip', false)
            ->assertSee('admin-row--new', false)
            ->assertSee('admin-row-dot', false);
    }

    public function test_the_list_marker_disappears_once_the_profile_is_opened(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();

        $this->actingAs($admin)->get('/admin/customers/'.$customer->id)->assertOk();

        $this->actingAs($admin)
            ->get('/admin/customers')
            ->assertOk()
            ->assertDontSee('admin-new-chip', false)
            ->assertDontSee('admin-row--new', false)
            ->assertDontSee('admin-row-dot', false);
    }

    public function test_only_the_unopened_rows_are_marked(): void
    {
        $admin = User::factory()->admin()->create();
        $seen = User::factory()->create();
        User::factory()->create();
        User::factory()->create();

        $this->actingAs($admin)->get('/admin/customers/'.$seen->id)->assertOk();

        $content = $this->actingAs($admin)->get('/admin/customers')->getContent();

        $this->assertSame(2, substr_count($content, 'admin-new-chip'));
        $this->assertSame(2, substr_count($content, 'admin-row--new'));
        $this->assertSame(2, substr_count($content, 'admin-row-dot'));
    }

    public function test_the_scope_matches_what_the_badge_claims(): void
    {
        $unviewed = User::factory()->create();
        $viewed = User::factory()->create(['admin_viewed_at' => now()]);
        User::factory()->create(['external' => true]);
        User::factory()->admin()->create();

        $ids = User::query()->unviewedByAdmin()->pluck('id');

        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains($unviewed->id));
        $this->assertFalse($ids->contains($viewed->id));
    }
}
