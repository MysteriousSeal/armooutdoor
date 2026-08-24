<?php

namespace Tests\Feature\Admin;

use App\Models\Marketplace;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La colonne Channel de la liste des commandes : le logo remplace le nom
 * en clair, celui-ci restant lisible via title/alt pour qui en a besoin.
 */
class OrderChannelLogoTest extends TestCase
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

    public function test_a_marketplace_order_shows_only_the_logo(): void
    {
        $marketplace = Marketplace::query()->create(['name' => 'NaturaBuy', 'logo' => 'marketplaces/naturabuy.webp']);
        $this->order(['is_manual' => true, 'marketplace_id' => $marketplace->id, 'marketplace_name' => 'NaturaBuy']);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->getContent();

        // Le nom reste présent pour l'accessibilité et la souris, mais
        // plus comme texte visible : "NaturaBuy" apparaît aussi dans le
        // filtre de marketplace, une simple sous-chaîne ne suffit donc pas
        // à prouver que la puce elle-même est muette.
        $this->assertStringContainsString('title="NaturaBuy"', $html);
        $this->assertStringContainsString('alt="NaturaBuy"', $html);

        $document = new \DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();

        $chip = (new \DOMXPath($document))->query('//span[contains(@class, "order-chip--channel-logo")]')->item(0);

        $this->assertNotNull($chip);
        $this->assertSame('', trim($chip->textContent));
    }

    public function test_a_manual_order_without_a_marketplace_shows_a_dash(): void
    {
        $this->order(['is_manual' => true, 'marketplace_id' => null, 'marketplace_name' => null]);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('order-chip--channel-logo', $html);
        $this->assertStringNotContainsString('Manuelle', $html);
    }

    public function test_a_storefront_order_still_shows_a_dash(): void
    {
        $this->order(['is_manual' => false]);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('order-chip--channel-logo', $html);
    }
}
