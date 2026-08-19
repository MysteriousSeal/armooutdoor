<?php

namespace Tests\Unit;

use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_total_cents_sums_every_line(): void
    {
        $a = Product::factory()->create(['price_cents' => 1000]);
        $b = Product::factory()->create(['price_cents' => 2500]);
        $cart = new Cart;

        $cart->add($a, 2); // 2000
        $cart->add($b, 1); // 2500

        $this->assertSame(4500, $cart->totalCents());
        $this->assertSame(3, $cart->quantity());
    }

    public function test_a_variants_own_price_overrides_the_product_price(): void
    {
        $product = Product::factory()->create(['price_cents' => 1000]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'attribute_values' => [['label' => 'Taille', 'value' => 'M']],
            'price_cents' => 1500,
            'quantity' => 10,
            'is_active' => true,
        ]);
        $cart = new Cart;

        $cart->add($product, 2, $variant);

        $this->assertSame(3000, $cart->totalCents());
    }

    public function test_a_variant_without_its_own_price_inherits_the_products_price(): void
    {
        $product = Product::factory()->create(['price_cents' => 1000]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'attribute_values' => [['label' => 'Taille', 'value' => 'M']],
            'price_cents' => null,
            'quantity' => 10,
            'is_active' => true,
        ]);
        $cart = new Cart;

        $cart->add($product, 1, $variant);

        $this->assertSame(1000, $cart->totalCents());
    }

    public function test_a_product_level_discount_applies_when_theres_no_variant(): void
    {
        $product = Product::factory()->create(['price_cents' => 1000]);
        Discount::query()->create(['product_id' => $product->id, 'type' => 'percentage', 'value' => 20]);
        $cart = new Cart;

        $cart->add($product, 1);

        $this->assertSame(800, $cart->totalCents());
    }

    public function test_a_product_level_discount_does_not_apply_to_a_variant_with_its_own_price(): void
    {
        $product = Product::factory()->create(['price_cents' => 1000]);
        Discount::query()->create(['product_id' => $product->id, 'type' => 'percentage', 'value' => 50]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'attribute_values' => [['label' => 'Taille', 'value' => 'M']],
            'price_cents' => 900,
            'quantity' => 10,
            'is_active' => true,
        ]);
        $cart = new Cart;

        $cart->add($product, 1, $variant);

        $this->assertSame(900, $cart->totalCents());
    }

    public function test_the_same_product_with_two_different_variants_are_separate_lines(): void
    {
        $product = Product::factory()->create(['price_cents' => 1000]);
        $small = ProductVariant::query()->create([
            'product_id' => $product->id,
            'attribute_values' => [['label' => 'Taille', 'value' => 'S']],
            'quantity' => 10,
            'is_active' => true,
        ]);
        $large = ProductVariant::query()->create([
            'product_id' => $product->id,
            'attribute_values' => [['label' => 'Taille', 'value' => 'L']],
            'quantity' => 10,
            'is_active' => true,
        ]);
        $cart = new Cart;

        $cart->add($product, 1, $small);
        $cart->add($product, 2, $large);

        $this->assertSame(2, $cart->lines()->count());
        $this->assertSame(1, $cart->quantityOf($product, $small));
        $this->assertSame(2, $cart->quantityOf($product, $large));
        $this->assertSame(3000, $cart->totalCents());
    }

    public function test_update_caps_quantity_at_the_products_max_purchasable(): void
    {
        $product = Product::factory()->create(['quantity' => 3]);
        $cart = new Cart;

        $cart->update($product, 99);

        $this->assertSame(3, $cart->quantityOf($product));
    }

    public function test_updating_quantity_to_zero_removes_the_line(): void
    {
        $product = Product::factory()->create(['quantity' => 5]);
        $cart = new Cart;

        $cart->add($product, 2);
        $cart->update($product, 0);

        $this->assertSame(0, $cart->quantityOf($product));
        $this->assertTrue($cart->lines()->isEmpty());
    }

    public function test_removing_a_product_clears_it_from_the_cart(): void
    {
        $product = Product::factory()->create();
        $cart = new Cart;

        $cart->add($product, 1);
        $cart->remove($product);

        $this->assertTrue($cart->isEmpty());
    }

    public function test_an_inactive_products_line_is_dropped_when_reading_the_cart(): void
    {
        $product = Product::factory()->create();
        $cart = new Cart;
        $cart->add($product, 1);

        $product->update(['is_active' => false]);

        $this->assertTrue($cart->lines()->isEmpty());
    }

    public function test_total_weight_sums_product_weight_times_quantity(): void
    {
        $a = Product::factory()->create(['weight_grams' => 200]);
        $b = Product::factory()->create(['weight_grams' => 500]);
        $cart = new Cart;

        $cart->add($a, 3); // 600g
        $cart->add($b, 1); // 500g

        $this->assertSame(1100, $cart->totalWeightGrams());
    }
}
