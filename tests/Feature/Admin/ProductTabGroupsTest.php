<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le découpage visuel de la barre d'onglets des produits.
 *
 * Huit onglets alignés sans respiration se lisent comme une seule liste. Ils
 * forment pourtant trois familles — ce qu'on vend, l'état des stocks, ce
 * qu'il reste à renseigner — et « Désactivés » n'appartient à aucune.
 */
class ProductTabGroupsTest extends TestCase
{
    use RefreshDatabase;

    private function bar(): string
    {
        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products')
            ->assertOk()
            ->getContent();

        preg_match('/<nav class="admin-tabs".*?<\/nav>/s', $html, $m);

        return $m[0] ?? '';
    }

    public function test_a_rule_opens_the_stock_group(): void
    {
        $this->assertMatchesRegularExpression('/tab=in-stock[^>]*"\s*class="starts-group/', $this->bar());
    }

    public function test_a_rule_opens_the_missing_data_group(): void
    {
        $this->assertMatchesRegularExpression('/tab=no-sku[^>]*"\s*class="starts-group/', $this->bar());
    }

    public function test_only_two_rules_are_drawn(): void
    {
        // Un filet par frontière : trois familles, deux frontières.
        $this->assertSame(2, substr_count($this->bar(), 'starts-group'));
    }

    public function test_disabled_sits_apart(): void
    {
        $this->assertMatchesRegularExpression('/tab=disabled[^>]*"\s*class="sits-apart/', $this->bar());
    }

    public function test_disabled_carries_no_rule(): void
    {
        // Seul de son côté, un filet ne séparerait rien.
        $bar = $this->bar();
        preg_match('/<a[^>]*tab=disabled[^>]*>/', $bar, $m);

        $this->assertStringNotContainsString('starts-group', $m[0]);
    }

    public function test_disabled_is_the_last_tab(): void
    {
        $bar = $this->bar();

        $this->assertGreaterThan(strpos($bar, 'tab=no-weight'), strpos($bar, 'tab=disabled'));
    }

    public function test_the_order_of_the_bar_holds(): void
    {
        $bar = $this->bar();
        $expected = ['tab=in-stock', 'tab=at-supplier', 'tab=out-of-stock', 'tab=no-sku', 'tab=no-gtin', 'tab=no-weight', 'tab=disabled'];
        $positions = array_map(fn (string $needle): int => strpos($bar, $needle), $expected);

        $sorted = $positions;
        sort($sorted);

        $this->assertSame($sorted, $positions);
    }

    public function test_the_active_tab_keeps_its_own_class(): void
    {
        // Les deux classes cohabitent sur le même onglet : la mise en avant ne
        // doit pas chasser le filet.
        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/products?tab=in-stock')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('class="starts-group active"', $html);
    }

    public function test_the_styles_exist(): void
    {
        $css = (string) file_get_contents(public_path('css/admin.css'));

        $this->assertMatchesRegularExpression('/\.admin-tabs a\.starts-group::before\s*\{/', $css);
        $this->assertMatchesRegularExpression('/\.admin-tabs a\.sits-apart\s*\{/', $css);
    }

    public function test_the_rules_are_dropped_when_the_bar_wraps(): void
    {
        // Replié, un filet en début de ligne ne sépare rien.
        $css = (string) file_get_contents(public_path('css/admin.css'));

        preg_match('/@media \(max-width: 900px\) \{.*?\n\}/s', $css, $m);

        $this->assertStringContainsString('starts-group', $m[0] ?? '');
        $this->assertStringContainsString('sits-apart', $m[0] ?? '');
    }
}
