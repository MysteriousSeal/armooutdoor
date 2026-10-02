<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Telescope's own dashboard middleware runs outside the app's admin auth,
 * with every request, query, and exception behind it, so this gate is the
 * only thing standing between that data and whoever finds the path.
 *
 * The dashboard routes themselves are only registered when telescope.enabled
 * is true, which phpunit.xml turns off for the suite (same as Pulse and
 * Nightwatch) to keep tests from recording themselves. The gate Laravel\
 * Telescope\TelescopeApplicationServiceProvider registers is unaffected by
 * that flag, so it is checked directly here.
 */
class TelescopeAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_view_telescope(): void
    {
        $this->assertFalse(Gate::check('viewTelescope', [null]));
    }

    public function test_a_staff_admin_cannot_view_telescope(): void
    {
        $staff = User::factory()->staffAdmin()->create();

        $this->assertFalse(Gate::forUser($staff)->check('viewTelescope'));
    }

    public function test_an_owner_can_view_telescope(): void
    {
        $owner = User::factory()->admin()->create();

        $this->assertTrue(Gate::forUser($owner)->check('viewTelescope'));
    }
}
