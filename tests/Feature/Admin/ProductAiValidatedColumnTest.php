<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** La colonne « AI » de la liste des produits. */
class ProductAiValidatedColumnTest extends TestCase
{
    use RefreshDatabase;

    private function list(): string
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products')
            ->assertOk()
            ->getContent();
    }

    public function test_a_reviewed_product_shows_a_tick(): void
    {
        Product::factory()->create(['ai_validated' => true]);

        $html = $this->list();

        $this->assertStringContainsString('>AI</th>', $html);
        $this->assertStringContainsString('Page reviewed and passed', $html);
        $this->assertStringNotContainsString('Page not reviewed yet', $html);
    }

    public function test_a_product_left_alone_shows_a_cross(): void
    {
        Product::factory()->create(['ai_validated' => false]);

        $html = $this->list();

        $this->assertStringContainsString('Page not reviewed yet', $html);
        $this->assertStringNotContainsString('Page reviewed and passed', $html);
    }

    public function test_the_state_is_said_and_not_only_drawn(): void
    {
        Product::factory()->create(['ai_validated' => true]);

        // Une coche seule ne se lit pas à voix haute : le lecteur d'écran doit
        // entendre l'état, pas un dessin.
        $this->assertMatchesRegularExpression('#sr-only">Reviewed<#', $this->list());
    }

    public function test_the_list_can_be_narrowed_to_reviewed_products(): void
    {
        Product::factory()->create([
            'ai_validated' => true,
            'name' => ['fr' => 'Gants revus', 'en' => 'Reviewed gloves'],
        ]);
        Product::factory()->create([
            'ai_validated' => false,
            'name' => ['fr' => 'Gants bruts', 'en' => 'Raw gloves'],
        ]);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products?ai=reviewed')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Gants revus', $html);
        $this->assertStringNotContainsString('Gants bruts', $html);
        $this->assertStringContainsString('AI · Reviewed', $html);
    }

    public function test_the_list_can_be_narrowed_to_products_not_yet_reviewed(): void
    {
        Product::factory()->create([
            'ai_validated' => true,
            'name' => ['fr' => 'Gants revus', 'en' => 'Reviewed gloves'],
        ]);
        Product::factory()->create([
            'ai_validated' => false,
            'name' => ['fr' => 'Gants bruts', 'en' => 'Raw gloves'],
        ]);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products?ai=pending')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Gants bruts', $html);
        $this->assertStringNotContainsString('Gants revus', $html);
        $this->assertStringContainsString('AI · Not reviewed', $html);
    }

    public function test_an_unknown_ai_filter_is_ignored(): void
    {
        Product::factory()->create([
            'ai_validated' => true,
            'name' => ['fr' => 'Gants revus', 'en' => 'Raw gloves'],
        ]);
        Product::factory()->create([
            'ai_validated' => false,
            'name' => ['fr' => 'Gants bruts', 'en' => 'Raw gloves two'],
        ]);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products?ai=maybe')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Gants revus', $html);
        $this->assertStringContainsString('Gants bruts', $html);
        $this->assertStringNotContainsString('AI · Reviewed', $html);
    }

    public function test_the_ai_filter_survives_a_tab(): void
    {
        Product::factory()->create([
            'ai_validated' => false,
            'name' => ['fr' => 'Gants bruts', 'en' => 'Raw gloves'],
        ]);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products?tab=in-stock&ai=pending')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="ai"', $html);
        $this->assertStringContainsString('value="pending" selected', $html);
        $this->assertStringContainsString('tab=in-stock', $html);
        $this->assertStringContainsString('ai=pending', $html);
    }
}
