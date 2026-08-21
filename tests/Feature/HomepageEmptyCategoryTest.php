<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Support\HomepageCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Une catégorie racine sans produit se crée en deux clics dans l'admin, et
 * elle mettait la page d'accueil en 500 : le map() de featured() renvoie null
 * pour elle, ce qui dégrade la collection Eloquent en collection de base, et
 * more() appelait modelKeys() qui n'existe que sur la première.
 */
class HomepageEmptyCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_homepage_survives_an_empty_root_category(): void
    {
        Product::factory()->create();
        Category::factory()->create(['parent_id' => null]);

        $this->get('/')->assertOk();
    }

    public function test_the_homepage_survives_when_every_root_is_empty(): void
    {
        Category::factory()->create(['parent_id' => null]);
        Category::factory()->create(['parent_id' => null]);

        $this->get('/')->assertOk();
    }

    public function test_more_still_excludes_the_featured_products(): void
    {
        $category = Category::factory()->create(['parent_id' => null]);
        $products = Product::factory()->count(3)->create(['category_id' => $category->id]);
        Category::factory()->create(['parent_id' => null]);

        $featured = HomepageCatalog::featured(10);
        $more = HomepageCatalog::more($featured, 10);

        // La correction ne doit pas seulement empêcher le plantage : les
        // produits déjà mis en avant doivent rester exclus du second bloc.
        $this->assertNotEmpty($featured);
        $this->assertEmpty(
            $more->pluck('id')->intersect($featured->pluck('id')),
            'Un produit mis en avant ne doit pas réapparaître plus bas.',
        );
        $this->assertSame($products->count(), $featured->count() + $more->count());
    }
}
