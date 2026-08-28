<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Banning a customer: the login door closes, live sessions die on their next
 * request, and the ban is reversible — nothing of the customer's history goes
 * with it.
 */
class AdminCustomerBanTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_the_owner_can_ban_and_it_is_logged(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($this->owner())
            ->patch('/admin/customers/'.$customer->id.'/ban')
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertNotNull($customer->fresh()->banned_at);
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'customer.banned']);
    }

    public function test_a_staff_admin_cannot_ban(): void
    {
        $customer = User::factory()->create();

        $this->actingAs(User::factory()->staffAdmin()->create())
            ->patch('/admin/customers/'.$customer->id.'/ban')
            ->assertForbidden();

        $this->assertNull($customer->fresh()->banned_at);
    }

    public function test_an_admin_account_cannot_be_banned(): void
    {
        $otherAdmin = User::factory()->admin()->create();

        $this->actingAs($this->owner())
            ->patch('/admin/customers/'.$otherAdmin->id.'/ban')
            ->assertNotFound();
    }

    public function test_a_banned_customer_cannot_log_in(): void
    {
        $customer = User::factory()->create(['banned_at' => now()]);

        $this->post('/login', ['email' => $customer->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_banned_customer_with_a_live_session_is_signed_out(): void
    {
        $customer = User::factory()->create();

        // Banned mid-session: the next page they ask for shows them out.
        $this->actingAs($customer);
        $customer->forceFill(['banned_at' => now()])->save();

        $this->get('/account')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_lifting_the_ban_restores_login(): void
    {
        $customer = User::factory()->create(['banned_at' => now()]);

        $this->actingAs($this->owner())
            ->patch('/admin/customers/'.$customer->id.'/unban')
            ->assertRedirect();

        $this->assertNull($customer->fresh()->banned_at);
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'customer.unbanned']);

        // Back to a guest: the login form only serves the signed-out.
        auth()->guard('web')->logout();
        $this->flushSession();

        $this->post('/login', ['email' => $customer->email, 'password' => 'password'])
            ->assertRedirect();
        $this->assertAuthenticatedAs($customer->fresh());
    }

    public function test_banned_customers_are_kept_out_of_the_other_tabs(): void
    {
        User::factory()->create(['first_name' => 'Bandit', 'last_name' => 'Banni', 'banned_at' => now()]);

        $this->actingAs($this->owner())
            ->get('/admin/customers')
            ->assertOk()
            ->assertDontSee('Bandit');
    }

    public function test_the_banned_tab_lists_only_banned_customers(): void
    {
        $banned = User::factory()->create(['first_name' => 'Bandit', 'last_name' => 'Banni', 'banned_at' => now()]);
        User::factory()->create(['first_name' => 'Brave', 'last_name' => 'Client']);

        $this->actingAs($this->owner())
            ->get('/admin/customers?tab=banned')
            ->assertOk()
            ->assertSee('Bandit')
            ->assertDontSee('Brave');
    }
}
