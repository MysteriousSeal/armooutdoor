<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * La page d'accueil ne doit jamais tomber en 500, quelle que soit la forme du
 * catalogue. Elle est déjà testée sur deux jeux de données fixes — une base
 * vide et le seeder — mais ces deux-là ne produisent que des catégories
 * peuplées : une catégorie racine vide suffisait à mettre le site à terre.
 *
 * Chaque cas ci-dessous est une forme de catalogue qu'un admin peut créer en
 * quelques clics.
 */
class HomepageResilienceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{\Closure}>
     */
    public static function catalogShapes(): array
    {
        return [
            'catalogue vide' => [fn () => null],

            'une seule catégorie vide' => [fn () => Category::factory()->create()],

            'catégorie vide à côté d\'une peuplée' => [function (): void {
                Product::factory()->create();
                Category::factory()->create();
            }],

            'racine sans produit mais avec un enfant peuplé' => [function (): void {
                $root = Category::factory()->create();
                $child = Category::factory()->create(['parent_id' => $root->id]);
                Product::factory()->create(['category_id' => $child->id]);
            }],

            'racine dont tous les enfants sont vides' => [function (): void {
                $root = Category::factory()->create();
                Category::factory()->create(['parent_id' => $root->id]);
                Category::factory()->create(['parent_id' => $root->id]);
            }],

            'uniquement des produits inactifs' => [fn () => Product::factory()->create(['is_active' => false])],

            'produit sans image' => [fn () => Product::factory()->create(['image' => ''])],

            'produit sans description' => [fn () => Product::factory()->create(['description' => ['fr' => '']])],

            'produit à prix nul' => [fn () => Product::factory()->create(['price_cents' => 0])],

            'produit avec variantes' => [function (): void {
                $product = Product::factory()->create();
                ProductVariant::query()->create([
                    'product_id' => $product->id,
                    'label' => 'M',
                    'quantity' => 3,
                    'is_active' => true,
                    'sort_order' => 1,
                ]);
            }],

            'produit en promotion' => [function (): void {
                $product = Product::factory()->create();
                Discount::query()->create([
                    'product_id' => $product->id,
                    'type' => 'percentage',
                    'value' => 10,
                    'ends_at' => now()->addWeek(),
                ]);
            }],

            'produit en rupture' => [fn () => Product::factory()->create(['quantity' => 0])],

            'beaucoup de catégories vides' => [function (): void {
                Product::factory()->create();
                Category::factory()->count(8)->create();
            }],
        ];
    }

    #[DataProvider('catalogShapes')]
    public function test_the_homepage_never_returns_a_server_error(\Closure $shape): void
    {
        $shape();

        $response = $this->get('/');

        // assertOk plutôt qu'un simple "pas 500" : une page d'accueil qui
        // redirige ou renvoie 404 serait tout aussi cassée pour un visiteur.
        $this->assertSame(
            200,
            $response->getStatusCode(),
            'La page d\'accueil a renvoyé '.$response->getStatusCode().' pour cette forme de catalogue.',
        );
    }
}
