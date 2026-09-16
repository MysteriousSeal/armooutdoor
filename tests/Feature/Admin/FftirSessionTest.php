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

    private function loggedLine(int $quantity = 50): FftirSessionLine
    {
        $weapon = $this->weapon();
        $ammunition = $this->ammunition(200);

        FftirSession::query()->create(['date' => '2026-01-15'])
            ->lines()->create([
                'fftir_weapon_id' => $weapon->id,
                'fftir_ammunition_id' => $ammunition->id,
                'caliber' => $weapon->caliber,
                'distance' => '25m',
                'quantity' => $quantity,
            ]);

        $ammunition->update(['quantity' => $ammunition->quantity - $quantity]);

        FftirAmmunitionStockMovement::query()->create([
            'fftir_ammunition_id' => $ammunition->id,
            'delta' => -$quantity,
            'quantity_before' => $ammunition->quantity + $quantity,
            'quantity_after' => $ammunition->quantity,
            'note' => 'Used in shooting session on 2026-01-15',
        ]);

        return FftirSessionLine::query()->sole();
    }

    public function test_the_edit_page_shows_the_current_values(): void
    {
        $owner = User::factory()->admin()->create();
        $line = $this->loggedLine();

        $this->actingAs($owner)
            ->get(route('admin.fftir.sessions.lines.edit', [$line->session, $line]))
            ->assertOk()
            ->assertSee('value="50"', false);
    }

    public function test_increasing_a_lines_quantity_deducts_the_difference(): void
    {
        $owner = User::factory()->admin()->create();
        $line = $this->loggedLine(50);
        $ammunition = $line->ammunition;

        $this->actingAs($owner)
            ->put(route('admin.fftir.sessions.lines.update', [$line->session, $line]), [
                'weapon_id' => $line->fftir_weapon_id,
                'ammunition_id' => $ammunition->id,
                'distance' => '50m',
                'quantity' => 70,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.fftir.sessions.index'));

        $line->refresh();
        $this->assertSame(70, $line->quantity);
        $this->assertSame('50m', $line->distance->value);
        $this->assertSame(130, $ammunition->fresh()->quantity);
    }

    public function test_removing_the_ammunition_from_a_line_restores_its_stock(): void
    {
        $owner = User::factory()->admin()->create();
        $line = $this->loggedLine(50);
        $ammunition = $line->ammunition;

        $this->actingAs($owner)
            ->put(route('admin.fftir.sessions.lines.update', [$line->session, $line]), [
                'weapon_id' => $line->fftir_weapon_id,
                'ammunition_id' => null,
                'distance' => '25m',
                'quantity' => 50,
            ])
            ->assertSessionHasNoErrors();

        $line->refresh();
        $this->assertNull($line->fftir_ammunition_id);
        $this->assertSame($line->weapon->caliber, $line->caliber);
        $this->assertSame(200, $ammunition->fresh()->quantity);
    }

    public function test_editing_a_line_to_a_wrong_caliber_ammunition_is_refused(): void
    {
        $owner = User::factory()->admin()->create();
        $line = $this->loggedLine(50);
        $wrongCaliberAmmo = FftirAmmunition::query()->create([
            'brand' => 'Norma', 'caliber' => '9x19mm', 'denomination' => 'FMJ', 'quantity' => 100,
        ]);

        $this->actingAs($owner)
            ->put(route('admin.fftir.sessions.lines.update', [$line->session, $line]), [
                'weapon_id' => $line->fftir_weapon_id,
                'ammunition_id' => $wrongCaliberAmmo->id,
                'distance' => '25m',
                'quantity' => 10,
            ])
            ->assertSessionHasErrors('ammunition_id');

        $line->refresh();
        $this->assertSame(50, $line->quantity);
        $this->assertSame(150, $line->ammunition->quantity);
        $this->assertSame(100, $wrongCaliberAmmo->fresh()->quantity);
    }

    public function test_editing_a_line_beyond_available_stock_is_refused_and_leaves_stock_untouched(): void
    {
        $owner = User::factory()->admin()->create();
        $line = $this->loggedLine(50);
        $ammunition = $line->ammunition;

        $this->actingAs($owner)
            ->put(route('admin.fftir.sessions.lines.update', [$line->session, $line]), [
                'weapon_id' => $line->fftir_weapon_id,
                'ammunition_id' => $ammunition->id,
                'distance' => '25m',
                'quantity' => 999,
            ])
            ->assertSessionHasErrors('quantity');

        $line->refresh();
        $this->assertSame(50, $line->quantity);
        $this->assertSame(150, $ammunition->fresh()->quantity);
    }

    public function test_a_line_cannot_be_edited_through_a_session_it_does_not_belong_to(): void
    {
        $owner = User::factory()->admin()->create();
        $line = $this->loggedLine();
        $otherSession = FftirSession::query()->create(['date' => '2026-02-01']);

        $this->actingAs($owner)
            ->get(route('admin.fftir.sessions.lines.edit', [$otherSession, $line]))
            ->assertNotFound();
    }
}
