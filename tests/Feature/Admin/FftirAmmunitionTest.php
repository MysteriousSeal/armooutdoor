<?php

namespace Tests\Feature\Admin;

use App\Models\FftirAmmunition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FftirAmmunitionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'brand' => 'Sellier & Bellot',
            'caliber' => '9x19mm',
            'denomination' => 'FMJ 124gr',
            ...$overrides,
        ];
    }

    public function test_an_owner_creates_an_ammunition_without_a_price_field(): void
    {
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)
            ->post(route('admin.fftir.ammunitions.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.fftir.ammunitions.index'));

        $ammunition = FftirAmmunition::query()->sole();
        $this->assertSame('Sellier & Bellot', $ammunition->brand);
        $this->assertSame(0, $ammunition->quantity);
        $this->assertNull($ammunition->averageUnitPriceCents());
    }

    public function test_the_edit_form_has_no_price_input(): void
    {
        $owner = User::factory()->admin()->create();
        $ammunition = FftirAmmunition::query()->create($this->payload());

        $this->actingAs($owner)
            ->get(route('admin.fftir.ammunitions.edit', $ammunition))
            ->assertOk()
            ->assertDontSee('name="unit_price"', false)
            ->assertDontSee('name="price"', false);
    }

    public function test_required_fields_are_enforced(): void
    {
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)
            ->post(route('admin.fftir.ammunitions.store'), $this->payload(['brand' => '']))
            ->assertSessionHasErrors('brand');

        $this->assertSame(0, FftirAmmunition::query()->count());
    }

    public function test_an_invalid_caliber_is_refused(): void
    {
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)
            ->post(route('admin.fftir.ammunitions.store'), $this->payload(['caliber' => '.50 BMG']))
            ->assertSessionHasErrors('caliber');
    }

    public function test_an_owner_updates_an_ammunition(): void
    {
        $owner = User::factory()->admin()->create();
        $ammunition = FftirAmmunition::query()->create($this->payload());

        $this->actingAs($owner)
            ->put(route('admin.fftir.ammunitions.update', $ammunition), $this->payload(['brand' => 'Norma']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Norma', $ammunition->fresh()->brand);
    }

    public function test_an_ammunition_cannot_be_deleted(): void
    {
        $this->assertFalse(Route::has('admin.fftir.ammunitions.destroy'));

        $ammunition = FftirAmmunition::query()->create($this->payload());

        $this->actingAs(User::factory()->admin()->create())
            ->delete('/admin/fftir/ammunitions/'.$ammunition->id)
            ->assertStatus(405);

        $this->assertNotNull($ammunition->fresh());
    }

    public function test_adding_stock_with_a_price_records_a_movement_and_the_average_price(): void
    {
        $owner = User::factory()->admin()->create();
        $ammunition = FftirAmmunition::query()->create($this->payload());

        $this->actingAs($owner)
            ->post(route('admin.fftir.ammunitions.stock.store', $ammunition), [
                'delta' => 200,
                'total_price' => '20.00',
                'note' => 'First batch',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.fftir.ammunitions.index'));

        $ammunition->refresh();
        $this->assertSame(200, $ammunition->quantity);
        $this->assertSame(10, $ammunition->averageUnitPriceCents());

        $movement = $ammunition->stockMovements()->sole();
        $this->assertSame(200, $movement->delta);
        $this->assertSame(2000, $movement->total_price_cents);
        $this->assertSame(0, $movement->quantity_before);
        $this->assertSame(200, $movement->quantity_after);
        $this->assertSame('First batch', $movement->note);
    }

    public function test_the_average_price_is_weighted_across_several_additions(): void
    {
        $owner = User::factory()->admin()->create();
        $ammunition = FftirAmmunition::query()->create($this->payload());

        $this->actingAs($owner)->post(route('admin.fftir.ammunitions.stock.store', $ammunition), [
            'delta' => 200, 'total_price' => '20.00',
        ]);
        $this->actingAs($owner)->post(route('admin.fftir.ammunitions.stock.store', $ammunition), [
            'delta' => 50, 'total_price' => '6.00',
        ]);

        // (2000 + 600) / (200 + 50) = 10.4, rounds to 10.
        $this->assertSame(10, $ammunition->fresh()->averageUnitPriceCents());
    }

    public function test_removing_stock_ignores_any_submitted_price(): void
    {
        $owner = User::factory()->admin()->create();
        $ammunition = FftirAmmunition::query()->create($this->payload(['quantity' => 100]));

        $this->actingAs($owner)
            ->post(route('admin.fftir.ammunitions.stock.store', $ammunition), [
                'delta' => -20,
                'total_price' => '5.00',
            ])
            ->assertSessionHasNoErrors();

        $movement = $ammunition->stockMovements()->sole();
        $this->assertSame(-20, $movement->delta);
        $this->assertNull($movement->total_price_cents);
        $this->assertNull($ammunition->fresh()->averageUnitPriceCents());
    }

    public function test_stock_cannot_be_taken_below_zero(): void
    {
        $owner = User::factory()->admin()->create();
        $ammunition = FftirAmmunition::query()->create($this->payload(['quantity' => 10]));

        $this->actingAs($owner)
            ->from(route('admin.fftir.ammunitions.index'))
            ->post(route('admin.fftir.ammunitions.stock.store', $ammunition), ['delta' => -20])
            ->assertRedirect(route('admin.fftir.ammunitions.index'))
            ->assertSessionHasErrors('delta', null, 'stock'.$ammunition->id);

        $this->assertSame(10, $ammunition->fresh()->quantity);
        $this->assertSame(0, $ammunition->stockMovements()->count());
    }

    public function test_a_zero_change_is_refused(): void
    {
        $owner = User::factory()->admin()->create();
        $ammunition = FftirAmmunition::query()->create($this->payload());

        $this->actingAs($owner)
            ->post(route('admin.fftir.ammunitions.stock.store', $ammunition), ['delta' => 0])
            ->assertSessionHasErrors('delta', null, 'stock'.$ammunition->id);
    }

    public function test_the_stock_history_page_lists_price_paid(): void
    {
        $owner = User::factory()->admin()->create();
        $ammunition = FftirAmmunition::query()->create($this->payload());

        $this->actingAs($owner)->post(route('admin.fftir.ammunitions.stock.store', $ammunition), [
            'delta' => 100, 'total_price' => '10.00',
        ]);

        $this->actingAs($owner)
            ->get(route('admin.fftir.ammunitions.stock-history', $ammunition))
            ->assertOk()
            ->assertSee(format_euros(1000), false);
    }
}
