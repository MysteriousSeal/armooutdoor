<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le résumé des caractéristiques, près du prix.
 *
 * Le tableau complet compte souvent seize lignes de même poids : celle qu'on
 * vérifie avant d'acheter s'y trouve à la deuxième comme à la quatorzième.
 */
class ProductKeySpecsTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $characteristics): Product
    {
        return Product::factory()->create([
            'is_active' => true,
            'quantity' => 5,
            'characteristics' => $characteristics,
        ]);
    }

    /** @param array<int, string> $labels */
    private function rows(array $labels): array
    {
        return array_map(fn (string $label): array => ['label' => $label, 'value' => 'x'], $labels);
    }

    public function test_the_deciding_rows_come_first(): void
    {
        $product = $this->product($this->rows([
            'Type', 'Utilisation', 'Quantité', 'Tenue', 'Diamètre', 'Matière', 'Poids du colis',
        ]));

        $this->assertSame(
            ['Quantité', 'Diamètre', 'Matière', 'Type'],
            array_column($product->keyCharacteristics(), 'label')
        );
    }

    public function test_the_package_weight_is_left_to_the_full_table(): void
    {
        $product = $this->product($this->rows(['Poids du colis', 'Poids', 'Utilisation']));

        // Il renseigne le port, il ne décide pas d'un achat.
        $this->assertSame(['Utilisation'], array_column($product->keyCharacteristics(), 'label'));
    }

    public function test_a_product_using_none_of_the_known_labels_keeps_its_own_order(): void
    {
        $product = $this->product($this->rows(['Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo']));

        // Un choix approximatif vaut mieux que pas de résumé du tout.
        $this->assertSame(
            ['Alpha', 'Bravo', 'Charlie', 'Delta'],
            array_column($product->keyCharacteristics(), 'label')
        );
    }

    public function test_incomplete_rows_are_dropped(): void
    {
        $product = $this->product([
            ['label' => 'Quantité', 'value' => ''],
            ['label' => '', 'value' => '10 cm'],
            ['label' => 'Diamètre', 'value' => '10 cm'],
        ]);

        $this->assertSame(['Diamètre'], array_column($product->keyCharacteristics(), 'label'));
    }

    public function test_the_strip_is_on_the_page_next_to_the_price(): void
    {
        $product = $this->product($this->rows(['Quantité', 'Diamètre']));

        $html = $this->get('/products/'.$product->slug)->assertOk()->getContent();

        $this->assertStringContainsString('product-key-specs', $html);
        // Au-dessus du bouton d'achat, pas en bas de page.
        $this->assertLessThan(
            strpos($html, 'product-buy-submit'),
            strpos($html, 'product-key-specs')
        );
    }

    public function test_a_product_without_characteristics_shows_no_empty_box(): void
    {
        $product = $this->product([]);

        $this->assertStringNotContainsString('product-key-specs', $this->get('/products/'.$product->slug)->getContent());
    }

    public function test_the_full_table_runs_in_two_columns(): void
    {
        $css = (string) file_get_contents(public_path('css/app.css'));
        $list = substr($css, strpos($css, '.product-specs-list {'));
        $list = substr($list, 0, strpos($list, '}'));

        $this->assertStringContainsString('column-count: 2', $list);
        // Une ligne coupée entre deux colonnes serait illisible.
        $this->assertStringContainsString('break-inside: avoid', $css);
    }
}
