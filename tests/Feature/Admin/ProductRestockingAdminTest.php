<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Le réassort vu depuis l'administration. */
class ProductRestockingAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function outOfStockProduct(): Product
    {
        return Product::factory()->create([
            'quantity' => 0,
            'is_active' => true,
            'supplier_id' => Supplier::query()->create(['name' => 'F', 'lead_time_days' => 5])->id,
            'available_at_supplier' => true,
        ]);
    }

    private function line(Product $product, string $status, int $ordered = 12, int $received = 0, ?int $variantId = null): PurchaseOrder
    {
        $po = PurchaseOrder::factory()->create(['status' => $status]);
        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'product_variant_id' => $variantId,
            'name' => $product->localizedName(),
            'quantity_ordered' => $ordered,
            'quantity_received' => $received,
            'unit_cost_cents' => 500,
        ]);

        return $po;
    }

    // -------------------------------------------------------------- list

    public function test_the_availability_column_shows_a_restocking_chip(): void
    {
        $product = $this->outOfStockProduct();
        $this->line($product, 'sent');

        $this->actingAs($this->admin())
            ->get('/admin/products')
            ->assertOk()
            ->assertSee('admin-availability-chip is-restocking', false)
            ->assertSee('Restocking');
    }

    /**
     * La pastille doit avoir une couleur à elle.
     *
     * Sans règle, elle sortait sans habillage ; et partager celle du
     * fournisseur ferait recommander un article déjà en route.
     */
    public function test_the_restocking_chip_has_its_own_colour(): void
    {
        $css = file_get_contents(public_path('css/admin.css'));

        $this->assertMatchesRegularExpression(
            '/\.admin-availability-chip\.is-restocking\s*\{[^}]*color:\s*#54479c/',
            $css,
        );
        $this->assertMatchesRegularExpression(
            "/\[data-theme='dark'\] \.admin-availability-chip\.is-restocking\s*\{/",
            $css,
        );
        // Sa teinte n'est pas celle du fournisseur.
        $this->assertDoesNotMatchRegularExpression(
            '/\.admin-availability-chip\.is-restocking\s*\{[^}]*color:\s*#2f5d8a/',
            $css,
        );
    }

    public function test_the_restocking_tab_filters_the_list(): void
    {
        $restocking = $this->outOfStockProduct();
        $this->line($restocking, 'sent');
        $plain = $this->outOfStockProduct();

        $this->actingAs($this->admin())
            ->get('/admin/products?tab=restocking')
            ->assertOk()
            ->assertSee($restocking->localizedName())
            ->assertDontSee($plain->localizedName());
    }

    /** Un brouillon n'engage rien : il ne remplit pas l'onglet. */
    public function test_a_draft_purchase_order_does_not_fill_the_tab(): void
    {
        $product = $this->outOfStockProduct();
        $this->line($product, 'draft');

        $this->actingAs($this->admin())
            ->get('/admin/products?tab=restocking')
            ->assertOk()
            ->assertDontSee($product->localizedName());
    }

    public function test_a_variant_on_order_puts_its_product_in_the_tab(): void
    {
        $product = Product::factory()->create(['quantity' => 0, 'is_active' => true, 'sku' => null]);
        $variant = $product->variants()->create([
            'attribute_values' => [['label' => 'Taille', 'value' => 'M']],
            'sku' => 'V-M',
            'quantity' => 0,
        ]);
        // Sans `product_id` : c'est la branche déclinaison qu'on veut voir
        // travailler. La colonne est nullable, donc le cas est possible.
        $po = PurchaseOrder::factory()->create(['status' => 'sent']);
        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => null,
            'product_variant_id' => $variant->id,
            'name' => 'V M',
            'quantity_ordered' => 5,
            'quantity_received' => 0,
            'unit_cost_cents' => 500,
        ]);

        $this->actingAs($this->admin())
            ->get('/admin/products?tab=restocking')
            ->assertOk()
            ->assertSee($product->localizedName());
    }

    // -------------------------------------------------------------- form

    public function test_the_edit_form_shows_what_is_on_order(): void
    {
        $product = $this->outOfStockProduct();
        $po = $this->line($product, 'sent', ordered: 12, received: 0);

        $this->actingAs($this->admin())
            ->get('/admin/products/'.$product->id.'/edit')
            ->assertOk()
            ->assertSee('product-inbound', false)
            ->assertSee('on order')
            ->assertSee('12')
            ->assertSee($po->number)
            ->assertSee(route('admin.purchase-orders.show', $po), false);
    }

    /** Ce qui reste à recevoir, pas ce qui a été commandé. */
    public function test_the_form_counts_only_what_is_still_missing(): void
    {
        $product = $this->outOfStockProduct();
        $this->line($product, 'partially_received', ordered: 12, received: 9);

        $this->actingAs($this->admin())
            ->get('/admin/products/'.$product->id.'/edit')
            ->assertOk()
            ->assertSee('<strong>3</strong>', false);
    }

    public function test_a_product_with_nothing_on_order_shows_no_line(): void
    {
        $product = $this->outOfStockProduct();

        $this->actingAs($this->admin())
            ->get('/admin/products/'.$product->id.'/edit')
            ->assertOk()
            ->assertDontSee('product-inbound', false);
    }

    public function test_the_create_form_still_opens(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/products/create')
            ->assertOk()
            ->assertDontSee('product-inbound', false);
    }
}
