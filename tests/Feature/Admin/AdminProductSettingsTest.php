<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductSetting;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProductSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): self
    {
        return $this->actingAs(User::factory()->admin()->create());
    }

    public function test_the_page_shows_the_current_threshold(): void
    {
        $this->actingAsAdmin()->get('/admin/settings/products')->assertOk()
            ->assertSee('Low-stock threshold')
            ->assertSee('value="2"', false);
    }

    public function test_the_threshold_can_be_saved_and_must_be_a_sane_integer(): void
    {
        $this->actingAsAdmin()->put('/admin/settings/products', ['low_stock_threshold' => 5])
            ->assertRedirect('/admin/settings/products');

        $this->assertSame(5, ProductSetting::current()->low_stock_threshold);

        $this->actingAsAdmin()->put('/admin/settings/products', ['low_stock_threshold' => 0])
            ->assertSessionHasErrors('low_stock_threshold');
    }

    public function test_products_and_variants_follow_the_threshold(): void
    {
        $product = Product::factory()->create(['quantity' => 5, 'is_active' => true]);

        $this->assertSame('in_stock', $product->availabilityState());

        ProductSetting::current()->update(['low_stock_threshold' => 5]);

        $this->assertSame('low_stock', $product->fresh()->availabilityState());

        // A variant reads the same number.
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'attribute_values' => ['Taille' => 'M'],
            'quantity' => 5,
            'is_active' => true,
        ]);
        $this->assertTrue($variant->lowStock());
    }

    public function test_guests_cannot_reach_the_page(): void
    {
        $this->get('/admin/settings/products')->assertRedirect('/admin');
    }
}
