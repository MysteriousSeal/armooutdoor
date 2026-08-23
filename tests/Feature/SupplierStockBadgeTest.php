<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La pastille « Dispo fournisseur ».
 *
 * Elle portait la couleur du stock faible : la même ambre disait à la fois
 * « il n'en reste presque plus » et « il faut le faire venir ». Deux états
 * distincts, deux couleurs — celle-ci reprend le bleu ardoise que la boutique
 * emploie déjà pour une commande livrée.
 */
class SupplierStockBadgeTest extends TestCase
{
    use RefreshDatabase;

    private function atSupplier(): Product
    {
        $supplier = Supplier::query()->create(['name' => 'Fournisseur', 'lead_time_days' => 5]);

        return Product::factory()->create([
            'quantity' => 0,
            'supplier_id' => $supplier->id,
            'available_at_supplier' => true,
        ]);
    }

    public function test_the_card_badge_has_its_own_class(): void
    {
        $this->atSupplier();

        $this->get('/nouveautes')
            ->assertOk()
            ->assertSee('card-stock-chip is-at-supplier', false)
            ->assertSee(__('store.card_available_at_supplier'));
    }

    public function test_the_card_badge_is_no_longer_the_low_stock_colour(): void
    {
        $this->atSupplier();

        // Le cœur du sujet : les deux états ne doivent plus partager la classe.
        $this->get('/nouveautes')
            ->assertOk()
            ->assertDontSee('card-stock-chip is-low-stock', false);
    }

    public function test_a_genuinely_low_stock_card_keeps_the_amber(): void
    {
        Product::factory()->create(['quantity' => 1]);

        $this->get('/nouveautes')
            ->assertOk()
            ->assertSee('card-stock-chip is-low-stock', false)
            ->assertDontSee('is-at-supplier', false);
    }

    public function test_the_product_page_badge_follows(): void
    {
        $product = $this->atSupplier();

        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertSee('stock-badge is-at-supplier', false);
    }

    public function test_a_variant_pill_follows_too(): void
    {
        $supplier = Supplier::query()->create(['name' => 'Fournisseur', 'lead_time_days' => 5]);
        $product = Product::factory()->create(['quantity' => 0]);

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'label' => ['en' => 'M', 'fr' => 'M'],
            'sku' => 'V-M',
            'quantity' => 0,
            'supplier_id' => $supplier->id,
            'available_at_supplier' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Les mêmes mots ne doivent pas apparaître en deux couleurs selon la
        // page où on les lit.
        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertSee('product-variant-chip-stock is-at-supplier', false);
    }

    public function test_an_out_of_stock_product_without_a_supplier_is_unchanged(): void
    {
        Product::factory()->create(['quantity' => 0, 'available_at_supplier' => false]);

        $this->get('/nouveautes')
            ->assertOk()
            ->assertSee('card-stock-chip is-out-of-stock', false)
            ->assertDontSee('is-at-supplier', false);
    }

    public function test_the_colour_is_declared_for_all_three_places(): void
    {
        $css = (string) file_get_contents(public_path('css/app.css'));

        // Le sélecteur doit finir là : sans cela « .is-at-supplier » se
        // retrouverait dans « .is-at-supplier-autre-chose ».
        foreach (['.stock-badge', '.card-stock-chip', '.product-variant-chip-stock'] as $base) {
            $this->assertMatchesRegularExpression(
                '/'.preg_quote($base, '/').'\.is-at-supplier\s*[,{]/',
                $css,
                $base.' n’a pas de couleur'
            );
        }
    }

    public function test_the_dark_theme_rule_comes_after_the_light_one(): void
    {
        $css = (string) file_get_contents(public_path('css/app.css'));

        // Même poids, donc l'ordre tranche : la règle sombre écrite avant la
        // claire ne s'appliquerait jamais.
        $light = strpos($css, '.card-stock-chip.is-at-supplier');
        $dark = strpos($css, "[data-theme='dark'] .card-stock-chip.is-at-supplier");

        $this->assertNotFalse($light);
        $this->assertNotFalse($dark);
        $this->assertGreaterThan($light, $dark);
    }
}
