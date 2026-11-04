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

    public function test_the_page_script_gets_the_new_state_without_a_redirect(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin())
            ->patchJson(route('admin.products.full-photos', $product))
            ->assertOk()
            ->assertExactJson(['full_photos' => true, 'full_photos_stats' => ['count' => '1', 'percent' => '100.000'], 'message' => 'Marked as having its full photos.']);

        $this->assertTrue($product->fresh()->full_photos);
    }

    public function test_the_list_hands_the_form_to_the_page_script(): void
    {
        Product::factory()->create();

        $this->actingAs($this->admin())->get('/admin/products')
            ->assertSee('data-full-photos', false)
            ->assertSee('js/admin-full-photos.js', false);
    }

    public function test_the_header_counts_the_active_marked_products(): void
    {
        Product::factory()->count(2)->create(['full_photos' => true]);
        Product::factory()->create();
        // Left out: a disabled product is not on the shop.
        Product::factory()->create(['full_photos' => true, 'is_active' => false]);

        $this->actingAs($this->admin())->get('/admin/products')
            // 2 of the 3 active products.
            ->assertSee('<span data-full-photos-count>2</span> with full photos · <span data-full-photos-percent>66.667</span>%', false);
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

    public function test_the_filter_keeps_marked_or_unmarked_products(): void
    {
        $marked = Product::factory()->create(['full_photos' => true]);
        $unmarked = Product::factory()->create();

        $listed = fn (string $query) => $this->actingAs($this->admin())
            ->get('/admin/products'.$query)
            ->assertOk()
            ->viewData('products')
            ->pluck('id')
            ->all();

        $this->assertSame([$marked->id], $listed('?photos=yes'));
        $this->assertSame([$unmarked->id], $listed('?photos=no'));
        $this->assertEqualsCanonicalizing([$marked->id, $unmarked->id], $listed(''));
        // An unknown value filters nothing.
        $this->assertEqualsCanonicalizing([$marked->id, $unmarked->id], $listed('?photos=maybe'));
    }

    public function test_an_active_filter_shows_its_chip_and_survives_the_tabs(): void
    {
        Product::factory()->create();

        $this->actingAs($this->admin())->get('/admin/products?photos=no')
            ->assertSee('Full photos · No')
            ->assertSee('tab=disabled&amp;sort=', false)
            ->assertSee('photos=no', false);
    }
}
