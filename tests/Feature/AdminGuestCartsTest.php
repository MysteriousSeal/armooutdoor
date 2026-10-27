<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Guests' carts on the admin carts page, read from their live sessions. */
class AdminGuestCartsTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1';

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.driver' => 'database', 'session.lifetime' => 120, 'session.serialization' => 'json']);
        $this->seed(AdminSeeder::class);
    }

    private function guestSession(array $cart, string $userAgent = self::CHROME, ?int $userId = null, int $minutesAgo = 5): void
    {
        DB::table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => $userId,
            'ip_address' => '203.0.113.90',
            'user_agent' => $userAgent,
            'payload' => base64_encode(json_encode(['_token' => Str::random(40), 'cart' => $cart])),
            'last_activity' => now()->subMinutes($minutesAgo)->getTimestamp(),
        ]);
    }

    private function adminPage(): string
    {
        $admin = User::query()->where('email', 'admin@armooutdoor.test')->firstOrFail();

        return $this->actingAs($admin)->get('/admin/carts')->assertOk()->getContent();
    }

    public function test_a_live_guest_cart_is_listed_with_its_products_and_total(): void
    {
        $product = Product::factory()->create(['name' => 'Cagoule Invitée', 'price_cents' => 390]);
        $this->guestSession([$product->id => [0 => 3]]);

        $html = $this->adminPage();

        $this->assertStringContainsString('Visitors not signed in', $html);
        $this->assertStringContainsString('3&times; Cagoule Invitée', $html);
        $this->assertStringContainsString('11,70', $html);
        $this->assertStringContainsString('1 guest cart', $html);
    }

    public function test_bots_expired_sessions_empty_carts_and_signed_in_sessions_are_left_out(): void
    {
        $product = Product::factory()->create(['name' => 'Produit Fantôme']);
        $this->guestSession([$product->id => [0 => 1]], 'Googlebot/2.1');
        $this->guestSession([$product->id => [0 => 1]], minutesAgo: 180);
        $this->guestSession([]);
        $this->guestSession([$product->id => [0 => 1]], userId: User::factory()->create()->id);

        $html = $this->adminPage();

        $this->assertStringNotContainsString('Produit Fantôme', $html);
        $this->assertStringContainsString('No visitor has anything in their cart right now.', $html);
    }

    public function test_php_serialised_sessions_are_read_too(): void
    {
        config(['session.serialization' => 'php']);
        $product = Product::factory()->create(['name' => 'Cagoule Sérialisée', 'price_cents' => 390]);
        DB::table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => null,
            'ip_address' => '203.0.113.92',
            'user_agent' => self::CHROME,
            'payload' => base64_encode(serialize(['cart' => [$product->id => [0 => 1]]])),
            'last_activity' => now()->getTimestamp(),
        ]);

        $this->assertStringContainsString('1&times; Cagoule Sérialisée', $this->adminPage());
    }

    public function test_a_payload_that_is_not_a_session_is_ignored(): void
    {
        DB::table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => null,
            'ip_address' => '203.0.113.91',
            'user_agent' => self::CHROME,
            'payload' => 'not-base64-@@',
            'last_activity' => now()->getTimestamp(),
        ]);

        $this->assertStringContainsString('No visitor has anything in their cart right now.', $this->adminPage());
    }
}
