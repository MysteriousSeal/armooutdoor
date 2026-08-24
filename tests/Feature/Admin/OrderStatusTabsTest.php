<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La barre d'onglets de la liste des commandes.
 *
 * Chaque statut y a son onglet ; « Archived » et « Test » sont renvoyés au
 * bout de la barre. Le statut n'est plus un filtre du formulaire mais un
 * onglet : ce qui se voit surtout quand on change d'onglet ou qu'on efface
 * les filtres, et c'est là que ces tests regardent.
 */
class OrderStatusTabsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function order(string $status, array $attributes = []): Order
    {
        return Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
            'status' => $status,
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000, 'shipping_cents' => 500, 'discount_cents' => 0,
            'total_cents' => 1500, 'payment_method' => 'card',
            ...$attributes,
        ]);
    }

    public function test_every_status_has_a_tab(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/orders')->assertOk();

        foreach (['Placed', 'Preparing', 'Shipped', 'In transit', 'Delivered', 'Refunded'] as $label) {
            $response->assertSee($label);
        }
        foreach (['placed', 'preparing', 'shipped', 'in_transit', 'delivered', 'refunded'] as $status) {
            $response->assertSee('status='.$status, false);
        }
    }

    /** Archived et Test ferment la barre, dans cet ordre. */
    public function test_archived_and_test_sit_at_the_far_end(): void
    {
        $html = $this->actingAs($this->admin())->get('/admin/orders')->getContent();
        $nav = substr($html, strpos($html, '<nav class="admin-tabs"'));
        $nav = substr($nav, 0, strpos($nav, '</nav>'));

        $order = [];
        foreach (['Orders', 'Drafts', 'Placed', 'Refunded', 'Archived', 'Test'] as $label) {
            $order[$label] = strpos($nav, $label);
            $this->assertNotFalse($order[$label], "the {$label} tab is present");
        }

        $this->assertLessThan($order['Placed'], $order['Drafts'], 'Drafts stays right after Orders');
        $this->assertLessThan($order['Archived'], $order['Refunded'], 'the statuses come before the far-end pair');
        $this->assertLessThan($order['Test'], $order['Archived'], 'Archived then Test');
        $this->assertStringContainsString('sits-apart', $nav, 'the far-end pair is pushed away');
    }

    public function test_a_status_tab_filters_the_list(): void
    {
        $shipped = $this->order('shipped');
        $placed = $this->order('placed');

        $this->actingAs($this->admin())
            ->get('/admin/orders?status=shipped')
            ->assertOk()
            ->assertSee($shipped->number)
            ->assertDontSee($placed->number);
    }

    public function test_the_tab_counts_ignore_the_search_filter(): void
    {
        $this->order('shipped');
        $this->order('shipped');
        $this->order('placed');

        // Le compteur dit ce que contient l'onglet, pas ce que la recherche
        // y trouverait — comme les onglets Drafts, Archived et Test.
        $html = $this->actingAs($this->admin())->get('/admin/orders?search=zzz-no-match')->getContent();
        $nav = substr($html, strpos($html, '<nav class="admin-tabs"'));
        $nav = substr($nav, 0, strpos($nav, '</nav>'));

        $this->assertMatchesRegularExpression('/Shipped\s*<span class="admin-tab-count">2<\/span>/', $nav);
    }

    public function test_the_status_dropdown_is_gone(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/orders')
            ->assertOk()
            ->assertDontSee('id="order-status"', false)
            ->assertDontSee('All statuses');
    }

    /** Une recherche garde l'onglet ; changer d'onglet garde la recherche. */
    public function test_a_status_tab_keeps_the_other_filters(): void
    {
        $html = $this->actingAs($this->admin())->get('/admin/orders?search=dupont')->getContent();

        $this->assertStringContainsString('search=dupont&amp;status=shipped', $html);
    }

    public function test_switching_to_archived_drops_the_status(): void
    {
        $html = $this->actingAs($this->admin())->get('/admin/orders?status=shipped')->getContent();
        $nav = substr($html, strpos($html, '<nav class="admin-tabs"'));
        $nav = substr($nav, 0, strpos($nav, '</nav>'));

        preg_match('/href="([^"]*tab=archived[^"]*)"/', $nav, $m);
        $this->assertNotEmpty($m, 'the Archived tab has a link');
        $this->assertStringNotContainsString('status=', $m[1]);
    }

    /** Le statut est un onglet : il ne doit pas déclencher la barre « Clear ». */
    public function test_a_status_tab_is_not_shown_as_an_active_filter_chip(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/orders?status=shipped')
            ->assertOk()
            ->assertDontSee('Status · Shipped')
            ->assertDontSee('admin-filter-chips', false);
    }

    public function test_clearing_the_filters_keeps_the_status_tab(): void
    {
        $html = $this->actingAs($this->admin())->get('/admin/orders?status=shipped&search=dupont')->getContent();

        preg_match('/href="([^"]*)"[^>]*class="btn btn-secondary">Clear/', $html, $m);
        $this->assertNotEmpty($m, 'the Clear button is shown');
        $this->assertStringContainsString('status=shipped', $m[1]);
        $this->assertStringNotContainsString('search=', $m[1]);
    }

    public function test_the_orders_tab_is_active_only_without_a_status(): void
    {
        $withStatus = $this->actingAs($this->admin())->get('/admin/orders?status=shipped')->getContent();
        $withStatus = substr($withStatus, strpos($withStatus, '<nav class="admin-tabs"'));
        $withStatus = substr($withStatus, 0, strpos($withStatus, '</nav>'));

        preg_match_all('/class="([^"]*active[^"]*)"/', $withStatus, $m);
        $this->assertCount(1, $m[0], 'exactly one tab is active');
    }
}
