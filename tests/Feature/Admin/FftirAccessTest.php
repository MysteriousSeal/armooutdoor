<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** FFTIR (weapons, ammunitions, sessions) is owner-only, like Accounting. */
class FftirAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, string>
     */
    private function pages(): array
    {
        return [
            route('admin.fftir.index'),
            route('admin.fftir.weapons.index'),
            route('admin.fftir.weapons.create'),
            route('admin.fftir.ammunitions.index'),
            route('admin.fftir.ammunitions.create'),
            route('admin.fftir.sessions.index'),
            route('admin.fftir.sessions.create'),
        ];
    }

    public function test_the_owner_reaches_every_page(): void
    {
        $owner = User::factory()->admin()->create();

        foreach ($this->pages() as $url) {
            $this->actingAs($owner)->get($url)->assertOk();
        }
    }

    public function test_a_staff_admin_is_turned_away(): void
    {
        $staff = User::factory()->staffAdmin()->create();

        foreach ($this->pages() as $url) {
            $this->actingAs($staff)->get($url)->assertForbidden();
        }
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        foreach ($this->pages() as $url) {
            $this->get($url)->assertRedirect(route('admin.login'));
        }
    }

    public function test_the_menu_shows_the_tab_only_for_the_owner(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('FFTIR')
            ->assertSee(route('admin.fftir.index'), false);

        $this->actingAs(User::factory()->staffAdmin()->create())
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertDontSee('FFTIR');
    }
}
