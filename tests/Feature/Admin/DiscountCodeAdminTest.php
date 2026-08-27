<?php

namespace Tests\Feature\Admin;

use App\Models\DiscountCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Creating and editing a discount code from the back office.
 *
 * The storefront side of codes is well covered; nothing checked what the
 * admin form writes. Two conversions live here and are invisible once
 * wrong: an amount typed in euros becomes cents, a percentage stays a whole
 * number, and a free-delivery code has no amount at all.
 */
class DiscountCodeAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function payload(array $overrides = []): array
    {
        return [
            'code' => 'WELCOME10',
            'type' => DiscountCode::TYPE_PERCENTAGE,
            'value' => '10',
            ...$overrides,
        ];
    }

    /* --- Le contrôle de disponibilité du code --------------------------- */

    public function test_an_unused_code_is_reported_as_free(): void
    {
        $this->actingAs($this->admin())
            ->getJson('/admin/discount-codes/check-code?code=BRANDNEW')
            ->assertOk()
            ->assertExactJson(['exists' => false]);
    }

    public function test_a_taken_code_is_reported_as_taken(): void
    {
        DiscountCode::query()->create(['code' => 'TAKEN', 'type' => 'percentage', 'value' => 10]);

        $this->actingAs($this->admin())
            ->getJson('/admin/discount-codes/check-code?code=TAKEN')
            ->assertOk()
            ->assertExactJson(['exists' => true]);
    }

    public function test_the_check_reads_the_code_the_way_the_form_will_save_it(): void
    {
        // Codes are stored upper case and trimmed, so the availability check
        // has to normalise the same way or it clears a code that then fails
        // to save as a duplicate.
        DiscountCode::query()->create(['code' => 'TAKEN', 'type' => 'percentage', 'value' => 10]);

        $this->actingAs($this->admin())
            ->getJson('/admin/discount-codes/check-code?code='.urlencode(' taken '))
            ->assertOk()
            ->assertExactJson(['exists' => true]);
    }

    public function test_an_empty_code_is_never_reported_as_taken(): void
    {
        $this->actingAs($this->admin())
            ->getJson('/admin/discount-codes/check-code?code=')
            ->assertOk()
            ->assertExactJson(['exists' => false]);
    }

    /* --- Création ------------------------------------------------------- */

    public function test_a_percentage_is_stored_as_a_whole_number(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/discount-codes', $this->payload(['value' => '15']))
            ->assertRedirect(route('admin.discounts.index', ['tab' => 'codes']));

        $code = DiscountCode::query()->firstOrFail();

        $this->assertSame(DiscountCode::TYPE_PERCENTAGE, $code->type);
        $this->assertSame(15, $code->value);
    }

    public function test_a_fixed_amount_typed_in_euros_is_stored_in_cents(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/discount-codes', $this->payload([
                'code' => 'MINUS12',
                'type' => DiscountCode::TYPE_FIXED,
                'value' => '12.50',
            ]))
            ->assertRedirect();

        $this->assertSame(1250, DiscountCode::query()->firstOrFail()->value);
    }

    public function test_a_free_delivery_code_carries_no_amount(): void
    {
        // The form leaves a stale amount in a hidden field; it must not end
        // up on the code.
        $this->actingAs($this->admin())
            ->post('/admin/discount-codes', $this->payload([
                'code' => 'FREERELAY',
                'type' => DiscountCode::TYPE_FREE_RELAY_SHIPPING,
                'value' => '99',
            ]))
            ->assertRedirect();

        $this->assertNull(DiscountCode::query()->firstOrFail()->value);
    }

    public function test_a_code_is_saved_upper_case_and_trimmed(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/discount-codes', $this->payload(['code' => '  welcome10  ']))
            ->assertRedirect();

        $this->assertSame('WELCOME10', DiscountCode::query()->firstOrFail()->code);
    }

    public function test_the_same_code_cannot_be_created_twice(): void
    {
        DiscountCode::query()->create(['code' => 'WELCOME10', 'type' => 'percentage', 'value' => 10]);

        $this->actingAs($this->admin())
            ->post('/admin/discount-codes', $this->payload())
            ->assertSessionHasErrors('code');

        $this->assertSame(1, DiscountCode::query()->count());
    }

    public function test_a_code_with_spaces_or_symbols_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/discount-codes', $this->payload(['code' => 'WELCOME 10%']))
            ->assertSessionHasErrors('code');

        $this->assertSame(0, DiscountCode::query()->count());
    }

    public function test_a_percentage_over_a_hundred_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/discount-codes', $this->payload(['value' => '120']))
            ->assertSessionHasErrors('value');
    }

    public function test_a_customer_cannot_be_allowed_more_uses_than_the_code_has(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/discount-codes', $this->payload([
                'quantity' => 5,
                'max_uses_per_customer' => 6,
            ]))
            ->assertSessionHasErrors('max_uses_per_customer');
    }

    public function test_blank_limits_mean_unlimited_rather_than_zero(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/discount-codes', $this->payload([
                'quantity' => '',
                'max_uses_per_customer' => '',
                'ends_at' => '',
                'user_id' => '',
            ]))
            ->assertRedirect();

        $code = DiscountCode::query()->firstOrFail();

        $this->assertNull($code->quantity);
        $this->assertNull($code->max_uses_per_customer);
        $this->assertNull($code->ends_at);
        $this->assertNull($code->user_id);
    }

    /* --- Modification et suppression ------------------------------------ */

    public function test_a_code_keeps_its_own_name_when_edited(): void
    {
        // The uniqueness rule has to ignore the row being saved, or no code
        // could ever be edited without being renamed.
        $code = DiscountCode::query()->create(['code' => 'WELCOME10', 'type' => 'percentage', 'value' => 10]);

        $this->actingAs($this->admin())
            ->put('/admin/discount-codes/'.$code->id, $this->payload(['value' => '20']))
            ->assertRedirect(route('admin.discounts.index', ['tab' => 'codes']));

        $this->assertSame(20, $code->fresh()->value);
    }

    public function test_a_code_can_be_removed(): void
    {
        $code = DiscountCode::query()->create(['code' => 'GONE', 'type' => 'percentage', 'value' => 10]);

        $this->actingAs($this->admin())
            ->delete('/admin/discount-codes/'.$code->id)
            ->assertRedirect(route('admin.discounts.index', ['tab' => 'codes']));

        $this->assertNull(DiscountCode::query()->find($code->id));
    }

    public function test_the_create_and_edit_forms_render(): void
    {
        $admin = $this->admin();
        $code = DiscountCode::query()->create(['code' => 'WELCOME10', 'type' => 'percentage', 'value' => 10]);

        $this->actingAs($admin)->get('/admin/discount-codes/create')->assertOk();
        $this->actingAs($admin)->get('/admin/discount-codes/'.$code->id.'/edit')->assertOk()->assertSee('WELCOME10');
    }
}
