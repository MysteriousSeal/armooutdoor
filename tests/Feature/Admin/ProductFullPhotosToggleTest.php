<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Full photos column of the product list: a hand-ticked mark for products
 * that have their model photos and infographics.
 */
class ProductFullPhotosToggleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_a_new_product_starts_unmarked(): void
    {
        $product = Product::factory()->create();

        $this->assertFalse($product->fresh()->full_photos);

        $this->actingAs($this->admin())->get('/admin/products')
            ->assertOk()
            ->assertSee('Full photos')
            ->assertSee(route('admin.products.full-photos', $product), false)
            ->assertSee('not uploaded yet: click to mark');
    }

    public function test_a_click_marks_the_product_and_logs_it(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin())
            ->patch(route('admin.products.full-photos', $product))
            ->assertRedirect();

        $this->assertTrue($product->fresh()->full_photos);
        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'product.full_photos_updated',
            'subject_id' => $product->id,
        ]);

        $this->actingAs($this->admin())->get('/admin/products')
            ->assertSee('uploaded: click to unmark');
    }

    public function test_a_second_click_undoes_a_misclick(): void
    {
        $product = Product::factory()->create(['full_photos' => true]);

        $this->actingAs($this->admin())->patch(route('admin.products.full-photos', $product));

        $this->assertFalse($product->fresh()->full_photos);
    }

    public function test_a_customer_cannot_mark_a_product(): void
    {
        $product = Product::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patch(route('admin.products.full-photos', $product));

        $this->assertFalse($product->fresh()->full_photos);
    }
}
