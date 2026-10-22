<?php

namespace Tests\Feature;

use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The welcome code offered in one click in the checkout's discount section,
 * only to a customer who could apply it.
 */
class CheckoutWelcomeCodeTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CatalogSeeder::class, ShippingSeeder::class]);

        $this->customer = User::factory()->create();
        $product = Product::factory()->create(['is_active' => true, 'quantity' => 5, 'price_cents' => 2000]);
        $this->actingAs($this->customer)->post('/cart', ['product_id' => $product->id, 'quantity' => 1]);
    }

    private function welcomeCode(array $overrides = []): DiscountCode
    {
        return DiscountCode::query()->create([
            'code' => 'BIENVENUE10',
            'type' => DiscountCode::TYPE_PERCENTAGE,
            'value' => 10,
            'max_uses_per_customer' => 1,
            'in_welcome_banner' => true,
            ...$overrides,
        ]);
    }

    public function test_an_eligible_customer_is_offered_the_welcome_code(): void
    {
        $this->welcomeCode();

        $this->actingAs($this->customer)->get('/checkout')
            ->assertOk()
            ->assertSee('discount-code-suggested', false)
            ->assertSee('<input type="hidden" name="code" value="BIENVENUE10">', false)
            ->assertSee('-10% sur votre première commande')
            ->assertSee('Utiliser ce code');
    }

    public function test_the_offer_applies_the_code_and_then_disappears(): void
    {
        $this->welcomeCode();

        $response = $this->actingAs($this->customer)
            ->postJson('/checkout/discount-code', ['code' => 'BIENVENUE10'])
            ->assertOk()
            ->assertJson(['applied' => true, 'discountCents' => 200]);

        $this->assertStringNotContainsString('discount-code-suggested', $response->json('sectionHtml'));

        $this->actingAs($this->customer)->get('/checkout')->assertDontSee('discount-code-suggested', false);
    }

    public function test_removing_the_code_offers_it_again(): void
    {
        $this->welcomeCode();
        $this->actingAs($this->customer)->postJson('/checkout/discount-code', ['code' => 'BIENVENUE10']);

        $response = $this->actingAs($this->customer)->deleteJson('/checkout/discount-code')->assertOk();

        $this->assertStringContainsString('discount-code-suggested', $response->json('sectionHtml'));
    }

    public function test_no_offer_once_the_customer_has_used_the_code(): void
    {
        $code = $this->welcomeCode();

        Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => $this->customer->id,
            'status' => 'delivered',
            'address_snapshot' => ['first_name' => 'Jean', 'last_name' => 'Client', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'Jean', 'last_name' => 'Client', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000,
            'shipping_cents' => 0,
            'discount_cents' => 100,
            'total_cents' => 900,
            'payment_method' => 'card',
            'discount_code_id' => $code->id,
        ]);

        $this->actingAs($this->customer)->get('/checkout')->assertOk()->assertDontSee('discount-code-suggested', false);
    }

    public function test_no_offer_without_a_live_banner_code(): void
    {
        $this->welcomeCode(['in_welcome_banner' => false]);
        $this->actingAs($this->customer)->get('/checkout')->assertDontSee('discount-code-suggested', false);

        DiscountCode::query()->delete();
        $this->welcomeCode(['ends_at' => now()->subMinute()]);
        $this->actingAs($this->customer)->get('/checkout')->assertDontSee('discount-code-suggested', false);
    }
}
