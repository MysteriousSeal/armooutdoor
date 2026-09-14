<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductSeoColumnTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_checks_read_the_meta_fields_first_and_fall_back_to_the_content(): void
    {
        // A good name in range but a raw description far past 160: title ok,
        // description not — the strict fallback that makes the column a
        // to-do list for the meta fields.
        $product = Product::factory()->create([
            'name' => ['fr' => Str::repeat('a', 40)],
            'description' => ['fr' => '<p>'.Str::repeat('b', 500).'</p>'],
        ]);

        $this->assertTrue($product->seoTitleOk());
        $this->assertFalse($product->seoDescriptionOk());
        $this->assertFalse($product->seoContentOk());

        // The meta fields override the content when written.
        $product->update([
            'meta_title' => Str::repeat('t', 61),
            'meta_description' => Str::repeat('d', 120),
        ]);

        $this->assertFalse($product->fresh()->seoTitleOk());
        $this->assertTrue($product->fresh()->seoDescriptionOk());

        $product->update(['meta_title' => Str::repeat('t', 60)]);
        $this->assertTrue($product->fresh()->seoContentOk());
    }

    public function test_the_list_shows_the_seo_column_with_a_verdict(): void
    {
        Product::factory()->create([
            'name' => ['fr' => Str::repeat('a', 40)],
            'meta_description' => Str::repeat('d', 120),
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products')->assertOk()
            ->assertSee('<th title="Title 20–60 and meta description 80–160 characters">SEO</th>', false)
            ->assertSee('Title and meta description sit in their good ranges');
    }

    public function test_the_missing_seo_tab_lists_only_the_failing_products(): void
    {
        $good = Product::factory()->create([
            'name' => ['fr' => Str::repeat('a', 40)],
            'meta_description' => Str::repeat('d', 120),
        ]);
        $bad = Product::factory()->create([
            'name' => ['fr' => Str::repeat('b', 40)],
            'description' => ['fr' => '<p>'.Str::repeat('c', 500).'</p>'],
        ]);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products?tab=no-seo')->assertOk()
            ->assertSee('Missing SEO');

        $ids = $response->viewData('products')->pluck('id');
        $this->assertTrue($ids->contains($bad->id));
        $this->assertFalse($ids->contains($good->id));
        $this->assertSame(1, $response->viewData('noSeoCount'));
    }

    public function test_the_cross_names_what_fails(): void
    {
        Product::factory()->create([
            'name' => ['fr' => 'Short'],
            'description' => ['fr' => '<p>Court.</p>'],
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products')->assertOk()
            ->assertSee('Title outside 20–60, meta description outside 80–160');
    }

    public function test_the_list_can_be_narrowed_to_products_whose_seo_is_ok(): void
    {
        $good = Product::factory()->create([
            'name' => ['fr' => 'Produit seo ok '.str_repeat('x', 20)],
            'meta_description' => str_repeat('d', 120),
        ]);
        $bad = Product::factory()->create([
            'name' => ['fr' => 'Produit seo ko'],
            'description' => ['fr' => '<p>Court.</p>'],
        ]);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products?seo=ok')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Produit seo ok', $html);
        $this->assertStringNotContainsString('Produit seo ko', $html);
        $this->assertStringContainsString('SEO · OK', $html);
        $this->assertTrue($good->seoContentOk());
        $this->assertFalse($bad->seoContentOk());
    }

    public function test_the_list_can_be_narrowed_to_products_whose_seo_is_off(): void
    {
        Product::factory()->create([
            'name' => ['fr' => 'Produit seo ok '.str_repeat('x', 20)],
            'meta_description' => str_repeat('d', 120),
        ]);
        Product::factory()->create([
            'name' => ['fr' => 'Produit seo ko'],
            'description' => ['fr' => '<p>Court.</p>'],
        ]);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products?seo=off')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Produit seo ko', $html);
        $this->assertStringNotContainsString('Produit seo ok', $html);
        $this->assertStringContainsString('SEO · Off', $html);
    }

    public function test_the_seo_filter_survives_a_tab(): void
    {
        Product::factory()->create([
            'name' => ['fr' => 'Produit seo ko'],
            'description' => ['fr' => '<p>Court.</p>'],
        ]);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products?tab=in-stock&seo=off')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="seo"', $html);
        $this->assertStringContainsString('value="off" selected', $html);
        $this->assertStringContainsString('tab=in-stock', $html);
        $this->assertStringContainsString('seo=off', $html);
    }
}
