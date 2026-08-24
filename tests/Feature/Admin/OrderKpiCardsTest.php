<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La rangée d'indicateurs de la liste des commandes.
 *
 * Sept chiffres, trois cartes : le total, le bloc des coûts, le bloc des
 * résultats. Le défaut que ces tests gardent est celui d'avant — une grille
 * déclarée pour six colonnes alors qu'il y avait sept cartes, si bien que la
 * dernière passait à la ligne sans que rien ne le signale.
 */
class OrderKpiCardsTest extends TestCase
{
    use RefreshDatabase;

    private function grid(): string
    {
        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/orders')->assertOk()->getContent();

        $grid = substr($html, strpos($html, 'admin-stat-grid--primary'));

        return substr($grid, 0, strpos($grid, '<nav class="admin-tabs"'));
    }

    /** Un bloc seul, repéré par son intitulé. */
    private function block(string $label): string
    {
        $grid = $this->grid();
        $from = strpos($grid, '>'.$label.'<');
        $this->assertNotFalse($from, "the {$label} block is present");
        $rest = substr($grid, $from);
        $next = strpos($rest, '<div class="admin-stat-card');

        return $next === false ? $rest : substr($rest, 0, $next);
    }

    private function cardCount(): int
    {
        return substr_count($this->grid(), '<div class="admin-stat-card');
    }

    public function test_the_seven_figures_are_all_present(): void
    {
        $grid = $this->grid();

        foreach ([
            'Total amount', 'Own shipping', 'Commission', 'Payment fees',
            'Total costs', 'Total perceived', 'Profit',
        ] as $label) {
            $this->assertStringContainsString($label, $grid, "{$label} is missing");
        }
    }

    public function test_the_row_holds_three_cards(): void
    {
        $this->assertSame(3, $this->cardCount());
    }

    /**
     * Le nombre de colonnes doit couvrir le nombre de cartes. C'est
     * exactement ce qui manquait : six colonnes, sept cartes.
     */
    public function test_the_grid_declares_a_column_for_every_card(): void
    {
        $css = file_get_contents(public_path('css/admin.css'));

        preg_match('/\.admin-orders-page \.admin-stat-grid--primary \{[^}]*grid-template-columns:([^;]+);/', $css, $m);
        $this->assertNotEmpty($m, 'the orders grid declares its columns');

        $this->assertCount(
            $this->cardCount(),
            preg_split('/\s+/', trim($m[1])),
            'the grid must declare one column per card, or the last one wraps'
        );
    }

    public function test_the_three_cost_parts_are_grouped_in_one_card(): void
    {
        $block = $this->block('Cost breakdown');

        $this->assertSame(3, substr_count($block, '<li class="admin-stat-part">'));
        foreach (['Own shipping', 'Commission', 'Payment fees'] as $part) {
            $this->assertStringContainsString($part, $block);
        }
    }

    public function test_the_three_results_are_grouped_in_one_card(): void
    {
        $block = $this->block('Results');

        $this->assertSame(3, substr_count($block, '<li class="admin-stat-part">'));
        foreach (['Total costs', 'Total perceived', 'Profit'] as $part) {
            $this->assertStringContainsString($part, $block);
        }
    }

    /** Rouge, vert, bleu : seul le montant est teinté, pas la ligne. */
    public function test_each_result_figure_keeps_its_own_colour(): void
    {
        $block = $this->block('Results');

        foreach (['is-cost', 'is-kept', 'is-profit'] as $tone) {
            $this->assertStringContainsString('admin-stat-part-value '.$tone, $block);
        }
    }

    public function test_each_cost_part_keeps_both_percentages(): void
    {
        $block = $this->block('Cost breakdown');

        $this->assertSame(3, substr_count($block, '% of amount'));
        $this->assertSame(3, substr_count($block, '% of costs'));
    }

    /**
     * « 9,39 % » et « of amount » se retrouvaient sur deux lignes. Le mot est
     * écrit en entier, séparé du chiffre par une espace, et la pastille ne se
     * coupe plus.
     */
    public function test_a_percentage_is_never_split_across_lines(): void
    {
        $css = file_get_contents(public_path('css/admin.css'));

        $this->assertMatchesRegularExpression(
            '/\.admin-stat-pct \{[^}]*white-space: nowrap;/s',
            $css,
            'a percentage pill must not wrap between the number and its unit'
        );

        // L'espace avant le % fait partie de ce qui est rendu, pas du CSS.
        $this->assertMatchesRegularExpression('/\d %\s*of amount/', $this->grid());
        $this->assertStringNotContainsString('amt', $this->grid());
    }

    public function test_the_total_spans_the_row_once_the_grid_narrows(): void
    {
        $css = file_get_contents(public_path('css/admin.css'));

        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 1100px\)[^@]*admin-stat-card--headline \{\s*grid-column: 1 \/ -1;/s',
            $css,
            'the total becomes a full-width banner when the blocks stop fitting beside it'
        );
    }
}
