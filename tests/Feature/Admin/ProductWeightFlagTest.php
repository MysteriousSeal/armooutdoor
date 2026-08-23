<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La colonne Poids de la liste des produits.
 *
 * Contrairement au code-barres, le poids se lit d'un coup d'œil et décide du
 * prix du port : le chiffre reste. C'est son absence qui méritait un signe —
 * un tiret se confond avec une colonne vide, alors que deux cent onze
 * produits actifs sur deux cent soixante et un n'ont pas de poids.
 */
class ProductWeightFlagTest extends TestCase
{
    use RefreshDatabase;

    private function list(): string
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products')
            ->assertOk()
            ->getContent();
    }

    public function test_a_weight_is_still_printed(): void
    {
        Product::factory()->create(['weight_grams' => 60]);

        // Le chiffre sert : on ne le remplace pas par une coche.
        $this->assertStringContainsString('60 g', $this->list());
    }

    public function test_a_large_weight_keeps_its_thousands_separator(): void
    {
        Product::factory()->create(['weight_grams' => 3015]);

        $this->assertStringContainsString('3,015 g', $this->list());
    }

    public function test_a_missing_weight_shows_a_cross(): void
    {
        Product::factory()->create(['weight_grams' => null]);

        $this->assertStringContainsString('gtin-flag is-missing', $this->list());
    }

    public function test_a_zero_weight_counts_as_missing(): void
    {
        Product::factory()->create(['weight_grams' => 0]);

        // Zéro gramme n'existe pas : c'est une case jamais remplie.
        $this->assertStringContainsString('No weight', $this->list());
    }

    public function test_the_em_dash_is_gone(): void
    {
        Product::factory()->create(['weight_grams' => null]);

        $html = $this->list();
        preg_match('/<td>\s*\n\s*<span class="gtin-flag is-missing" title="No weight">/', $html, $m);

        $this->assertNotEmpty($m, 'la case vide ne porte pas la croix');
    }

    public function test_the_state_is_readable_without_seeing_the_icon(): void
    {
        Product::factory()->create(['weight_grams' => null]);

        $html = $this->list();

        $this->assertStringContainsString('<span class="sr-only">No weight</span>', $html);
        $this->assertStringContainsString('title="No weight"', $html);
    }

    public function test_a_weighed_product_carries_no_cross(): void
    {
        Product::factory()->create(['weight_grams' => 120]);

        $this->assertStringNotContainsString('No weight', $this->list());
    }

    public function test_the_missing_weight_tab_still_agrees(): void
    {
        Product::factory()->create(['weight_grams' => null]);
        Product::factory()->create(['weight_grams' => 0]);
        Product::factory()->create(['weight_grams' => 90]);

        // La croix et l'onglet lisent la même donnée, zéro compris.
        $response = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products?tab=no-weight')
            ->assertOk();

        $this->assertCount(2, $response->viewData('products'));
    }
}
