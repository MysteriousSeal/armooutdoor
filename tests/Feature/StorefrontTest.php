<?php

namespace Tests\Feature;

use App\Models\Category;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_root_redirects_to_french_by_default(): void
    {
        $this->get('/')
            ->assertRedirect('/fr');
    }

    public function test_homepage_shows_catalog(): void
    {
        $this->get('/fr')
            ->assertOk()
            ->assertSee('Armo')
            ->assertSee('Outdoor')
            ->assertSee('Du matériel discret pour le stand et le terrain')
            ->assertSee('Abris')
            ->assertSee('Tente crête deux places')
            ->assertSee('349,00')
            ->assertSee('Pièces choisies')
            ->assertSee('Parcourir par catégorie')
            ->assertSee('Boutique en ligne pour le stand de tir et le terrain')
            ->assertSee('Colissimo');
    }

    public function test_category_page_lists_products(): void
    {
        $this->get('/fr/categories/packs')
            ->assertOk()
            ->assertSee('Sacs')
            ->assertSee('Sac Daylight 28 L')
            ->assertSee('159,00');
    }

    public function test_category_page_can_be_sorted_by_price(): void
    {
        $this->get('/fr/categories/shelters')
            ->assertOk()
            ->assertSee('Trier')
            ->assertSee('Prix croissant')
            ->assertSee('Prix décroissant');

        $asc = $this->get('/fr/categories/shelters?sort=price-asc')->assertOk()->getContent();
        $desc = $this->get('/fr/categories/shelters?sort=price-desc')->assertOk()->getContent();

        $this->assertLessThan(
            strpos($asc, 'Tente crête deux places'),
            strpos($asc, 'Bâche-abri alpine'),
        );

        $this->assertLessThan(
            strpos($desc, 'Bâche-abri alpine'),
            strpos($desc, 'Tente crête deux places'),
        );
    }

    public function test_product_page_shows_euro_price(): void
    {
        $this->get('/fr/products/cast-iron-skillet')
            ->assertOk()
            ->assertSee('Poêle en fonte')
            ->assertSee('Cuisine de camp')
            ->assertSee('79,00');
    }

    public function test_unknown_locale_is_not_found(): void
    {
        $this->get('/en')->assertNotFound();
        $this->get('/de')->assertNotFound();
    }

    public function test_each_category_has_ten_products(): void
    {
        $this->assertSame(4, Category::query()->count());

        Category::query()->withCount('products')->get()->each(function (Category $category): void {
            $this->assertSame(10, $category->products_count, $category->slug);
        });

        $this->get('/fr/categories/shelters')
            ->assertOk()
            ->assertSee('Tente cerceau solo');

        $this->get('/fr/categories/camp-kitchen')
            ->assertOk()
            ->assertSee('Crémaillère de camp');
    }
}
