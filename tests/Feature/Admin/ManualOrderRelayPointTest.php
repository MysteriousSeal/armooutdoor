<?php

namespace Tests\Feature\Admin;

use App\Models\Carrier;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RelayPoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le point relais d'une commande saisie à la main.
 *
 * Le sélecteur remplissait l'adresse d'expédition et rien d'autre : la
 * commande partait sans savoir où le colis était retiré. L'adresse ne suffit
 * pas — elle porte le nom du commerce, pas son identité de point de retrait —
 * et une vente de place de marché impose souvent un relais absent de la liste
 * du transporteur, d'où des champs qui restent saisissables.
 */
class ManualOrderRelayPointTest extends TestCase
{
    use RefreshDatabase;

    private function carrier(string $method): Carrier
    {
        return Carrier::query()->create([
            'slug' => $method === 'relay' ? 'relais-pickup' : 'colissimo-home',
            'name' => ['en' => 'Carrier', 'fr' => 'Transporteur'],
            'description' => ['en' => '', 'fr' => ''],
            'eta' => ['en' => '', 'fr' => ''],
            'method' => $method,
            'price_cents' => 500,
            'active' => true,
            'sort_order' => 1,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        $product = Product::factory()->create(['quantity' => 10, 'price_cents' => 999]);

        return [
            'action' => 'draft',
            'customer_mode' => 'new',
            'new_customer_first_name' => 'Jean-Philippe',
            'new_customer_last_name' => 'Balmon',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'price' => '9.99']],
            'carrier_id' => $this->carrier('relay')->id,
            'shipping_price' => '0',
            'relay' => [
                'slug' => null,
                'name' => 'Consigne Intermarché Sainte-Marie-de-Cuines',
                'line1' => 'ZI des Grands Prés',
                'postal_code' => '73130',
                'city' => 'Sainte-Marie-de-Cuines',
            ],
            'first_name' => 'Jean-Philippe', 'last_name' => 'Balmon',
            'line1' => 'Consigne Intermarché', 'line2' => 'ZI des Grands Prés',
            'postal_code' => '73130', 'city' => 'Sainte-Marie-de-Cuines', 'country' => 'FR', 'phone' => '',
            'billing_first_name' => 'Jean-Philippe', 'billing_last_name' => 'Balmon',
            'billing_line1' => '5 Chemin des Étalons Dessous', 'billing_line2' => '',
            'billing_postal_code' => '73660', 'billing_city' => 'Saint-Rémy-de-Maurienne', 'billing_country' => 'FR', 'billing_phone' => '',
            ...$overrides,
        ];
    }

    public function test_the_relay_point_is_saved_on_the_order(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/orders', $this->payload())->assertSessionHasNoErrors();

        $relay = Order::query()->latest('id')->first()->relay_snapshot;

        $this->assertSame('Consigne Intermarché Sainte-Marie-de-Cuines', $relay['name']);
        $this->assertSame('ZI des Grands Prés', $relay['line1']);
        $this->assertSame('73130', $relay['postal_code']);
        $this->assertSame('FR', $relay['country']);
    }

    public function test_a_relay_absent_from_the_carrier_list_can_be_typed(): void
    {
        $admin = User::factory()->admin()->create();

        // Le cas réel : NaturaBuy impose son relais, qui n'est dans aucune
        // liste de la boutique. Sans saisie libre il serait introuvable.
        $this->assertSame(0, RelayPoint::query()->count());

        $this->actingAs($admin)->post('/admin/orders', $this->payload())->assertSessionHasNoErrors();

        $this->assertNotNull(Order::query()->latest('id')->first()->relay_snapshot);
    }

    public function test_a_home_carrier_stores_no_relay(): void
    {
        $admin = User::factory()->admin()->create();

        // Carrier 1 livre à domicile : un relais y serait un mensonge.
        $this->actingAs($admin)
            ->post('/admin/orders', $this->payload(['carrier_id' => $this->carrier('home')->id]))
            ->assertSessionHasNoErrors();

        $this->assertNull(Order::query()->latest('id')->first()->relay_snapshot);
    }

    public function test_a_draft_may_be_saved_without_one(): void
    {
        $admin = User::factory()->admin()->create();

        // Un brouillon est un travail en cours : le relais peut manquer encore.
        $this->actingAs($admin)
            ->post('/admin/orders', $this->payload(['relay' => ['name' => '', 'line1' => '', 'postal_code' => '', 'city' => '', 'slug' => '']]))
            ->assertSessionHasNoErrors();

        $this->assertSame('draft', Order::query()->latest('id')->first()->status);
    }

    public function test_finalizing_without_one_is_refused(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/orders', $this->payload([
                'action' => 'placed',
                'relay' => ['name' => '', 'line1' => '', 'postal_code' => '', 'city' => '', 'slug' => ''],
            ]))
            ->assertSessionHasErrors('relay.name');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_finalizing_a_home_delivery_needs_no_relay(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/orders', $this->payload([
                'action' => 'placed',
                'carrier_id' => $this->carrier('home')->id,
                'relay' => ['name' => '', 'line1' => '', 'postal_code' => '', 'city' => '', 'slug' => ''],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('placed', Order::query()->latest('id')->first()->status);
    }

    public function test_the_fields_are_on_the_form(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/orders/create')
            ->assertOk()
            ->assertSee('name="relay[name]"', false)
            ->assertSee('name="relay[line1]"', false)
            ->assertSee('name="relay[postal_code]"', false)
            ->assertSee('name="relay[city]"', false);
    }

    public function test_editing_a_draft_shows_its_relay_point(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/orders', $this->payload())->assertSessionHasNoErrors();
        $order = Order::query()->latest('id')->firstOrFail();

        // Sinon rouvrir le brouillon effacerait le relais au premier
        // enregistrement.
        $this->actingAs($admin)
            ->get(route('admin.orders.edit', $order))
            ->assertOk()
            ->assertSee('Consigne Intermarché Sainte-Marie-de-Cuines', false);
    }

    public function test_two_lines_of_the_same_product_become_one(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['quantity' => 5, 'price_cents' => 100]);

        // Séparées, chaque ligne était vérifiée contre le stock entier puis
        // retranchée : cinq en stock passaient à moins un.
        $this->actingAs($admin)
            ->post('/admin/orders', $this->payload([
                'action' => 'placed',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 3, 'price' => '1.00'],
                    ['product_id' => $product->id, 'quantity' => 2, 'price' => '1.00'],
                ],
            ]))
            ->assertSessionHasNoErrors();

        $order = Order::query()->latest('id')->firstOrFail();

        $this->assertCount(1, $order->items);
        $this->assertSame(5, $order->items->first()->quantity);
        $this->assertSame(0, $product->fresh()->quantity);
    }

    public function test_merged_lines_are_refused_when_the_total_exceeds_stock(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['quantity' => 5, 'price_cents' => 100]);

        // Le stock ne descend jamais sous zéro : c'était le vrai défaut.
        $this->actingAs($admin)->post('/admin/orders', $this->payload([
            'action' => 'placed',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3, 'price' => '1.00'],
                ['product_id' => $product->id, 'quantity' => 3, 'price' => '1.00'],
            ],
        ]));

        $this->assertSame(5, $product->fresh()->quantity);
        $this->assertSame(0, Order::query()->count());
    }

    public function test_different_variants_of_a_product_stay_separate(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['quantity' => 0, 'price_cents' => 100]);

        $variants = collect(['S', 'M'])->map(fn (string $label): ProductVariant => ProductVariant::query()->create([
            'product_id' => $product->id,
            'label' => ['en' => $label, 'fr' => $label],
            'sku' => 'V-'.$label,
            'quantity' => 4,
            'is_active' => true,
            'sort_order' => 1,
        ]));

        // Fusionner sur le seul produit confondrait deux tailles.
        $this->actingAs($admin)
            ->post('/admin/orders', $this->payload([
                'action' => 'placed',
                'items' => $variants->map(fn ($v): array => [
                    'product_id' => $product->id, 'variant_id' => $v->id, 'quantity' => 1, 'price' => '1.00',
                ])->all(),
            ]))
            ->assertSessionHasNoErrors();

        $this->assertCount(2, Order::query()->latest('id')->firstOrFail()->items);
    }

    public function test_an_admin_line_records_that_it_was_not_backordered(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/orders', $this->payload(['action' => 'placed', 'carrier_id' => $this->carrier('home')->id]))
            ->assertSessionHasNoErrors();

        // Le drapeau vient désormais de l'allocation, pas d'un défaut de
        // colonne : il resterait juste si le réassort était un jour ouvert.
        $this->assertFalse(Order::query()->latest('id')->firstOrFail()->items->first()->was_backordered);
    }
}
