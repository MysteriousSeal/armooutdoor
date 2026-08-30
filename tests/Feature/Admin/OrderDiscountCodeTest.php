<?php

namespace Tests\Feature\Admin;

use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le code de remerciement offert depuis une commande : 10 %, un usage,
 * pour n'importe quel client, trois mois à compter de la commande — et
 * un seul par commande.
 */
class OrderDiscountCodeTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_the_code_is_created_with_the_promised_terms(): void
    {
        $order = $this->order(['created_at' => now()->subDays(10)]);

        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/orders/'.$order->number.'/discount-code')
            ->assertRedirect();

        $code = $order->generatedDiscountCode()->first();

        $this->assertNotNull($code);
        $this->assertSame(DiscountCode::TYPE_PERCENTAGE, $code->type);
        $this->assertSame(10, $code->value);
        $this->assertNull($code->user_id);
        $this->assertSame(1, $code->quantity);
        $this->assertSame(1, $code->max_uses_per_customer);
        $this->assertTrue($code->ends_at->equalTo($order->created_at->copy()->addMonths(3)));
        $this->assertMatchesRegularExpression('/^MERCI-[BCDFGHJKMNPQRSTVWXZ2-9]{6}$/', $code->code);
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'discount_code.created']);
    }

    public function test_a_second_click_creates_nothing(): void
    {
        $order = $this->order();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/orders/'.$order->number.'/discount-code');
        $this->actingAs($admin)->post('/admin/orders/'.$order->number.'/discount-code')->assertRedirect();

        $this->assertSame(1, DiscountCode::query()->count());
    }

    public function test_a_draft_is_refused(): void
    {
        $order = $this->order(['status' => 'draft']);

        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/orders/'.$order->number.'/discount-code')
            ->assertStatus(422);

        $this->assertSame(0, DiscountCode::query()->count());
    }

    public function test_the_order_page_shows_the_button_then_the_code(): void
    {
        $order = $this->order();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/orders/'.$order->number)
            ->assertOk()
            ->assertSee('Create a discount code');

        $this->actingAs($admin)->post('/admin/orders/'.$order->number.'/discount-code');

        $html = $this->actingAs($admin)
            ->get('/admin/orders/'.$order->number)
            ->assertOk()
            ->assertDontSee('Create a discount code')
            ->getContent();

        $this->assertStringContainsString($order->generatedDiscountCode()->value('code'), $html);
        $this->assertStringContainsString('Valid until', $html);
    }

    public function test_the_customer_never_sees_the_code_on_their_own_order_page(): void
    {
        $order = $this->order();
        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/orders/'.$order->number.'/discount-code');

        $code = $order->generatedDiscountCode()->value('code');

        // Le test partage une session entre l'admin et le client ; en vrai
        // ce sont deux navigateurs. Sans ce flush, le flash « Code …
        // created. » de l'admin s'afficherait sur la page suivante et
        // ferait accuser à tort la page de commande.
        $this->flushSession();

        // La page de commande du client montre le code qu'il a dépensé
        // (le snapshot), jamais celui que la commande a valu : ce
        // dernier se distribue à la main, pas tout seul.
        $html = $this->actingAs($order->user)
            ->get('/orders/'.$order->number)
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString($code, $html);
        $this->assertStringNotContainsString('MERCI-', $html);
    }

    public function test_guests_cannot_create_one(): void
    {
        $order = $this->order();

        $this->post('/admin/orders/'.$order->number.'/discount-code')
            ->assertRedirect('/admin');

        $this->assertSame(0, DiscountCode::query()->count());
    }
}
