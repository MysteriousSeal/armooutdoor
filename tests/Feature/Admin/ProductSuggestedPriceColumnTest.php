<?php

namespace Tests\Feature\Admin;

use App\Models\NaturabuyListing;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The product list's « Suggested » column: the site price that leaves 200%
 * profit on the average purchase cost, once URSSAF, the Stripe fee and the
 * packaging are paid.
 */
class ProductSuggestedPriceColumnTest extends TestCase
{
    use RefreshDatabase;

    private function receive(Product $product, int $quantity, int $unitCostCents): void
    {
        $po = PurchaseOrder::factory()->create(['vat_rate_basis_points' => 2000]);

        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'name' => $product->localizedName(),
            'quantity_ordered' => $quantity,
            'quantity_received' => $quantity,
            'unit_cost_cents' => $unitCostCents,
        ]);
    }

    public function test_the_price_is_the_lowest_step_that_meets_the_target(): void
    {
        // 1,20 € incl. VAT: the shop must keep 3,90 €. At 4,87 € URSSAF takes
        // 0,65 € and Stripe 0,07 € + 0,25 €, leaving exactly 3,90 €; the next
        // price ending in ,90 is 4,90 €.
        $this->assertSame(490, Product::siteSuggestedPriceCents(120));

        foreach ([0, 1, 66, 120, 999, 1200, 4321, 25000] as $costCents) {
            $neededCents = $costCents * 3 + NaturabuyListing::PACKAGING_COST_CENTS;
            $price = Product::siteSuggestedPriceCents($costCents);

            $this->assertSame(90, $price % 100);
            $this->assertGreaterThanOrEqual($neededCents, Product::siteNetAfterChargesCents($price));
            // The ,90 a euro below would fall short, or it would have been chosen.
            $this->assertLessThan($neededCents, Product::siteNetAfterChargesCents($price - 100));
        }
    }

    public function test_it_is_cheaper_than_the_naturabuy_suggestion(): void
    {
        // No marketplace commission on the shop's own site.
        $this->assertLessThan(NaturabuyListing::suggestedPriceCents(1200), Product::siteSuggestedPriceCents(1200));
    }

    public function test_the_chip_is_red_below_blue_above_and_green_on_the_suggestion(): void
    {
        foreach ([300, 490, 900] as $priceCents) {
            $product = Product::factory()->create(['price_cents' => $priceCents]);
            $this->receive($product, 10, 100); // 1,20 € incl. VAT, suggested 4,90 €
        }

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products')->assertOk()
            ->assertSee('>Suggested</th>', false)
            ->getContent();

        $this->assertSame(1, substr_count($html, 'class="admin-order-profit admin-suggested-price is-low"'));
        $this->assertSame(1, substr_count($html, 'class="admin-order-profit admin-suggested-price is-ok"'));
        $this->assertSame(1, substr_count($html, 'class="admin-order-profit admin-suggested-price is-high"'));
        $this->assertStringContainsString('The product sells for less than this', $html);
        $this->assertStringContainsString('The product sells at this price', $html);
        $this->assertStringContainsString('The product sells for more than this', $html);
    }

    public function test_a_discount_counts_as_the_current_price(): void
    {
        $product = Product::factory()->create(['price_cents' => 900]);
        $this->receive($product, 10, 100);
        $product->discount()->create(['type' => 'percentage', 'value' => 50]);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products')->assertOk()
            ->assertSee('class="admin-order-profit admin-suggested-price is-low"', false);
    }

    public function test_the_chip_opens_the_price_dialog_unless_the_price_already_matches(): void
    {
        $under = Product::factory()->create(['price_cents' => 300]);
        $exact = Product::factory()->create(['price_cents' => 490]);
        $this->receive($under, 10, 100);
        $this->receive($exact, 10, 100);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products')->assertOk()
            ->assertSee('<dialog id="suggested-price-modal" class="modal"', false)
            ->assertSee('js/admin-suggested-price.js', false)
            ->getContent();

        $this->assertStringContainsString('data-price-url="'.route('admin.products.price', $under).'"', $html);
        $this->assertSame(2, substr_count($html, 'data-suggested-price="490"'));
        $this->assertMatchesRegularExpression('/data-price-url="'.preg_quote(route('admin.products.price', $exact), '/').'"[^>]*disabled/s', $html);
        $this->assertDoesNotMatchRegularExpression('/data-price-url="'.preg_quote(route('admin.products.price', $under), '/').'"[^>]*disabled/s', $html);
    }

    public function test_the_price_endpoint_sets_the_price_and_answers_in_json(): void
    {
        $product = Product::factory()->create(['price_cents' => 300]);
        $product->discount()->create(['type' => 'percentage', 'value' => 10]);

        $this->actingAs(User::factory()->admin()->create())
            ->patchJson(route('admin.products.price', $product), ['price_cents' => 490])
            ->assertOk()
            ->assertJson(['price_cents' => 490, 'effective_price_cents' => 441]);

        $this->assertSame(490, $product->fresh()->price_cents);
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'product.price_updated', 'subject_id' => $product->id]);
    }

    public function test_the_price_endpoint_refuses_a_bad_price_and_non_admins(): void
    {
        $product = Product::factory()->create(['price_cents' => 300]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patchJson(route('admin.products.price', $product), ['price_cents' => 0])->assertUnprocessable();
        $this->actingAs($admin)->patchJson(route('admin.products.price', $product), ['price_cents' => 'abc'])->assertUnprocessable();
        $this->actingAs(User::factory()->create())->patchJson(route('admin.products.price', $product), ['price_cents' => 490]);

        $this->assertSame(300, $product->fresh()->price_cents);
    }

    public function test_the_price_mismatch_tab_lists_active_products_off_their_suggestion(): void
    {
        $below = Product::factory()->create(['price_cents' => 300, 'name' => ['fr' => 'Sous le prix']]);
        $above = Product::factory()->create(['price_cents' => 900, 'name' => ['fr' => 'Au-dessus du prix']]);
        $exact = Product::factory()->create(['price_cents' => 490, 'name' => ['fr' => 'Au bon prix']]);
        $discounted = Product::factory()->create(['price_cents' => 490, 'name' => ['fr' => 'Au bon prix en promo']]);
        $disabled = Product::factory()->create(['price_cents' => 300, 'is_active' => false, 'name' => ['fr' => 'Désactivé']]);
        Product::factory()->create(['price_cents' => 300, 'name' => ['fr' => 'Jamais reçu']]);

        foreach ([$below, $above, $exact, $discounted, $disabled] as $product) {
            $this->receive($product, 10, 100); // suggested 4,90 €
        }
        $discounted->discount()->create(['type' => 'percentage', 'value' => 20]);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products?tab=price-mismatch')->assertOk()
            ->assertSee('Price mismatch <span class="admin-tab-count">2</span>', false)
            ->assertSee('Sous le prix')
            ->assertSee('Au-dessus du prix')
            ->assertDontSee('Au bon prix')
            ->assertDontSee('Désactivé')
            ->assertDontSee('Jamais reçu');
    }

    public function test_the_price_mismatch_tab_says_so_when_empty(): void
    {
        $product = Product::factory()->create(['price_cents' => 490]);
        $this->receive($product, 10, 100);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products?tab=price-mismatch')->assertOk()
            ->assertSee('Every product with a purchase cost sells at its suggested price.');
    }

    public function test_a_product_never_received_shows_a_dash(): void
    {
        Product::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products')->assertOk()
            ->assertSee('<span title="No purchase received yet">-</span>', false)
            ->assertDontSee('admin-order-profit admin-suggested-price', false);
    }
}
