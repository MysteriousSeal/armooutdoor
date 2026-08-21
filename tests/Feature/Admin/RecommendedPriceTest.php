<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Le prix de vente conseillé : achat HT, plus 20 % de TVA, plus la marge
 * voulue, remonté au prochain montant finissant par ,49 ou ,99.
 */
class RecommendedPriceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Les deux cas donnés en exemple : 12,39 remonte à 12,49, et 12,51 saute
     * au palier suivant plutôt que de redescendre à 12,49.
     *
     * @return array<string, array{int, int}>
     */
    public static function roundingCases(): array
    {
        return [
            'juste sous le premier palier' => [1239, 1249],
            'juste au-dessus du premier palier' => [1251, 1299],
            'déjà sur un palier bas' => [1249, 1249],
            'déjà sur un palier haut' => [1299, 1299],
            'euro rond' => [1300, 1349],
            'demi-euro' => [1350, 1399],
            'le plus petit montant' => [1, 49],
            'pile sur 49' => [49, 49],
            'pile sur 99' => [99, 99],
        ];
    }

    #[DataProvider('roundingCases')]
    public function test_prices_only_ever_round_upward(int $cents, int $expected): void
    {
        $this->assertSame($expected, Product::roundUpToPsychologicalPrice($cents));
        $this->assertGreaterThanOrEqual($cents, $expected, 'Arrondir vers le bas rognerait la marge demandée.');
    }

    public function test_vat_and_markup_are_both_applied(): void
    {
        // 49,90 × 1,20 × 1,30 = 77,844 → palier supérieur : 77,99.
        $product = Product::factory()->create([
            'supplier_price_cents' => 4990,
            'markup_basis_points' => 3000,
        ]);

        $this->assertSame(7799, $product->recommendedPriceCents());
    }

    public function test_a_missing_markup_still_gives_the_cost_price(): void
    {
        // 29,27 × 1,20 = 35,124 → 35,49. Sans marge, au moins le prix de revient.
        $product = Product::factory()->create([
            'supplier_price_cents' => 2927,
            'markup_basis_points' => null,
        ]);

        $this->assertSame(3549, $product->recommendedPriceCents());
    }

    public function test_a_fractional_markup_is_honoured(): void
    {
        // 100,00 × 1,20 × 1,325 = 159,00 → 159,49.
        $product = Product::factory()->create([
            'supplier_price_cents' => 10000,
            'markup_basis_points' => 3250,
        ]);

        $this->assertSame(15949, $product->recommendedPriceCents());
    }

    public function test_no_purchase_price_means_no_recommendation(): void
    {
        // Sans prix d'achat il n'y a rien à recommander : mieux vaut ne rien
        // afficher qu'un chiffre calculé sur zéro.
        $product = Product::factory()->create([
            'supplier_price_cents' => null,
            'markup_basis_points' => 3000,
        ]);

        $this->assertNull($product->recommendedPriceCents());
    }

    public function test_the_edit_page_shows_the_recommendation(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create([
            'supplier_price_cents' => 4990,
            'markup_basis_points' => 3000,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('id="supplier-recommended"', false)
            ->assertSee(format_euros(7799));
    }

    public function test_the_line_is_hidden_when_there_is_nothing_to_recommend(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['supplier_price_cents' => null]);

        $html = $this->actingAs($admin)->get(route('admin.products.edit', $product))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/id="supplier-recommended"[^>]*\shidden/', $html);
    }

    public function test_the_recommendation_never_exceeds_what_the_form_accepts(): void
    {
        // Achat et marge au maximum du formulaire donnent 1 319 999,99 €, très
        // au-dessus du plafond du champ Prix. Appliquer une telle valeur mettrait
        // le champ hors bornes : le navigateur refuse alors l'envoi sans rien
        // afficher, et le bouton Enregistrer paraît mort.
        $product = Product::factory()->create([
            'supplier_price_cents' => 9999999,
            'markup_basis_points' => 100000,
        ]);

        $this->assertSame(Product::MAX_PRICE_CENTS, $product->recommendedPriceCents());
    }

    public function test_a_zero_purchase_price_recommends_nothing(): void
    {
        // Sans coût il n'y a pas de marge à calculer, et 0,49 € conseillé sur
        // un article gratuit ressemble à un bug plutôt qu'à un conseil.
        $product = Product::factory()->create([
            'supplier_price_cents' => 0,
            'markup_basis_points' => 3000,
        ]);

        $this->assertNull($product->recommendedPriceCents());
    }

    public function test_the_smallest_purchase_price_still_recommends(): void
    {
        $product = Product::factory()->create(['supplier_price_cents' => 1]);

        $this->assertSame(49, $product->recommendedPriceCents());
    }

    public function test_the_apply_button_and_its_modal_are_rendered(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create([
            'supplier_price_cents' => 4990,
            'markup_basis_points' => 3000,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('id="supplier-recommended-apply"', false)
            ->assertSee('id="apply-price-modal"', false)
            ->assertSee('id="apply-price-confirm"', false);
    }

    public function test_the_apply_button_starts_hidden_for_no_javascript(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create([
            'supplier_price_cents' => 4990,
            'markup_basis_points' => 3000,
        ]);

        // Sans script il ne ferait rien : il n'apparaît qu'une fois câblé.
        $html = $this->actingAs($admin)->get(route('admin.products.edit', $product))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/id="supplier-recommended-apply"[^>]*\shidden/', $html);
    }

    public function test_the_modal_is_available_on_the_create_form_too(): void
    {
        $admin = User::factory()->admin()->create();

        // Le prix conseillé apparaît dès qu'on saisit un prix d'achat, même
        // sur un produit qui n'existe pas encore : la modale doit suivre.
        $this->actingAs($admin)
            ->get(route('admin.products.create'))
            ->assertOk()
            ->assertSee('id="apply-price-modal"', false);
    }

    public function test_a_product_with_variants_gets_neither(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['supplier_price_cents' => 4990]);
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'label' => 'M',
            'quantity' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertDontSee('id="apply-price-modal"', false);
    }

    public function test_the_recommendation_never_reaches_the_storefront(): void
    {
        $product = Product::factory()->create([
            'supplier_price_cents' => 4990,
            'markup_basis_points' => 3000,
            'price_cents' => 7784,
        ]);

        // Le conseillé est un outil de marge, pas un prix barré.
        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertDontSee('Recommended price')
            ->assertDontSee(format_euros(7799));
    }
}
