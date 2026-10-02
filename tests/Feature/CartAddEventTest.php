<?php

namespace Tests\Feature;

use App\Models\CartAddEvent;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The server-side log of every add-to-cart, kept alongside the browser's
 * add_to_cart/cart_item_added events (public/js/analytics.js) so the shop has
 * its own record even when a visitor blocks analytics scripts entirely.
 */
class CartAddEventTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_adding_a_product_records_an_event(): void
    {
        $product = Product::query()->where('slug', 'ridge-tent')->firstOrFail();

        $this->post('/cart', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $event = CartAddEvent::query()->firstOrFail();

        $this->assertSame($product->id, $event->product_id);
        $this->assertSame(2, $event->quantity);
        $this->assertSame($product->effectivePriceCents(), $event->unit_price_cents);
        $this->assertNull($event->user_id);
    }

    public function test_the_session_id_is_only_carried_once_consented(): void
    {
        $product = Product::query()->where('slug', 'ridge-tent')->firstOrFail();

        $this->post('/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->assertNull(CartAddEvent::query()->firstOrFail()->session_id);

        CartAddEvent::query()->delete();

        $this->withUnencryptedCookie('cookie_consent', 'all')->post('/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->assertNotNull(CartAddEvent::query()->firstOrFail()->session_id);
    }

    public function test_a_logged_in_user_is_recorded_on_the_event(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->where('slug', 'ridge-tent')->firstOrFail();

        $this->actingAs($user)->post('/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->assertSame($user->id, CartAddEvent::query()->firstOrFail()->user_id);
    }
}
