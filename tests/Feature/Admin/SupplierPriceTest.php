<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a product costs before VAT at the supplier. Stored in cents like every
 * other amount in the shop, kept out of the storefront entirely, and cleared
 * with the rest of the supplier block when a product gains variants.
 */
class SupplierPriceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Widget',
            'description' => '<p>Un widget.</p>',
            'category_id' => Category::factory()->create()->id,
            'price' => '19.90',
            'quantity' => 5,
            ...$overrides,
        ];
    }

    public function test_an_owner_records_the_supplier_price(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = Supplier::query()->create(['name' => 'DM Diffusion']);
        $product = Product::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), $this->payload([
                'supplier_id' => $supplier->id,
                'supplier_price' => '49.90',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        // Stocké en centimes, comme price_cents : pas de flottant en base.
        $this->assertSame(4990, $product->fresh()->supplier_price_cents);
    }

    public function test_an_empty_field_stores_nothing_rather_than_zero(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['supplier_price_cents' => 4990]);

        // Zéro voudrait dire "gratuit chez le fournisseur", ce qui est faux :
        // l'absence de prix doit rester une absence, et vider le champ doit
        // effacer la valeur plutôt que la laisser en place.
        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), $this->payload(['supplier_price' => '']))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertNull($product->fresh()->supplier_price_cents);
    }

    public function test_the_field_round_trips_through_the_edit_form(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['supplier_price_cents' => 11290]);

        $this->actingAs($admin)
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('name="supplier_price"', false)
            ->assertSee('value="112.90"', false);
    }

    public function test_a_negative_price_is_refused(): void
    {
        $admin = User::factory()->admin()->create();

        $product = Product::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), $this->payload(['supplier_price' => '-5']))
            ->assertSessionHasErrors('supplier_price');
    }

    public function test_the_markup_is_stored_in_basis_points(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), $this->payload(['markup_percent' => '30']))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(3000, $product->fresh()->markup_basis_points);
    }

    public function test_a_fractional_markup_survives_the_round_trip(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        // Le stockage en points de base existe pour ça : 32,5 % doit revenir
        // à l'identique, sans flottant ni arrondi à 32 ou 33.
        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), $this->payload(['markup_percent' => '32.5']))
            ->assertSessionHasNoErrors();

        $this->assertSame(3250, $product->fresh()->markup_basis_points);

        $this->actingAs($admin)
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('id="markup_percent"', false)
            ->assertSee('value="32.5"', false);
    }

    public function test_a_whole_markup_is_shown_without_trailing_zeros(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['markup_basis_points' => 3000]);

        // "30" se relit d'un coup d'œil, "30.00" fait hésiter sur l'unité.
        $this->actingAs($admin)
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('value="30"', false);
    }

    public function test_an_empty_markup_stores_nothing(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['markup_basis_points' => 3000]);

        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), $this->payload(['markup_percent' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull($product->fresh()->markup_basis_points);
    }

    public function test_the_markup_never_reaches_the_storefront(): void
    {
        $product = Product::factory()->create(['markup_basis_points' => 3000, 'price_cents' => 9900]);

        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertDontSee('markup', false)
            ->assertDontSee('Markup', false);
    }

    public function test_the_supplier_price_never_reaches_the_storefront(): void
    {
        $product = Product::factory()->create(['supplier_price_cents' => 4990, 'price_cents' => 9900]);

        // Ce que la boutique paie ne regarde pas le client.
        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertDontSee('49,90')
            ->assertDontSee('49.90');
    }
}
