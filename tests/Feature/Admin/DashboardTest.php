<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductSetting;
use App\Models\User;
use App\Services\DashboardPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Le tableau de bord, cadré par une période.
 *
 * Une seule tranche de temps porte la page : si un panneau regardait
 * ailleurs que les autres, deux chiffres côte à côte se contrediraient.
 * Les écarts se comparent à la tranche précédente de même longueur, et une
 * tranche précédente vide n'a pas de pourcentage à afficher.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function order(array $overrides = []): Order
    {
        return Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
            'status' => 'placed',
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000,
            'shipping_cents' => 500,
            'discount_cents' => 0,
            'total_cents' => 1500,
            'payment_method' => 'card',
            ...$overrides,
        ]);
    }

    private function orderAt(string $date, int $totalCents = 1500): Order
    {
        $order = $this->order(['total_cents' => $totalCents]);
        $order->forceFill(['created_at' => $date])->save();

        return $order;
    }

    private function dashboard(?string $period = null)
    {
        return $this->actingAs($this->admin())
            ->get(route('admin.dashboard', $period ? ['period' => $period] : []))
            ->assertOk();
    }

    public function test_the_period_scopes_revenue_and_orders(): void
    {
        $this->orderAt(now()->subDays(2)->toDateTimeString(), 1000);
        $this->orderAt(now()->subDays(45)->toDateTimeString(), 9999);

        $sevenDays = $this->dashboard('7d')->viewData('headline');
        $ninetyDays = $this->dashboard('90d')->viewData('headline');

        $this->assertSame(1000, $sevenDays['revenue_cents']);
        $this->assertSame(1, $sevenDays['orders']);

        $this->assertSame(10999, $ninetyDays['revenue_cents']);
        $this->assertSame(2, $ninetyDays['orders']);
    }

    public function test_the_delta_compares_against_the_previous_window_of_the_same_length(): void
    {
        // 7 jours courants : 100,00 €. Les 7 jours d'avant : 50,00 €.
        $this->orderAt(now()->subDays(1)->toDateTimeString(), 10000);
        $this->orderAt(now()->subDays(9)->toDateTimeString(), 5000);

        $headline = $this->dashboard('7d')->viewData('headline');

        $this->assertSame(10000, $headline['revenue_cents']);
        $this->assertSame(100.0, $headline['revenue_delta']['percent']);
        $this->assertSame('up', $headline['revenue_delta']['direction']);
    }

    public function test_an_empty_previous_window_renders_a_dash_rather_than_dividing_by_zero(): void
    {
        $this->orderAt(now()->subDay()->toDateTimeString(), 4200);

        $response = $this->dashboard('7d');
        $headline = $response->viewData('headline');

        // Croître depuis zéro n'a pas de pourcentage : ni « +∞ % », ni un plantage.
        $this->assertNull($headline['revenue_delta']['percent']);
        $response->assertSee('—');
    }

    public function test_top_products_reflect_the_period_rather_than_all_time(): void
    {
        $recent = Product::factory()->create(['name' => ['fr' => 'Sold recently']]);
        $old = Product::factory()->create(['name' => ['fr' => 'Sold long ago']]);

        $recentOrder = $this->orderAt(now()->subDay()->toDateTimeString());
        OrderItem::query()->create([
            'order_id' => $recentOrder->id, 'product_id' => $recent->id, 'product_slug' => $recent->slug,
            'name' => ['fr' => 'Sold recently'], 'image' => '', 'quantity' => 1,
            'unit_price_cents' => 1000, 'line_cents' => 1000,
        ]);

        $oldOrder = $this->orderAt(now()->subDays(60)->toDateTimeString());
        OrderItem::query()->create([
            'order_id' => $oldOrder->id, 'product_id' => $old->id, 'product_slug' => $old->slug,
            'name' => ['fr' => 'Sold long ago'], 'image' => '', 'quantity' => 99,
            'unit_price_cents' => 1000, 'line_cents' => 99000,
        ]);

        $sevenDays = $this->dashboard('7d')->viewData('topProducts');

        // Le vieux produit domine en tout-temps mais n'a rien vendu ici.
        $this->assertSame(['Sold recently'], $sevenDays->pluck('name')->all());

        $ninetyDays = $this->dashboard('90d')->viewData('topProducts');
        $this->assertSame('Sold long ago', $ninetyDays->first()['name']);
    }

    public function test_the_attention_strip_is_absent_when_nothing_needs_attention(): void
    {
        // Une bande d'alerte toujours présente apprend à l'ignorer.
        $this->dashboard()
            ->assertDontSee('Needs attention')
            ->assertDontSee('dash-attention-list', false);
    }

    public function test_the_attention_strip_appears_when_something_needs_attention(): void
    {
        $this->order(['status' => 'placed']);

        $response = $this->dashboard();

        $response->assertSee('Needs attention');
        $this->assertSame(
            1,
            (int) $response->viewData('attention')->firstWhere('key', 'to-prepare')['count'],
        );
    }

    public function test_every_period_option_renders(): void
    {
        $this->orderAt(now()->subDay()->toDateTimeString());

        foreach (array_keys(DashboardPeriod::OPTIONS) as $period) {
            $this->dashboard($period)->assertSee('Revenue');
        }
    }

    public function test_an_unknown_period_falls_back_to_the_default(): void
    {
        $this->assertSame('30d', $this->dashboard('nonsense')->viewData('period')->key);
    }

    public function test_the_table_views_carry_every_value_without_javascript(): void
    {
        $this->orderAt(now()->subDay()->toDateTimeString(), 2500);

        $html = $this->dashboard('7d')->getContent();

        // Le canvas reste vide sans JS : le tableau rendu côté serveur est le
        // seul moyen de lire les valeurs, il doit donc toujours être là.
        $this->assertStringContainsString('dash-table-view', $html);
        $this->assertStringContainsString('25,00', $html);
    }

    public function test_the_query_count_does_not_grow_with_the_number_of_orders(): void
    {
        $measure = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        // Un produit distinct par commande : c'est le nombre de références
        // vendues, pas de commandes, qui faisait exploser l'ancien calcul —
        // il chargeait un produit par référence avant même d'en garder cinq.
        $seed = function (int $howMany): void {
            foreach (range(1, $howMany) as $i) {
                $product = Product::factory()->create();
                $order = $this->orderAt(now()->subDays($i % 20)->toDateTimeString());
                OrderItem::query()->create([
                    'order_id' => $order->id, 'product_id' => $product->id, 'product_slug' => $product->slug,
                    'name' => ['fr' => 'X'], 'image' => '', 'quantity' => 1,
                    'unit_price_cents' => 1000, 'line_cents' => 1000,
                ]);
            }
        };

        // Warm the low-stock threshold: once() memoizes it for the process,
        // so the first render would pay its firstOrCreate and the second
        // would not — a difference that is not the dashboard's own work.
        ProductSetting::lowStockThreshold();

        $seed(5);
        $small = $measure();

        $seed(45);
        $large = $measure();

        // La version précédente chargeait chaque ligne de commande puis un
        // produit par référence distincte : le compte grimpait avec le
        // catalogue. Il doit maintenant être plat.
        $this->assertSame($small, $large, "Query count grew from {$small} to {$large} — an N+1 came back.");
    }
}
