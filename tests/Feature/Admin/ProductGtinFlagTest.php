<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La colonne GTIN de la liste des produits.
 *
 * Elle imprimait le code-barres en entier, treize chiffres que personne ne lit
 * d'un coup d'œil, pour une seule question utile : est-il renseigné ? Une
 * coche y répond, et le compte n'apparaît que s'il manque une taille.
 */
class ProductGtinFlagTest extends TestCase
{
    use RefreshDatabase;

    private function variant(Product $product, ?string $gtin, int $index = 1): void
    {
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'label' => ['en' => 'T'.$index, 'fr' => 'T'.$index],
            'sku' => 'V-'.$product->id.'-'.$index,
            'gtin' => $gtin,
            'quantity' => 1,
            'is_active' => true,
            'sort_order' => $index,
        ]);
    }

    private function list(): string
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products')
            ->assertOk()
            ->getContent();
    }

    public function test_a_product_with_a_gtin_shows_a_tick(): void
    {
        Product::factory()->create(['gtin' => '3663596000123']);

        $this->assertStringContainsString('gtin-flag is-set', $this->list());
    }

    public function test_a_product_without_one_shows_a_cross(): void
    {
        Product::factory()->create(['gtin' => null]);

        $this->assertStringContainsString('gtin-flag is-missing', $this->list());
    }

    public function test_the_number_is_no_longer_printed(): void
    {
        Product::factory()->create(['gtin' => '3663596000123']);

        // Le cœur du sujet : la colonne ne porte plus les treize chiffres.
        $this->assertStringNotContainsString('3663596000123', $this->list());
    }

    public function test_a_fully_covered_sized_product_shows_a_tick(): void
    {
        $product = Product::factory()->create(['gtin' => null]);
        $this->variant($product, '3663596000001', 1);
        $this->variant($product, '3663596000002', 2);

        $html = $this->list();

        $this->assertStringContainsString('gtin-flag is-set', $html);
        $this->assertStringNotContainsString('2/2', $html);
    }

    public function test_a_partly_covered_sized_product_shows_the_count(): void
    {
        $product = Product::factory()->create(['gtin' => null]);
        $this->variant($product, '3663596000001', 1);
        $this->variant($product, null, 2);

        // Le détail ne prend de la place que là où il en vaut la peine.
        $this->assertStringContainsString('1/2', $this->list());
    }

    public function test_a_sized_product_with_none_shows_the_count_in_red(): void
    {
        $product = Product::factory()->create(['gtin' => null]);
        $this->variant($product, null, 1);
        $this->variant($product, null, 2);

        $html = $this->list();

        $this->assertStringContainsString('0/2', $html);
        $this->assertStringContainsString('gtin-flag is-missing', $html);
    }

    public function test_an_empty_string_counts_as_missing(): void
    {
        Product::factory()->create(['gtin' => '']);

        $this->assertStringContainsString('gtin-flag is-missing', $this->list());
    }

    public function test_the_state_is_readable_without_seeing_the_icon(): void
    {
        Product::factory()->create(['gtin' => '3663596000123']);

        // Une coche seule ne dit rien à un lecteur d'écran.
        $html = $this->list();

        // Le title seul ne suffit pas : il faut le texte dans le flux.
        $this->assertStringContainsString('<span class="sr-only">GTIN set</span>', $html);
        $this->assertStringContainsString('title="GTIN set"', $html);
    }

    public function test_the_old_class_is_gone_everywhere(): void
    {
        $css = (string) file_get_contents(public_path('css/admin.css'));
        $view = (string) file_get_contents(resource_path('views/admin/products/index.blade.php'));

        // La règle sombre de l'ancienne pastille serait restée orpheline.
        $this->assertStringNotContainsString('variant-gtin-ratio', $css);
        $this->assertStringNotContainsString('variant-gtin-ratio', $view);
    }

    public function test_the_three_states_are_styled_in_both_themes(): void
    {
        $css = (string) file_get_contents(public_path('css/admin.css'));

        foreach (['is-set', 'is-partial', 'is-missing'] as $class) {
            $this->assertMatchesRegularExpression('/\.gtin-flag\.'.$class.'\s*\{/', $css);
            $this->assertMatchesRegularExpression("/\[data-theme='dark'\]\s*\.gtin-flag\.".$class.'\s*\{/', $css);
        }
    }

    public function test_the_missing_gtin_tab_still_agrees(): void
    {
        Product::factory()->create(['gtin' => null]);
        Product::factory()->create(['gtin' => '3663596000123']);

        // Le témoin et l'onglet lisent la même donnée.
        $response = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products?tab=no-gtin')
            ->assertOk();

        $this->assertCount(1, $response->viewData('products'));
    }
}
