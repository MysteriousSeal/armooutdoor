<?php

namespace Tests\Feature\Admin;

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

class FftirSessionTest extends TestCase
{
    use RefreshDatabase;

    private function weapon(): FftirWeapon
    {
        return FftirWeapon::query()->create([
            'brand' => 'FN', 'model' => '502', 'caliber' => '.22LR', 'type' => WeaponType::Pistol,
        ]);
    }

    private function ammunition(int $quantity = 200): FftirAmmunition
    {
        $ammunition = FftirAmmunition::query()->create([
            'brand' => 'GECKO', 'caliber' => '.22LR', 'denomination' => 'Rifle', 'quantity' => $quantity,
        ]);

        FftirAmmunitionStockMovement::query()->create([
            'fftir_ammunition_id' => $ammunition->id,
            'delta' => $quantity,
            'quantity_before' => 0,
            'quantity_after' => $quantity,
            'total_price_cents' => $quantity * 10,
        ]);

        return $ammunition;
    }

    public function test_the_create_page_lists_real_weapons_and_ammunitions(): void
    {
        $owner = User::factory()->admin()->create();
        $weapon = $this->weapon();
        $ammunition = $this->ammunition();

        $this->actingAs($owner)
            ->get(route('admin.fftir.sessions.create'))
            ->assertOk()
            ->assertSee($weapon->brand)
            ->assertSee($ammunition->brand)
            ->assertSee('25m');
    }

    public function test_logging_a_session_deducts_stock_and_records_the_line(): void
    {
        $owner = User::factory()->admin()->create();
        $weapon = $this->weapon();
        $ammunition = $this->ammunition(200);

        $this->actingAs($owner)
            ->post(route('admin.fftir.sessions.store'), [
                'date' => '2026-01-15',
                'note' => 'Zeroing',
                'lines' => [
                    ['weapon_id' => $weapon->id, 'ammunition_id' => $ammunition->id, 'distance' => '25m', 'quantity' => 50],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.fftir.sessions.index'));

        $session = FftirSession::query()->sole();
        $this->assertSame('2026-01-15', $session->date->toDateString());
        $this->assertSame('Zeroing', $session->note);

        $line = $session->lines()->sole();
        $this->assertSame($weapon->id, $line->fftir_weapon_id);
        $this->assertSame($ammunition->id, $line->fftir_ammunition_id);
        $this->assertSame(50, $line->quantity);

        $this->assertSame(150, $ammunition->fresh()->quantity);

        $movement = $ammunition->stockMovements()->latest('id')->first();
        $this->assertSame(-50, $movement->delta);
        $this->assertNull($movement->total_price_cents);
        $this->assertStringContainsString('2026-01-15', $movement->note);
    }

    public function test_several_lines_using_the_same_ammunition_are_deducted_together(): void
    {
        $owner = User::factory()->admin()->create();
        $weapon = $this->weapon();
        $ammunition = $this->ammunition(100);

        $this->actingAs($owner)
            ->post(route('admin.fftir.sessions.store'), [
                'date' => '2026-01-15',
                'lines' => [
                    ['weapon_id' => $weapon->id, 'ammunition_id' => $ammunition->id, 'distance' => '10m', 'quantity' => 40],
                    ['weapon_id' => $weapon->id, 'ammunition_id' => $ammunition->id, 'distance' => '50m', 'quantity' => 40],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(20, $ammunition->fresh()->quantity);
        $this->assertSame(2, FftirSession::query()->sole()->lines()->count());
    }

    public function test_insufficient_aggregate_stock_rejects_the_whole_session(): void
    {
        $owner = User::factory()->admin()->create();
        $weapon = $this->weapon();
        $ammunition = $this->ammunition(50);

        $this->actingAs($owner)
            ->post(route('admin.fftir.sessions.store'), [
                'date' => '2026-01-15',
                'lines' => [
                    ['weapon_id' => $weapon->id, 'ammunition_id' => $ammunition->id, 'distance' => '10m', 'quantity' => 30],
                    ['weapon_id' => $weapon->id, 'ammunition_id' => $ammunition->id, 'distance' => '50m', 'quantity' => 30],
                ],
            ])
            ->assertSessionHasErrors('lines');

        // Neither line went through, and nothing was deducted: a session
        // that runs out of ammo halfway through would be worse than one
        // that never started.
        $this->assertSame(0, FftirSession::query()->count());
        $this->assertSame(50, $ammunition->fresh()->quantity);
        $this->assertSame(1, $ammunition->stockMovements()->count());
    }

    public function test_a_missing_line_is_refused(): void
    {
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)
            ->post(route('admin.fftir.sessions.store'), ['date' => '2026-01-15', 'lines' => []])
            ->assertSessionHasErrors('lines');
    }

    public function test_an_unknown_distance_is_refused(): void
    {
        $owner = User::factory()->admin()->create();
        $weapon = $this->weapon();
        $ammunition = $this->ammunition();

        $this->actingAs($owner)
            ->post(route('admin.fftir.sessions.store'), [
                'date' => '2026-01-15',
                'lines' => [
                    ['weapon_id' => $weapon->id, 'ammunition_id' => $ammunition->id, 'distance' => '75m', 'quantity' => 10],
                ],
            ])
            ->assertSessionHasErrors('lines.0.distance');
    }

    public function test_a_session_cannot_be_edited(): void
    {
        $this->assertFalse(Route::has('admin.fftir.sessions.update'));
    }

    public function test_a_line_without_an_ammunition_derives_its_caliber_from_the_weapon_and_skips_deduction(): void
    {
        $owner = User::factory()->admin()->create();
        $weapon = $this->weapon(); // .22LR
        $ammunition = $this->ammunition(200);

        $this->actingAs($owner)
            ->post(route('admin.fftir.sessions.store'), [
                'date' => '2026-01-15',
                'lines' => [
                    ['weapon_id' => $weapon->id, 'ammunition_id' => null, 'distance' => '25m', 'quantity' => 20],
                ],
            ])
            ->assertSessionHasNoErrors();

        $line = FftirSession::query()->sole()->lines()->sole();
        $this->assertNull($line->fftir_ammunition_id);
        $this->assertSame($weapon->caliber, $line->caliber);

        // Nothing to deduct from when the box isn't named.
        $this->assertSame(200, $ammunition->fresh()->quantity);
        $this->assertSame(1, $ammunition->stockMovements()->count());
    }

    public function test_ammunition_of_the_wrong_caliber_for_the_weapon_is_refused(): void
    {
        $owner = User::factory()->admin()->create();
        $weapon = $this->weapon(); // .22LR
        $wrongCaliberAmmo = FftirAmmunition::query()->create([
            'brand' => 'Norma', 'caliber' => '9x19mm', 'denomination' => 'FMJ', 'quantity' => 100,
        ]);

        $this->actingAs($owner)
            ->post(route('admin.fftir.sessions.store'), [
                'date' => '2026-01-15',
                'lines' => [
                    ['weapon_id' => $weapon->id, 'ammunition_id' => $wrongCaliberAmmo->id, 'distance' => '25m', 'quantity' => 10],
                ],
            ])
            ->assertSessionHasErrors('lines.0.ammunition_id');

        $this->assertSame(0, FftirSession::query()->count());
        $this->assertSame(100, $wrongCaliberAmmo->fresh()->quantity);
    }

    public function test_deleting_a_session_restores_the_stock_it_deducted(): void
    {
        $owner = User::factory()->admin()->create();
        $weapon = $this->weapon();
        $ammunition = $this->ammunition(200);

        $this->actingAs($owner)->post(route('admin.fftir.sessions.store'), [
            'date' => '2026-01-15',
            'lines' => [
                ['weapon_id' => $weapon->id, 'ammunition_id' => $ammunition->id, 'distance' => '25m', 'quantity' => 50],
            ],
        ]);

        $session = FftirSession::query()->sole();
        $this->assertSame(150, $ammunition->fresh()->quantity);

        $this->actingAs($owner)
            ->delete(route('admin.fftir.sessions.destroy', $session))
            ->assertRedirect(route('admin.fftir.sessions.index'));

        $this->assertSame(0, FftirSession::query()->count());
        $this->assertSame(0, FftirSessionLine::query()->count());
        $this->assertSame(200, $ammunition->fresh()->quantity);

        // The original deduction stays in the ledger; a new entry offsets it.
        $this->assertSame(3, $ammunition->stockMovements()->count());
        $restoration = $ammunition->stockMovements()->latest('id')->first();
        $this->assertSame(50, $restoration->delta);
    }

    public function test_deleting_a_session_with_a_caliber_only_line_touches_no_stock(): void
    {
        $owner = User::factory()->admin()->create();
        $weapon = $this->weapon();
        $ammunition = $this->ammunition(200);

        $this->actingAs($owner)->post(route('admin.fftir.sessions.store'), [
            'date' => '2026-01-15',
            'lines' => [
                ['weapon_id' => $weapon->id, 'ammunition_id' => null, 'distance' => '25m', 'quantity' => 20],
            ],
        ]);

        $session = FftirSession::query()->sole();

        $this->actingAs($owner)->delete(route('admin.fftir.sessions.destroy', $session));

        $this->assertSame(0, FftirSession::query()->count());
        $this->assertSame(200, $ammunition->fresh()->quantity);
        $this->assertSame(1, $ammunition->stockMovements()->count());
    }

    public function test_the_weapon_list_reflects_a_logged_session(): void
    {
        $owner = User::factory()->admin()->create();
        $weapon = $this->weapon();
        $weapon->update(['price_cents' => 10000]);
        $ammunition = $this->ammunition(200);

        $this->actingAs($owner)->post(route('admin.fftir.sessions.store'), [
            'date' => '2026-01-15',
            'lines' => [
                ['weapon_id' => $weapon->id, 'ammunition_id' => $ammunition->id, 'distance' => '25m', 'quantity' => 100],
            ],
        ]);

        // 10000 (weapon) + 100 * 10 (ammo) = 11000, / 100 rounds fired = 110c.
        $this->actingAs($owner)
            ->get(route('admin.fftir.weapons.index'))
            ->assertOk()
            ->assertSee(format_euros(110), false);
    }
}
