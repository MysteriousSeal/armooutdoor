<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La mention « nous pouvons le commander » sur une fiche produit.
 *
 * La page annonçait un délai fournisseur sans jamais dire pourquoi il y en
 * avait un. Le lecteur voyait « Dispo fournisseur » et un nombre de jours,
 * sans qu'on lui explique que l'article n'est pas sur l'étagère mais qu'on
 * peut le faire venir.
 */
class ProductSupplierNoticeTest extends TestCase
{
    use RefreshDatabase;

    private function supplier(): Supplier
    {
        return Supplier::query()->create(['name' => 'Fournisseur', 'lead_time_days' => 5]);
    }

    public function test_it_shows_when_the_product_is_only_at_the_supplier(): void
    {
        $product = Product::factory()->create([
            'quantity' => 0,
            'supplier_id' => $this->supplier()->id,
            'available_at_supplier' => true,
        ]);

        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertSee(__('store.supplier_notice'), false);
    }

    public function test_it_is_hidden_on_a_product_in_stock(): void
    {
        $product = Product::factory()->create([
            'quantity' => 10,
            'supplier_id' => $this->supplier()->id,
            'available_at_supplier' => true,
        ]);

        $html = $this->get('/products/'.$product->slug)->assertOk()->getContent();

        // Elle est dans la page mais masquée : le script la révèle si l'on
        // choisit une déclinaison épuisée.
        $this->assertMatchesRegularExpression('/id="product-supplier-notice"[^>]*\shidden/', $html);
    }

    public function test_it_is_hidden_on_a_product_with_no_supplier(): void
    {
        $product = Product::factory()->create(['quantity' => 0, 'available_at_supplier' => false]);

        $html = $this->get('/products/'.$product->slug)->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/id="product-supplier-notice"[^>]*\shidden/', $html);
    }

    public function test_it_sits_above_the_lead_time_it_explains(): void
    {
        $product = Product::factory()->create([
            'quantity' => 0,
            'supplier_id' => $this->supplier()->id,
            'available_at_supplier' => true,
        ]);

        $html = $this->get('/products/'.$product->slug)->assertOk()->getContent();

        // On dit d'abord pourquoi il y a un délai, ensuite lequel.
        $this->assertGreaterThan(
            strpos($html, 'id="product-supplier-notice"'),
            strpos($html, 'id="product-lead-time"'),
        );
    }

    public function test_it_follows_the_variant_selection(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);
        $supplier = $this->supplier();

        ProductVariant::query()->create([
            'product_id' => $product->id, 'label' => ['en' => 'S', 'fr' => 'S'], 'sku' => 'V-S',
            'quantity' => 5, 'is_active' => true, 'sort_order' => 1,
        ]);
        ProductVariant::query()->create([
            'product_id' => $product->id, 'label' => ['en' => 'M', 'fr' => 'M'], 'sku' => 'V-M',
            'quantity' => 0, 'supplier_id' => $supplier->id, 'available_at_supplier' => true,
            'is_active' => true, 'sort_order' => 2,
        ]);

        $html = $this->get('/products/'.$product->slug)->assertOk()->getContent();

        // Le script bascule la mention sur le même drapeau que le délai : la
        // fiche doit donc porter les deux états par déclinaison.
        $this->assertStringContainsString('data-lead-time-visible="1"', $html);
        $this->assertStringContainsString('data-lead-time-visible=""', $html);
    }

    public function test_the_script_toggles_it(): void
    {
        $js = (string) file_get_contents(public_path('js/product-variant.js'));

        $this->assertStringContainsString("getElementById('product-supplier-notice')", $js);
        $this->assertStringContainsString('supplierNoticeEl.hidden', $js);
    }

    public function test_the_notice_has_a_style(): void
    {
        $css = (string) file_get_contents(public_path('css/app.css'));

        $this->assertMatchesRegularExpression('/\.product-supplier-notice\s*\{/', $css);
        $this->assertMatchesRegularExpression("/\[data-theme='dark'\]\s*\.product-supplier-notice\s*\{/", $css);
    }

    public function test_the_info_icon_exists_in_the_registry(): void
    {
        // Un nom absent du registre tombe sur l'icône par défaut, un carré.
        $registry = (string) file_get_contents(resource_path('views/partials/icon.blade.php'));

        $this->assertStringContainsString("'circle-info' =>", $registry);
    }
}
