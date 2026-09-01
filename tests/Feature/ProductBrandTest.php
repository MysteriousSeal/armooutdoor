<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Support\ProductSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The maker of a product, now a field of its own.
 *
 * It used to be a « Marque » entry in the free-form characteristics list. The
 * old entries were copied into the column and left where they were, since the
 * category filters are built from them.
 */
class ProductBrandTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_column_is_used_when_it_is_set(): void
    {
        $product = Product::factory()->create(['brand' => 'Umarex']);

        $this->assertSame('Umarex', $product->brandName());
        $this->assertSame('Umarex', ProductSchema::for($product)['brand']['name']);
    }

    public function test_a_product_not_yet_migrated_falls_back_to_its_characteristic(): void
    {
        $product = Product::factory()->create([
            'brand' => null,
            'characteristics' => [['label' => 'Marque', 'value' => 'Mechanix']],
        ]);

        $this->assertSame('Mechanix', $product->brandName());
    }

    public function test_the_column_wins_over_a_stale_characteristic(): void
    {
        $product = Product::factory()->create([
            'brand' => 'ASG',
            'characteristics' => [['label' => 'Marque', 'value' => 'ASG (Blaster)']],
        ]);

        $this->assertSame('ASG', $product->brandName());
    }

    public function test_a_product_with_no_maker_anywhere_claims_none(): void
    {
        $product = Product::factory()->create(['brand' => null, 'characteristics' => []]);

        $this->assertNull($product->brandName());
        $this->assertArrayNotHasKey('brand', ProductSchema::for($product));
    }

    public function test_blank_is_stored_as_absent_rather_than_empty(): void
    {
        $product = Product::factory()->create(['brand' => '   ', 'characteristics' => []]);

        $this->assertNull($product->brandName());
    }

    public function test_the_known_brands_are_offered_without_repeating_themselves(): void
    {
        Product::factory()->create(['brand' => 'Umarex']);
        Product::factory()->create(['brand' => 'Umarex']);
        Product::factory()->create(['brand' => 'ASG']);
        Product::factory()->create(['brand' => null]);

        $this->assertSame(['ASG', 'Umarex'], Product::knownBrands());
    }

    public function test_the_edit_form_offers_the_known_brands(): void
    {
        Product::factory()->create(['brand' => 'Umarex']);
        $product = Product::factory()->create(['brand' => 'Mechanix']);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products/'.$product->id.'/edit')
            ->assertOk()
            ->assertSee('<datalist id="known-brands">', false)
            ->assertSee('<option value="Umarex">', false)
            ->assertSee('name="brand"', false);
    }

    public function test_the_form_saves_a_brand_and_clears_it_again(): void
    {
        $product = Product::factory()->create(['brand' => 'Umarex']);
        $admin = User::factory()->admin()->create();

        $payload = fn (array $override) => array_merge([
            'name' => 'Une carabine',
            'description' => 'Une description.',
            'category_id' => $product->category_id,
            'price' => '19.90',
            'quantity' => 3,
            'carrier_ids' => [],
        ], $override);

        $this->actingAs($admin)->put('/admin/products/'.$product->id, $payload(['brand' => 'Mechanix']));
        $this->assertSame('Mechanix', $product->fresh()->brand);

        // An emptied field means "no brand", not an empty string: the schema
        // decides on null.
        $this->actingAs($admin)->put('/admin/products/'.$product->id, $payload(['brand' => '']));
        $this->assertNull($product->fresh()->brand);
    }
}
