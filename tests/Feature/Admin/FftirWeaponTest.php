<?php

namespace Tests\Feature\Admin;

use App\Enums\Caliber;
use App\Enums\WeaponType;
use App\Models\FftirAmmunition;
use App\Models\FftirAmmunitionStockMovement;
use App\Models\FftirSession;
use App\Models\FftirSessionLine;
use App\Models\FftirWeapon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FftirWeaponTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'type' => WeaponType::Pistol->value,
            'brand' => 'Glock',
            'model' => '17 Gen5',
            'caliber' => '9x19mm',
            ...$overrides,
        ];
    }

    public function test_an_owner_creates_a_weapon(): void
    {
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)
            ->post(route('admin.fftir.weapons.store'), $this->payload(['price' => '599.90']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.fftir.weapons.index'));

        $weapon = FftirWeapon::query()->sole();

        $this->assertSame('Glock', $weapon->brand);
        $this->assertSame('17 Gen5', $weapon->model);
        $this->assertSame(Caliber::Luger9x19, $weapon->caliber);
        $this->assertSame(WeaponType::Pistol, $weapon->type);
        $this->assertSame(59990, $weapon->price_cents);
    }

    public function test_the_price_is_optional(): void
    {
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)
            ->post(route('admin.fftir.weapons.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertNull(FftirWeapon::query()->sole()->price_cents);
    }

    public function test_required_fields_are_enforced(): void
    {
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)
            ->post(route('admin.fftir.weapons.store'), $this->payload(['brand' => '', 'type' => '']))
            ->assertSessionHasErrors(['brand', 'type']);

        $this->assertSame(0, FftirWeapon::query()->count());
    }

    public function test_an_invalid_type_is_refused(): void
    {
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)
            ->post(route('admin.fftir.weapons.store'), $this->payload(['type' => 'bazooka']))
            ->assertSessionHasErrors('type');
    }

    public function test_an_invalid_caliber_is_refused(): void
    {
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)
            ->post(route('admin.fftir.weapons.store'), $this->payload(['caliber' => '.50 BMG']))
            ->assertSessionHasErrors('caliber');
    }

    public function test_a_negative_price_is_refused(): void
    {
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)
            ->post(route('admin.fftir.weapons.store'), $this->payload(['price' => '-1']))
            ->assertSessionHasErrors('price');
    }

    public function test_the_edit_form_shows_the_current_values(): void
    {
        $owner = User::factory()->admin()->create();
        $weapon = FftirWeapon::query()->create($this->payload(['price_cents' => 65995]) + ['type' => WeaponType::Revolver]);

        $this->actingAs($owner)
            ->get(route('admin.fftir.weapons.edit', $weapon))
            ->assertOk()
            ->assertSee('value="Glock"', false)
            ->assertSee('value="659.95"', false);
    }

    public function test_an_owner_updates_a_weapon(): void
    {
        $owner = User::factory()->admin()->create();
        $weapon = FftirWeapon::query()->create($this->payload() + ['type' => WeaponType::Pistol]);

        $this->actingAs($owner)
            ->put(route('admin.fftir.weapons.update', $weapon), $this->payload([
                'brand' => 'Sig Sauer',
                'model' => 'P320',
                'type' => WeaponType::Revolver->value,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.fftir.weapons.index'));

        $weapon->refresh();
        $this->assertSame('Sig Sauer', $weapon->brand);
        $this->assertSame('P320', $weapon->model);
        $this->assertSame(WeaponType::Revolver, $weapon->type);
    }

    public function test_a_weapon_cannot_be_deleted(): void
    {
        $this->assertFalse(Route::has('admin.fftir.weapons.destroy'));

        $weapon = FftirWeapon::query()->create($this->payload() + ['type' => WeaponType::Pistol]);

        $this->actingAs(User::factory()->admin()->create())
            ->delete('/admin/fftir/weapons/'.$weapon->id)
            ->assertStatus(405);

        $this->assertNotNull($weapon->fresh());
    }

    public function test_the_list_shows_rounds_fired_and_cost_per_round(): void
    {
        $owner = User::factory()->admin()->create();

        $weapon = FftirWeapon::query()->create($this->payload(['price_cents' => 10000]) + ['type' => WeaponType::Pistol]);
        $ammunition = FftirAmmunition::query()->create([
            'brand' => 'GECKO',
            'caliber' => '.22LR',
            'denomination' => 'Rifle',
            'quantity' => 200,
        ]);

        FftirAmmunitionStockMovement::query()->create([
            'fftir_ammunition_id' => $ammunition->id,
            'delta' => 200,
            'quantity_before' => 0,
            'quantity_after' => 200,
            'total_price_cents' => 2000,
        ]);

        $session = FftirSession::query()->create(['date' => '2026-01-01']);
        FftirSessionLine::query()->create([
            'fftir_session_id' => $session->id,
            'fftir_weapon_id' => $weapon->id,
            'fftir_ammunition_id' => $ammunition->id,
            'distance' => '25m',
            'quantity' => 100,
        ]);

        // 10000 (weapon) + 100 * 10 (ammo, 10c/round) = 11000, / 100 rounds = 110c.
        $this->actingAs($owner)
            ->get(route('admin.fftir.weapons.index'))
            ->assertOk()
            ->assertSee('100')
            ->assertSee(format_euros(110), false);
    }

    public function test_a_never_fired_weapon_shows_not_applicable(): void
    {
        $owner = User::factory()->admin()->create();
        FftirWeapon::query()->create($this->payload() + ['type' => WeaponType::Pistol]);

        $this->actingAs($owner)
            ->get(route('admin.fftir.weapons.index'))
            ->assertOk()
            ->assertSee('N/A');
    }
}
