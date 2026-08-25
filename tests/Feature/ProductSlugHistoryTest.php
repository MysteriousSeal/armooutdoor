<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'histoire des adresses d'un produit.
 *
 * Renommer un produit cassait tous les liens déjà partagés ou indexés. Chaque
 * slug porté reste connu, et les anciens redirigent vers l'actuel.
 */
class ProductSlugHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_product_records_its_first_slug(): void
    {
        $product = Product::factory()->create(['slug' => 'cible-ronde']);

        $this->assertDatabaseHas('product_slugs', [
            'product_id' => $product->id,
            'slug' => 'cible-ronde',
            'is_active' => true,
        ]);
    }

    public function test_a_rename_keeps_the_old_address_and_moves_the_flag(): void
    {
        $product = Product::factory()->create(['slug' => 'ancien']);
        $product->update(['slug' => 'nouveau']);

        $this->assertSame(2, $product->slugs()->count());
        $this->assertSame('nouveau', $product->slugs()->active()->value('slug'));
        $this->assertSame('ancien', $product->retiredSlugs()->value('slug'));
    }

    public function test_every_address_is_kept_not_only_the_last(): void
    {
        $product = Product::factory()->create(['slug' => 'un']);
        $product->update(['slug' => 'deux']);
        $product->update(['slug' => 'trois']);

        $this->assertSame(
            ['deux', 'un'],
            $product->retiredSlugs()->orderBy('slug')->pluck('slug')->sort()->values()->all()
        );
        $this->assertSame(1, $product->slugs()->active()->count());
    }

    public function test_going_back_to_an_old_slug_reuses_its_row(): void
    {
        $product = Product::factory()->create(['slug' => 'un']);
        $product->update(['slug' => 'deux']);
        $product->update(['slug' => 'un']);

        // Un aller-retour, pas une troisième adresse.
        $this->assertSame(2, $product->slugs()->count());
        $this->assertSame('un', $product->slugs()->active()->value('slug'));
    }

    public function test_an_old_address_redirects_to_the_current_one(): void
    {
        $product = Product::factory()->create(['slug' => 'ancien', 'is_active' => true]);
        $product->update(['slug' => 'nouveau']);

        $this->get('/products/ancien')
            ->assertStatus(301)
            ->assertRedirect('/products/nouveau');
    }

    public function test_a_slug_nobody_ever_had_is_still_a_404(): void
    {
        $this->get('/products/jamais-vu')->assertNotFound();
    }

    public function test_an_old_address_of_a_retired_product_is_a_404(): void
    {
        $product = Product::factory()->create(['slug' => 'ancien', 'is_active' => true]);
        $product->update(['slug' => 'nouveau']);
        $product->update(['is_active' => false]);

        // Rediriger vers une page qui répond 404 ne vaut pas mieux que 404.
        $this->get('/products/ancien')->assertNotFound();
    }

    public function test_the_history_goes_with_the_product(): void
    {
        $product = Product::factory()->create(['slug' => 'ancien']);
        $product->update(['slug' => 'nouveau']);

        $product->delete();

        $this->assertSame(0, ProductSlug::query()->count());
    }
}
