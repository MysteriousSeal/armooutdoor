<?php

namespace Tests\Feature\Admin;

use App\Models\Carrier;
use App\Models\CarrierPriceTier;
use App\Models\CompanySetting;
use App\Models\PackageType;
use App\Models\ShippingSetting;
use App\Models\User;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the settings forms actually write down.
 *
 * The activity-log test already proves these routes are reachable and leave
 * a trace; nothing checked what landed in the database. Two things are easy
 * to get wrong here and expensive to notice later: prices typed in euros are
 * stored in cents, and a checkbox nobody ticked is absent from the request
 * rather than false — so it has to be coerced, not read.
 */
class AdminSettingsPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ShippingSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function carrier(): Carrier
    {
        return Carrier::query()->orderBy('id')->firstOrFail();
    }

    /* --- Livraison offerte -------------------------------------------- */

    public function test_the_free_shipping_threshold_is_typed_in_euros_and_stored_in_cents(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/settings/shipping', ['free_shipping_threshold' => '59.90'])
            ->assertRedirect(route('admin.settings.shipping.edit'));

        $this->assertSame(5990, ShippingSetting::current()->fresh()->free_shipping_threshold_cents);
    }

    public function test_an_empty_threshold_switches_free_shipping_off_rather_than_setting_it_to_zero(): void
    {
        // Zero would make every order qualify — the opposite of what an
        // emptied field means.
        ShippingSetting::current()->update(['free_shipping_threshold_cents' => 5000]);

        $this->actingAs($this->admin())
            ->put('/admin/settings/shipping', ['free_shipping_threshold' => ''])
            ->assertRedirect(route('admin.settings.shipping.edit'));

        $this->assertNull(ShippingSetting::current()->fresh()->free_shipping_threshold_cents);
    }

    public function test_the_carriers_that_ship_for_free_are_stored_as_integers(): void
    {
        $carrier = $this->carrier();

        $this->actingAs($this->admin())
            ->put('/admin/settings/shipping', [
                'free_shipping_threshold' => '50',
                // The form posts checkbox values as strings.
                'free_shipping_carrier_ids' => [(string) $carrier->id],
            ])
            ->assertRedirect(route('admin.settings.shipping.edit'));

        $this->assertSame([$carrier->id], ShippingSetting::current()->fresh()->free_shipping_carrier_ids);
    }

    public function test_an_unknown_carrier_is_refused_rather_than_stored(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/settings/shipping', [
                'free_shipping_threshold' => '50',
                'free_shipping_carrier_ids' => [99999],
            ])
            ->assertSessionHasErrors('free_shipping_carrier_ids.0');

        $this->assertNull(ShippingSetting::current()->fresh()->free_shipping_threshold_cents);
    }

    /* --- Grille tarifaire d'un transporteur ---------------------------- */

    public function test_saving_a_grid_replaces_the_previous_tiers_instead_of_adding_to_them(): void
    {
        $carrier = $this->carrier();
        $carrier->priceTiers()->create(['min_weight_grams' => 500, 'price_cents' => 490]);

        $this->actingAs($this->admin())
            ->put('/admin/settings/carriers/'.$carrier->id.'/price-tiers', [
                'default_price' => '4.90',
                'tiers' => [
                    ['min_weight' => '1000', 'price' => '7.90'],
                    ['min_weight' => '2000', 'price' => '11.50'],
                ],
            ])
            ->assertRedirect(route('admin.settings.shipping.edit'));

        $tiers = $carrier->priceTiers()->orderBy('min_weight_grams')->get();

        $this->assertCount(2, $tiers, 'The old tier survived the save.');
        $this->assertSame([1000, 2000], $tiers->pluck('min_weight_grams')->all());
        $this->assertSame([790, 1150], $tiers->pluck('price_cents')->all());
        $this->assertSame(490, $carrier->fresh()->price_cents);
    }

    public function test_a_grid_can_be_emptied_back_to_the_single_default_price(): void
    {
        $carrier = $this->carrier();
        $carrier->priceTiers()->create(['min_weight_grams' => 500, 'price_cents' => 490]);

        $this->actingAs($this->admin())
            ->put('/admin/settings/carriers/'.$carrier->id.'/price-tiers', ['default_price' => '6.20'])
            ->assertRedirect(route('admin.settings.shipping.edit'));

        $this->assertSame(0, $carrier->priceTiers()->count());
        $this->assertSame(620, $carrier->fresh()->price_cents);
    }

    public function test_two_tiers_cannot_start_at_the_same_weight(): void
    {
        // Two tiers from 1 kg would make the price of a 1 kg parcel depend on
        // which row is read first.
        $carrier = $this->carrier();
        $originalPrice = $carrier->price_cents;

        $this->actingAs($this->admin())
            ->put('/admin/settings/carriers/'.$carrier->id.'/price-tiers', [
                'default_price' => '4.90',
                'tiers' => [
                    ['min_weight' => '1000', 'price' => '7.90'],
                    ['min_weight' => '1000', 'price' => '9.90'],
                ],
            ])
            ->assertSessionHasErrors('tiers.1.min_weight', null, 'carrierTiers'.$carrier->id);

        $this->assertSame(0, $carrier->priceTiers()->count(), 'A refused grid was written anyway.');
        $this->assertSame($originalPrice, $carrier->fresh()->price_cents);
    }

    public function test_a_carrier_grid_errors_land_in_that_carrier_own_bag(): void
    {
        // Every carrier is a separate form on the same page: a shared bag
        // would reopen the wrong modal.
        $carrier = $this->carrier();

        $this->actingAs($this->admin())
            ->put('/admin/settings/carriers/'.$carrier->id.'/price-tiers', ['default_price' => ''])
            ->assertSessionHasErrors('default_price', null, 'carrierTiers'.$carrier->id);
    }

    /* --- Société et facture -------------------------------------------- */

    public function test_an_unticked_vat_exemption_is_saved_as_false(): void
    {
        CompanySetting::current()->update(['vat_exempt' => true]);

        $this->actingAs($this->admin())
            ->put('/admin/settings/company', ['company_name' => 'SwiftShelf'])
            ->assertRedirect(route('admin.settings.company.edit'));

        $setting = CompanySetting::current()->fresh();

        $this->assertFalse($setting->vat_exempt);
        $this->assertSame('SwiftShelf', $setting->company_name);
    }

    public function test_an_unticked_invoice_footer_is_saved_as_false(): void
    {
        CompanySetting::current()->update(['invoice_footer_enabled' => true]);

        $this->actingAs($this->admin())
            ->put('/admin/settings/invoice', ['invoice_footer_text' => 'Merci !'])
            ->assertRedirect(route('admin.settings.invoice.edit'));

        $this->assertFalse(CompanySetting::current()->fresh()->invoice_footer_enabled);
    }

    public function test_a_company_email_has_to_look_like_one(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/settings/company', ['contact_email' => 'not-an-email'])
            ->assertSessionHasErrors('contact_email');
    }

    /* --- Types de colis ------------------------------------------------ */

    public function test_a_package_type_name_is_used_once(): void
    {
        PackageType::query()->create(['name' => 'Small Box']);

        $this->actingAs($this->admin())
            ->post('/admin/settings/package-types', ['name' => 'Small Box'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, PackageType::query()->where('name', 'Small Box')->count());
    }

    public function test_removing_a_package_type_leaves_the_others_alone(): void
    {
        $removed = PackageType::query()->create(['name' => 'Small Box']);
        $kept = PackageType::query()->create(['name' => 'Large Box']);

        $this->actingAs($this->admin())
            ->delete('/admin/settings/package-types/'.$removed->id)
            ->assertRedirect(route('admin.settings.shipping.edit'));

        $this->assertNull(PackageType::query()->find($removed->id));
        $this->assertNotNull(PackageType::query()->find($kept->id));
    }

    /* --- Les pages elles-mêmes ----------------------------------------- */

    public function test_every_settings_page_renders(): void
    {
        $admin = $this->admin();
        $carrier = $this->carrier();
        CarrierPriceTier::query()->create([
            'carrier_id' => $carrier->id,
            'min_weight_grams' => 1000,
            'price_cents' => 790,
        ]);
        PackageType::query()->create(['name' => 'Small Box']);

        foreach ([
            '/admin/settings',
            '/admin/settings/orders',
            '/admin/settings/shipping',
            '/admin/settings/company',
            '/admin/settings/invoice',
        ] as $page) {
            $this->actingAs($admin)->get($page)->assertOk();
        }
    }
}
