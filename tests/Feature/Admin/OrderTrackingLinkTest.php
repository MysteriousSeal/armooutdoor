<?php

namespace Tests\Feature\Admin;

use App\Models\Carrier;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le lien de suivi sur la fiche commande de l'admin. Il ne s'affiche qu'avec
 * un numéro et un transporteur dont on connaît la page de suivi ; sinon le
 * numéro reste en clair, sans faire croire à un lien mort.
 */
class OrderTrackingLinkTest extends TestCase
{
    use RefreshDatabase;

    private function carrier(string $slug, string $method = 'home'): Carrier
    {
        return Carrier::query()->create([
            'slug' => $slug,
            'name' => ['en' => $slug, 'fr' => $slug],
            'description' => ['en' => '', 'fr' => ''],
            'eta' => ['en' => '', 'fr' => ''],
            'method' => $method,
            'price_cents' => 500,
            'active' => true,
            'sort_order' => 1,
        ]);
    }

    private function order(array $attributes = []): Order
    {
        return Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
            'status' => 'shipped',
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000,
            'shipping_cents' => 500,
            'discount_cents' => 0,
            'total_cents' => 1500,
            'payment_method' => 'card',
            ...$attributes,
        ]);
    }

    public function test_every_carrier_the_shop_uses_has_a_tracking_page(): void
    {
        $expected = [
            'colissimo-home' => 'laposte.fr',
            'lettre-suivie' => 'laposte.fr',
            'chronopost-home' => 'chronopost.fr',
            'relais-pickup' => 'chronopost.fr',
            'mondial-relay' => 'mondialrelay.fr',
        ];

        foreach ($expected as $slug => $host) {
            $url = $this->carrier($slug)->trackingUrlFor('ABC123', '75001');

            $this->assertNotNull($url, $slug.' devrait avoir une page de suivi.');
            $this->assertStringContainsString($host, $url);
            $this->assertStringContainsString('ABC123', $url);
        }
    }

    public function test_mondial_relay_carries_the_postcode(): void
    {
        // Leur page de suivi réclame le code postal du destinataire en plus
        // du numéro : sans lui, elle ouvre un formulaire vide.
        $url = $this->carrier('mondial-relay', 'relay')->trackingUrlFor('64271769', '44300');

        $this->assertStringContainsString('numeroExpedition=64271769', $url);
        $this->assertStringContainsString('codePostal=44300', $url);
    }

    public function test_mondial_relay_without_a_postcode_gives_no_link(): void
    {
        $carrier = $this->carrier('mondial-relay', 'relay');

        $this->assertNull($carrier->trackingUrlFor('64271769', null));
        $this->assertNull($carrier->trackingUrlFor('64271769', ''));
    }

    public function test_the_other_carriers_do_not_need_a_postcode(): void
    {
        // Seul Mondial Relay le demande : exiger un code postal partout
        // supprimerait des liens qui marchent très bien sans.
        $this->assertNotNull($this->carrier('colissimo-home')->trackingUrlFor('ABC123'));
        $this->assertNotNull($this->carrier('chronopost-home')->trackingUrlFor('ABC123'));
    }

    public function test_the_delivery_address_postcode_is_used(): void
    {
        $carrier = $this->carrier('mondial-relay', 'relay');
        $order = $this->order([
            'carrier_id' => $carrier->id,
            'tracking_number' => '64271769',
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '', 'city' => 'Nantes', 'country' => 'FR'],
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '44300', 'city' => 'Nantes', 'country' => 'FR'],
            'relay_snapshot' => ['name' => 'Point relais', 'line1' => 'y', 'postal_code' => '44000', 'city' => 'Nantes'],
        ]);

        // No billing postcode: the delivery address comes before the relay point.
        $this->assertStringContainsString('codePostal=44300', $order->trackingUrl());
    }

    public function test_the_relay_postcode_is_the_fallback(): void
    {
        $carrier = $this->carrier('mondial-relay', 'relay');
        $order = $this->order([
            'carrier_id' => $carrier->id,
            'tracking_number' => '64271769',
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '', 'city' => 'Nantes', 'country' => 'FR'],
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '', 'city' => 'Nantes', 'country' => 'FR'],
            'relay_snapshot' => ['name' => 'Point relais', 'line1' => 'y', 'postal_code' => '44000', 'city' => 'Nantes'],
        ]);

        $this->assertStringContainsString('codePostal=44000', $order->trackingUrl());
    }

    public function test_no_postcode_anywhere_means_plain_text(): void
    {
        $admin = User::factory()->admin()->create();
        $carrier = $this->carrier('mondial-relay', 'relay');
        $order = $this->order([
            'carrier_id' => $carrier->id,
            'tracking_number' => '64271769',
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '', 'city' => 'Nantes', 'country' => 'FR'],
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '', 'city' => 'Nantes', 'country' => 'FR'],
            'relay_snapshot' => null,
        ]);

        $this->assertNull($order->trackingUrl());

        $this->actingAs($admin)
            ->get('/admin/orders/'.$order->number)
            ->assertOk()
            ->assertSee('is-plain', false)
            ->assertSee('64271769');
    }

    public function test_an_unknown_carrier_has_no_tracking_page(): void
    {
        $this->assertNull($this->carrier('transporteur-maison')->trackingUrlFor('ABC123'));
    }

    public function test_no_number_means_no_link(): void
    {
        $carrier = $this->carrier('colissimo-home');

        $this->assertNull($carrier->trackingUrlFor(null, '75001'));
        $this->assertNull($carrier->trackingUrlFor('', '75001'));
    }

    public function test_the_number_is_url_encoded(): void
    {
        // Un numéro recopié avec une espace casserait l'URL sans encodage.
        $url = $this->carrier('colissimo-home')->trackingUrlFor('AB 12&34');

        $this->assertStringContainsString('AB%2012%2634', $url);
        $this->assertStringNotContainsString('AB 12&34', $url);
    }

    public function test_the_tracking_carrier_wins_over_the_delivery_carrier(): void
    {
        $delivery = $this->carrier('colissimo-home');
        $tracking = $this->carrier('chronopost-home');
        $order = $this->order([
            'carrier_id' => $delivery->id,
            'tracking_carrier_id' => $tracking->id,
            'tracking_number' => 'XM022436897TS',
        ]);

        // Le colis peut partir par un autre transporteur que celui choisi au
        // checkout : c'est celui qui l'achemine qui compte.
        $this->assertStringContainsString('chronopost.fr', $order->trackingUrl());
    }

    public function test_the_delivery_carrier_is_used_when_no_tracking_carrier_is_set(): void
    {
        $carrier = $this->carrier('mondial-relay', 'relay');
        $order = $this->order([
            'carrier_id' => $carrier->id,
            'tracking_carrier_id' => null,
            'tracking_number' => 'MR9988',
        ]);

        $this->assertStringContainsString('mondialrelay.fr', $order->trackingUrl());
    }

    public function test_an_order_without_a_number_has_no_link(): void
    {
        $carrier = $this->carrier('colissimo-home');
        $order = $this->order(['carrier_id' => $carrier->id, 'tracking_number' => null]);

        $this->assertNull($order->trackingUrl());
    }

    public function test_the_admin_page_shows_a_clickable_link(): void
    {
        $admin = User::factory()->admin()->create();
        $carrier = $this->carrier('chronopost-home');
        $order = $this->order(['carrier_id' => $carrier->id, 'tracking_number' => 'XM022436897TS']);

        $this->actingAs($admin)
            ->get('/admin/orders/'.$order->number)
            ->assertOk()
            ->assertSee('order-tracking-link', false)
            ->assertSee('listeNumerosLT=XM022436897TS', false)
            ->assertSee('rel="noopener noreferrer"', false);
    }

    public function test_the_admin_page_shows_plain_text_for_an_unknown_carrier(): void
    {
        $admin = User::factory()->admin()->create();
        $carrier = $this->carrier('transporteur-maison');
        $order = $this->order(['carrier_id' => $carrier->id, 'tracking_number' => 'ABC123']);

        // Le numéro reste lisible, mais rien ne doit ressembler à un lien.
        $this->actingAs($admin)
            ->get('/admin/orders/'.$order->number)
            ->assertOk()
            ->assertSee('is-plain', false)
            ->assertSee('ABC123')
            ->assertDontSee('order-tracking-link-value"', false);
    }

    public function test_the_block_is_absent_without_a_tracking_number(): void
    {
        $admin = User::factory()->admin()->create();
        $carrier = $this->carrier('colissimo-home');
        $order = $this->order(['carrier_id' => $carrier->id, 'tracking_number' => null]);

        $this->actingAs($admin)
            ->get('/admin/orders/'.$order->number)
            ->assertOk()
            ->assertDontSee('order-tracking-link', false);
    }

    public function test_the_customer_page_is_unchanged(): void
    {
        $customer = User::factory()->create();
        $carrier = $this->carrier('chronopost-home');
        $order = $this->order([
            'user_id' => $customer->id,
            'carrier_id' => $carrier->id,
            'tracking_number' => 'XM022436897TS',
        ]);

        // Décision explicite : le lien reste côté admin pour l'instant.
        $this->actingAs($customer)
            ->get('/orders/'.$order->number)
            ->assertOk()
            ->assertSee('XM022436897TS')
            ->assertDontSee('chronopost.fr', false);
    }

    /** A Vinted parcel is booked under Vinted's brand code, and found by number alone. */
    public function test_a_vinted_mondial_relay_order_links_with_vinted_brand_code(): void
    {
        $carrier = $this->carrier('mondial-relay', 'relay');
        $order = $this->order([
            'carrier_id' => $carrier->id,
            'tracking_number' => '74051897',
            'marketplace_name' => 'Vinted',
        ]);

        $this->assertSame(
            'https://www.mondialrelay.fr/suivi-de-colis?codeMarque=V2&numeroExpedition=74051897',
            $order->trackingUrl(),
        );
    }

    /** Only Vinted's own parcels: the shop's and other marketplaces' keep number and postcode. */
    public function test_other_mondial_relay_orders_keep_the_postcode_link(): void
    {
        $carrier = $this->carrier('mondial-relay', 'relay');

        foreach ([null, 'LeBonCoin'] as $marketplace) {
            $order = $this->order([
                'carrier_id' => $carrier->id,
                'tracking_number' => '74051897',
                'marketplace_name' => $marketplace,
            ]);

            $this->assertSame(
                'https://www.mondialrelay.fr/suivi-de-colis?numeroExpedition=74051897&codePostal=75000',
                $order->trackingUrl(),
                (string) $marketplace,
            );
        }
    }

    /** A Vinted order with another carrier keeps that carrier's page. */
    public function test_a_vinted_order_with_another_carrier_is_unchanged(): void
    {
        $carrier = $this->carrier('colissimo-home');
        $order = $this->order([
            'carrier_id' => $carrier->id,
            'tracking_number' => '6A123',
            'marketplace_name' => 'Vinted',
        ]);

        $this->assertSame('https://www.laposte.fr/outils/suivre-vos-envois?code=6A123', $order->trackingUrl());
    }

    /** The billing postcode first, then the shipping one, then the relay point's. */
    public function test_the_mondial_relay_postcode_is_taken_from_billing_first(): void
    {
        $carrier = $this->carrier('mondial-relay', 'relay');
        $shipping = ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '40700', 'city' => 'Hagetmau', 'country' => 'FR'];
        $billing = ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'y', 'postal_code' => '44270', 'city' => 'Machecoul', 'country' => 'FR'];
        $relay = ['name' => 'Locker', 'line1' => 'z', 'postal_code' => '33000', 'city' => 'Bordeaux', 'country' => 'FR'];

        $cases = [
            '44270' => [$billing, $shipping, $relay],
            '40700' => [[...$billing, 'postal_code' => ''], $shipping, $relay],
            '33000' => [null, [...$shipping, 'postal_code' => null], $relay],
        ];

        foreach ($cases as $expected => [$billingSnapshot, $shippingSnapshot, $relaySnapshot]) {
            $order = $this->order([
                'carrier_id' => $carrier->id,
                'tracking_number' => '74051897',
                'billing_address_snapshot' => $billingSnapshot,
                'address_snapshot' => $shippingSnapshot,
                'relay_snapshot' => $relaySnapshot,
            ]);

            $this->assertStringEndsWith('&codePostal='.$expected, (string) $order->trackingUrl(), (string) $expected);
        }
    }
}
