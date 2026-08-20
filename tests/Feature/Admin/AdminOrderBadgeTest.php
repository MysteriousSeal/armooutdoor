<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The Orders nav badge counts only orders nobody has started on. Anything
 * already being prepared, shipped, refunded, archived or still a draft is
 * deliberately outside it — widening that should take a deliberate change.
 */
class AdminOrderBadgeTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $status = 'placed', ?Carbon $archivedAt = null): Order
    {
        return Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
            'status' => $status,
            'archived_at' => $archivedAt,
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000,
            'shipping_cents' => 500,
            'discount_cents' => 0,
            'total_cents' => 1500,
            'payment_method' => 'card',
        ]);
    }

    public function test_a_placed_order_badges_the_orders_nav_item(): void
    {
        $admin = User::factory()->admin()->create();
        $this->order('placed');

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('not started yet', false);
    }

    public function test_no_badge_without_any_orders(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertDontSee('not started yet', false);
    }

    public function test_the_badge_shows_how_many_are_waiting(): void
    {
        $admin = User::factory()->admin()->create();
        $this->order('placed');
        $this->order('placed');
        $this->order('placed');

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('3 not started yet', false);
    }

    public function test_orders_already_being_prepared_do_not_count(): void
    {
        $admin = User::factory()->admin()->create();
        $this->order('preparing');

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertDontSee('not started yet', false);
    }

    public function test_shipped_and_refunded_orders_do_not_count(): void
    {
        $admin = User::factory()->admin()->create();
        $this->order('shipped');
        $this->order('refunded');

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertDontSee('not started yet', false);
    }

    public function test_archived_and_draft_orders_do_not_count(): void
    {
        $admin = User::factory()->admin()->create();
        $this->order('placed', now());
        $this->order('draft');

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertDontSee('not started yet', false);
    }

    public function test_the_badge_counts_only_the_waiting_ones_among_others(): void
    {
        $admin = User::factory()->admin()->create();
        $this->order('placed');
        $this->order('preparing');
        $this->order('shipped');
        $this->order('placed', now());
        $this->order('draft');

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('1 not started yet', false);
    }

    public function test_the_badge_shows_on_every_admin_page_not_just_orders(): void
    {
        $admin = User::factory()->admin()->create();
        $this->order('placed');

        foreach (['/admin/dashboard', '/admin/orders', '/admin/customers', '/admin/conversations'] as $path) {
            $this->actingAs($admin)
                ->get($path)
                ->assertOk()
                ->assertSee('not started yet', false);
        }
    }

    public function test_the_scope_matches_what_the_badge_claims(): void
    {
        $waiting = $this->order('placed');
        $this->order('preparing');
        $this->order('shipped');
        $this->order('refunded');
        $this->order('draft');
        $this->order('placed', now());

        $ids = Order::query()->awaitingStart()->pluck('id');

        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains($waiting->id));
    }
}
