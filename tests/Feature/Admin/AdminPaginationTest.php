<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Every admin list pages the same way. */
class AdminPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_admin_list_uses_the_storefront_pager(): void
    {
        // The shop's pager is styled for the shop; the admin has its own, and
        // a list reaching for the wrong one is how the three ended up looking
        // unalike.
        foreach (glob(resource_path('views/admin/**/*.blade.php')) as $view) {
            $this->assertStringNotContainsString(
                "@include('partials.pager'",
                (string) file_get_contents($view),
                basename($view).' reaches for the storefront pager.',
            );
        }
    }

    public function test_the_labels_list_pages_like_the_products_list(): void
    {
        Product::factory()->count(45)->create();

        $admin = User::factory()->admin()->create();

        $labels = $this->actingAs($admin)->get('/admin/labels')->assertOk();
        $products = $this->actingAs($admin)->get('/admin/products')->assertOk();

        foreach ([$labels, $products] as $response) {
            $response->assertSee('admin-pagination', false)
                ->assertSee('admin-pagination-page', false)
                ->assertDontSee('store-pager', false);
        }
    }

    public function test_purchase_orders_page_by_number(): void
    {
        $supplier = Supplier::query()->create(['name' => 'DM Diffusion', 'lead_time_days' => 4]);

        for ($i = 0; $i < 25; $i++) {
            PurchaseOrder::query()->create([
                'number' => 'BC-2026-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'status' => 'sent',
                'shipping_cents' => 0,
                'discount_cents' => 0,
                'additional_costs_cents' => 0,
                'vat_rate_basis_points' => 2000,
            ]);
        }

        // Numbered pages, not just Previous and Next: the list says how far it
        // goes, like the others.
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/purchase-orders')
            ->assertOk()
            ->assertSee('admin-pagination-page', false)
            ->assertSee('admin-pagination-pages', false);
    }
}
