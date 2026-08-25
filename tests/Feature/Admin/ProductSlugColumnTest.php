<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Le slug sous le nom et la référence, dans la liste des produits. */
class ProductSlugColumnTest extends TestCase
{
    use RefreshDatabase;

    private function list(): string
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products')
            ->assertOk()
            ->getContent();
    }

    public function test_the_slug_sits_under_the_name(): void
    {
        Product::factory()->create([
            'sku' => 'CIBLE-RONDE-10',
            'slug' => 'cible-ronde-10cm-fluo',
        ]);

        $html = $this->list();

        $this->assertStringContainsString('admin-table-slug', $html);
        $this->assertStringContainsString('cible-ronde-10cm-fluo', $html);
        // Sous la référence, pas au-dessus : le nom, puis le code, puis l'adresse.
        $this->assertLessThan(
            strpos($html, 'cible-ronde-10cm-fluo'),
            strpos($html, 'CIBLE-RONDE-10')
        );
    }

    public function test_a_product_without_a_sku_still_shows_its_slug(): void
    {
        Product::factory()->create(['sku' => null, 'slug' => 'housse-de-silencieux-tan']);

        // Le slug sert aussi d'adresse au lien d'édition : on vérifie qu'il
        // est bien imprimé dans sa ligne, pas seulement dans un href.
        $this->assertMatchesRegularExpression(
            '#admin-table-slug"[^>]*>housse-de-silencieux-tan<#',
            $this->list()
        );
    }

    public function test_a_long_slug_stays_on_one_line(): void
    {
        Product::factory()->create(['slug' => 'cible-ronde-fluorescente-10-15-20-cm-lot-de-cinquante']);

        // Coupé aux points de suspension, en entier au survol : un slug long
        // ne doit pas faire grandir la ligne du tableau.
        $this->assertMatchesRegularExpression(
            '#title="cible-ronde-fluorescente-10-15-20-cm-lot-de-cinquante"#',
            $this->list()
        );

        $css = file_get_contents(public_path('css/admin.css'));
        $slug = substr($css, strpos($css, '.admin-table-slug {'));
        $slug = substr($slug, 0, strpos($slug, '}'));

        $this->assertStringContainsString('white-space: nowrap', $slug);
        $this->assertStringContainsString('text-overflow: ellipsis', $slug);
    }

    public function test_the_id_column_takes_only_what_it_needs(): void
    {
        Product::factory()->create();

        $css = file_get_contents(public_path('css/admin.css'));
        $id = substr($css, strpos($css, '.admin-table-id {'));
        $id = substr($id, 0, strpos($id, '}'));

        $this->assertStringContainsString('width: 1%', $id);
        $this->assertStringContainsString('admin-table-id', $this->list());
    }
}
