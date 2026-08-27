<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Signing in and out of the back office, and what deactivating an admin
 * really takes away.
 *
 * Deactivation is the door that gets closed when somebody leaves, so the
 * test that matters is not that a date was written down — the management
 * test covers that — but that the account can no longer sign in, and that
 * the shop can never be left without an owner able to open it.
 */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $overrides = []): User
    {
        return User::factory()->admin()->create([
            'password' => Hash::make('secret-password'),
            ...$overrides,
        ]);
    }

    /* --- Connexion ------------------------------------------------------ */

    public function test_an_admin_signs_in_and_lands_on_the_dashboard(): void
    {
        $admin = $this->admin(['email' => 'camille@example.com']);

        $this->post('/admin/login', ['email' => 'camille@example.com', 'password' => 'secret-password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_a_wrong_password_says_so_and_signs_nobody_in(): void
    {
        $this->admin(['email' => 'camille@example.com']);

        $this->from('/admin')
            ->post('/admin/login', ['email' => 'camille@example.com', 'password' => 'not-it'])
            ->assertRedirect('/admin')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_the_login_page_sends_an_admin_who_is_already_in_to_the_dashboard(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_the_login_page_stays_put_for_a_signed_in_customer(): void
    {
        // A customer session is not an admin session: the form has to be
        // shown, not skipped over.
        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertOk();
    }

    public function test_signing_out_ends_the_session(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/logout')
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
        $this->get('/admin/dashboard')->assertRedirect(route('admin.login'));
    }

    /* --- Désactivation --------------------------------------------------- */

    public function test_a_deactivated_admin_can_no_longer_sign_in(): void
    {
        $owner = $this->admin();
        $leaver = $this->admin(['email' => 'leaver@example.com', 'role' => 'staff']);

        $this->actingAs($owner)
            ->patch('/admin/settings/admins/'.$leaver->id.'/deactivate')
            ->assertRedirect(route('admin.settings.admins.index'));

        $this->post('/admin/logout');

        $this->post('/admin/login', ['email' => 'leaver@example.com', 'password' => 'secret-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_deactivated_admin_loses_the_session_they_already_had(): void
    {
        $leaver = $this->admin(['role' => 'staff']);

        $this->actingAs($leaver)->get('/admin/dashboard')->assertOk();

        $leaver->forceFill(['is_admin' => false, 'admin_deactivated_at' => now()])->save();

        $this->actingAs($leaver->fresh())
            ->get('/admin/dashboard')
            ->assertRedirect(route('admin.login'));
    }

    public function test_an_owner_can_be_deactivated_while_another_owner_remains(): void
    {
        $first = $this->admin();
        $second = $this->admin();

        $this->actingAs($first)
            ->patch('/admin/settings/admins/'.$second->id.'/deactivate')
            ->assertRedirect(route('admin.settings.admins.index'));

        $second->refresh();

        $this->assertFalse($second->is_admin);
        $this->assertNotNull($second->admin_deactivated_at);
    }

    public function test_reactivating_gives_the_account_its_access_back(): void
    {
        $owner = $this->admin();
        $returner = $this->admin(['email' => 'returner@example.com', 'role' => 'staff']);

        $this->actingAs($owner)->patch('/admin/settings/admins/'.$returner->id.'/deactivate');
        $this->actingAs($owner)->patch('/admin/settings/admins/'.$returner->id.'/reactivate')
            ->assertRedirect(route('admin.settings.admins.index'));

        $this->post('/admin/logout');

        $this->post('/admin/login', ['email' => 'returner@example.com', 'password' => 'secret-password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertTrue($returner->fresh()->is_admin);
        $this->assertSame('staff', $returner->fresh()->role);
    }
}
