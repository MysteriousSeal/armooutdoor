<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Support\ProductSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The two things a product listing is judged on beyond its price.
 *
 * A listing with no brand and no stated returns is read as a shop that has
 * neither. Both were absent, and both are printed beside free listings.
 */
class MerchantSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_product_names_its_maker_when_the_catalogue_knows_one(): void
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'characteristics' => [
                ['label' => 'Matière', 'value' => 'Nylon'],
                ['label' => 'Marque', 'value' => 'Mechanix'],
            ],
        ]);

        $this->assertSame(
            ['@type' => 'Brand', 'name' => 'Mechanix'],
            ProductSchema::for($product)['brand'],
        );
    }

    public function test_a_product_with_no_recorded_maker_claims_none(): void
    {
        // Armo Outdoor did not make the gloves it resells, and brand is a
        // field Google reads back against merchant feeds: a gap it forgives,
        // a wrong answer it does not.
        $product = Product::factory()->create([
            'is_active' => true,
            'characteristics' => [['label' => 'Matière', 'value' => 'Nylon']],
        ]);

        $this->assertArrayNotHasKey('brand', ProductSchema::for($product));
    }

    public function test_the_maker_is_also_read_from_the_filter_attributes(): void
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'characteristics' => [],
            'filter_attributes' => [['label' => 'Marque', 'value' => 'Umarex']],
        ]);

        $this->assertSame('Umarex', ProductSchema::for($product)['brand']['name']);
    }

    public function test_every_offer_carries_the_withdrawal_period(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $policy = ProductSchema::for($product)['offers']['hasMerchantReturnPolicy'];

        $this->assertSame('MerchantReturnPolicy', $policy['@type']);
        $this->assertSame(14, $policy['merchantReturnDays']);
        $this->assertSame('FR', $policy['applicableCountry']);
        // The customer bears the return postage, so who pays is stated and
        // no figure is invented.
        $this->assertSame('https://schema.org/ReturnFeesCustomerResponsibility', $policy['returnFees']);
    }
}
