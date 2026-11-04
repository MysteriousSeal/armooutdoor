<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The name cell of the product list: the name, its category, then its SKU. */
class ProductNameCellTest extends TestCase
{
    use RefreshDatabase;

    private function list(): string
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products')
            ->assertOk()
            ->getContent();
    }

    public function test_the_category_sits_between_the_name_and_the_sku(): void
    {
        $category = Category::factory()->create(['name' => ['fr' => 'Cibles de tir']]);
        Product::factory()->create([
            'name' => ['fr' => 'Cible ronde'],
            'sku' => 'CIBLE-RONDE-10',
            'category_id' => $category->id,
        ]);

        $html = $this->list();
        $category = strpos($html, 'admin-table-category" title="Cibles de tir">Cibles de tir</span>');

        $this->assertNotFalse($category);
        $this->assertLessThan($category, strpos($html, 'Cible ronde'));
        $this->assertLessThan(strpos($html, '<span class="admin-table-sub">CIBLE-RONDE-10</span>'), $category);
    }

    public function test_the_list_has_no_category_column(): void
    {
        Product::factory()->create();

        $this->assertStringNotContainsString('<th>Category</th>', $this->list());
    }

    public function test_the_slug_is_not_printed(): void
    {
        Product::factory()->create(['slug' => 'housse-de-silencieux-tan']);

        $html = $this->list();

        $this->assertStringNotContainsString('admin-table-slug', $html);
        $this->assertStringNotContainsString('>housse-de-silencieux-tan<', $html);
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
