<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** On passe d'une commande à sa voisine depuis l'en-tête. */
class OrderStepperTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ShippingSeeder::class);
    }

    private function order(string $placedAt, string $status = 'placed'): Order
    {
        return Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
            'status' => $status,
            'created_at' => $placedAt,
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000, 'shipping_cents' => 0, 'discount_cents' => 0,
            'total_cents' => 1000, 'payment_method' => 'card',
        ]);
    }

    private function show(Order $order): TestResponse
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/orders/'.$order->number)
            ->assertOk();
    }

    public function test_the_middle_order_links_to_both_neighbours(): void
    {
        $older = $this->order('2026-08-20 09:00:00');
        $middle = $this->order('2026-08-21 09:00:00');
        $newer = $this->order('2026-08-22 09:00:00');

        $this->show($middle)
            ->assertSee('/admin/orders/'.$newer->number, false)
            ->assertSee('/admin/orders/'.$older->number, false);
    }

    public function test_the_newest_order_has_no_link_to_a_newer_one(): void
    {
        $older = $this->order('2026-08-20 09:00:00');
        $newest = $this->order('2026-08-21 09:00:00');

        $response = $this->show($newest);

        $response->assertSee('/admin/orders/'.$older->number, false);
        // Le bouton reste, en creux : l'en-tête ne bouge pas d'une commande à l'autre.
        $this->assertSame(1, substr_count($response->getContent(), 'order-stepper-btn is-disabled'));
    }

    public function test_the_oldest_order_has_no_link_to_an_older_one(): void
    {
        $oldest = $this->order('2026-08-20 09:00:00');
        $newer = $this->order('2026-08-21 09:00:00');

        $response = $this->show($oldest);

        $response->assertSee('/admin/orders/'.$newer->number, false);
        $this->assertSame(1, substr_count($response->getContent(), 'order-stepper-btn is-disabled'));
    }

    public function test_a_lone_order_has_two_dead_buttons(): void
    {
        $response = $this->show($this->order('2026-08-20 09:00:00'));

        $this->assertSame(2, substr_count($response->getContent(), 'order-stepper-btn is-disabled'));
    }

    public function test_orders_of_the_same_second_are_departed_by_id(): void
    {
        $first = $this->order('2026-08-20 09:00:00');
        $second = $this->order('2026-08-20 09:00:00');

        // Sans départage, chacune se verrait elle-même comme sa propre voisine.
        $this->show($first)
            ->assertSee('/admin/orders/'.$second->number, false)
            ->assertDontSee('/admin/orders/'.$first->number.'"', false);

        $this->show($second)
            ->assertSee('/admin/orders/'.$first->number, false)
            ->assertDontSee('/admin/orders/'.$second->number.'"', false);
    }

    public function test_a_real_order_never_steps_into_a_draft(): void
    {
        $draft = $this->order('2026-08-21 09:00:00', 'draft');
        $older = $this->order('2026-08-20 09:00:00');
        $placed = $this->order('2026-08-22 09:00:00');

        $this->show($placed)
            ->assertDontSee('/admin/orders/'.$draft->number, false)
            ->assertSee('/admin/orders/'.$older->number, false);
    }

    public function test_a_draft_steps_only_between_drafts(): void
    {
        $otherDraft = $this->order('2026-08-19 09:00:00', 'draft');
        $draft = $this->order('2026-08-21 09:00:00', 'draft');
        $this->order('2026-08-20 09:00:00');

        $this->show($draft)
            ->assertSee('/admin/orders/'.$otherDraft->number, false)
            ->assertSee('order-stepper-btn is-disabled', false);
    }

    public function test_each_chevron_points_the_right_way(): void
    {
        $older = $this->order('2026-08-20 09:00:00');
        $middle = $this->order('2026-08-21 09:00:00');
        $newer = $this->order('2026-08-22 09:00:00');

        $content = $this->show($middle)->getContent();

        // La liste va du plus récent au plus ancien : reculer, c'est remonter
        // vers les commandes récentes.
        $this->assertMatchesRegularExpression(
            '#href="[^"]*/admin/orders/'.$newer->number.'"[^>]*rel="prev"#s',
            $content
        );
        $this->assertMatchesRegularExpression(
            '#href="[^"]*/admin/orders/'.$older->number.'"[^>]*rel="next"#s',
            $content
        );
    }

    public function test_the_arrows_are_square_and_match_the_admin_pager(): void
    {
        $css = file_get_contents(public_path('css/admin.css'));

        $stepper = $this->declarations($css, '.order-stepper-btn {');
        $pager = $this->declarations($css, '.admin-main .store-pager-arrow,');

        // Carré : même largeur que hauteur, même taille que les flèches du
        // pager, et des coins droits comme le reste du back-office.
        $this->assertSame($stepper['width'], $stepper['height']);
        $this->assertSame($pager['height'], $stepper['height']);
        $this->assertSame('0', $stepper['border-radius']);
    }

    /**
     * @return array<string, string>
     */
    private function declarations(string $css, string $selector): array
    {
        $start = strpos($css, $selector);
        $this->assertNotFalse($start, $selector.' introuvable.');

        $body = substr($css, $start, strpos($css, '}', $start) - $start);
        preg_match_all('/([a-z-]+):\s*([^;]+);/', $body, $matches, PREG_SET_ORDER);

        return array_reduce($matches, function (array $carry, array $match): array {
            $carry[$match[1]] = trim($match[2]);

            return $carry;
        }, []);
    }

    public function test_a_real_order_never_steps_into_a_test_order(): void
    {
        $older = $this->order('2026-08-20 09:00:00');
        $test = $this->order('2026-08-21 09:00:00');
        $test->forceFill(['test_marked_at' => now()])->save();
        $placed = $this->order('2026-08-22 09:00:00');

        $this->show($placed)
            ->assertDontSee('/admin/orders/'.$test->number, false)
            ->assertSee('/admin/orders/'.$older->number, false);
    }

    public function test_a_test_order_steps_only_between_test_orders(): void
    {
        $otherTest = $this->order('2026-08-19 09:00:00');
        $otherTest->forceFill(['test_marked_at' => now()])->save();
        $test = $this->order('2026-08-21 09:00:00');
        $test->forceFill(['test_marked_at' => now()])->save();
        $this->order('2026-08-20 09:00:00');

        $this->show($test)
            ->assertSee('/admin/orders/'.$otherTest->number, false)
            ->assertSee('order-stepper-btn is-disabled', false);
    }

    public function test_a_live_order_never_steps_into_an_archived_one(): void
    {
        $older = $this->order('2026-08-20 09:00:00');
        $archived = $this->order('2026-08-21 09:00:00');
        $archived->forceFill(['archived_at' => now()])->save();
        $live = $this->order('2026-08-22 09:00:00');

        $this->show($live)
            ->assertDontSee('/admin/orders/'.$archived->number, false)
            ->assertSee('/admin/orders/'.$older->number, false);
    }

    public function test_an_archived_order_steps_only_between_archived_ones(): void
    {
        $otherArchived = $this->order('2026-08-19 09:00:00');
        $otherArchived->forceFill(['archived_at' => now()])->save();
        $archived = $this->order('2026-08-21 09:00:00');
        $archived->forceFill(['archived_at' => now()])->save();
        $this->order('2026-08-20 09:00:00');

        $this->show($archived)
            ->assertSee('/admin/orders/'.$otherArchived->number, false)
            ->assertSee('order-stepper-btn is-disabled', false);
    }
}
