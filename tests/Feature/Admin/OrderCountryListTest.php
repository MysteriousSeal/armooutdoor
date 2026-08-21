<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Les pays acceptés sur une adresse de commande.
 *
 * Deux listes séparées : celle de l'administration couvre les ventes des
 * places de marché, qui partent bien plus loin que la boutique elle-même.
 * Un pays absent de la liste fait échouer la validation, et l'adresse d'une
 * commande déjà rapatriée devient alors impossible à corriger.
 */
class OrderCountryListTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $country = 'PT'): Order
    {
        return Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
            'status' => 'placed',
            'address_snapshot' => ['first_name' => 'Pedro', 'last_name' => 'Rosado', 'line1' => 'Rua A', 'postal_code' => '2835-334', 'city' => 'Lavradio', 'country' => $country],
            'billing_address_snapshot' => ['first_name' => 'Pedro', 'last_name' => 'Rosado', 'line1' => 'Rua A', 'postal_code' => '2835-334', 'city' => 'Lavradio', 'country' => $country],
            'carrier_method' => 'relay',
            'carrier_snapshot' => ['name' => ['fr' => 'Chronopost']],
            'subtotal_cents' => 3500,
            'shipping_cents' => 0,
            'discount_cents' => 0,
            'total_cents' => 3500,
            'payment_method' => 'card',
            'is_manual' => true,
        ]);
    }

    public function test_the_expected_countries_are_accepted(): void
    {
        $expected = ['FR', 'DE', 'BE', 'ES', 'IE', 'IT', 'LU', 'NL', 'PT', 'CH'];

        $this->assertSame($expected, config('shop.countries'));
    }

    public function test_each_country_has_the_right_name(): void
    {
        // Sans libellé, le sélecteur afficherait la clé de traduction brute.
        $names = [
            'FR' => 'France', 'DE' => 'Allemagne', 'BE' => 'Belgique',
            'ES' => 'Espagne', 'IE' => 'Irlande', 'IT' => 'Italie',
            'LU' => 'Luxembourg', 'NL' => 'Pays-Bas', 'PT' => 'Portugal',
            'CH' => 'Suisse',
        ];

        foreach ($names as $code => $name) {
            $this->assertSame($name, __('store.country_'.$code));
        }
    }

    public function test_the_list_reads_in_order_in_the_dropdown(): void
    {
        // Le menu suit l'ordre du tableau : la France en tête parce qu'elle
        // est le choix par défaut, le reste alphabétique par libellé.
        $countries = config('shop.countries');
        $labels = array_map(fn (string $code): string => __('store.country_'.$code), $countries);

        $this->assertSame('France', array_shift($labels));

        $sorted = $labels;
        // Comparaison locale : sans elle « Pays-Bas » passerait après
        // « Portugal » à cause du tiret.
        usort($sorted, fn (string $a, string $b): int => strcoll($a, $b));

        $this->assertSame($sorted, $labels);
    }

    public function test_every_accepted_country_has_a_name(): void
    {
        foreach (config('shop.countries') as $country) {
            $this->assertNotSame(
                'store.country_'.$country,
                __('store.country_'.$country),
                $country.' n’a pas de libellé'
            );
        }
    }

    public function test_every_country_appears_in_the_address_form(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order('FR');

        $html = $this->actingAs($admin)->get('/admin/orders/'.$order->number)->assertOk()->getContent();

        foreach (config('shop.countries') as $code) {
            $this->assertStringContainsString('value="'.$code.'"', $html, $code.' manque au sélecteur');
            $this->assertStringContainsString(__('store.country_'.$code), $html);
        }
    }

    public function test_a_newly_accepted_country_can_be_saved(): void
    {
        $admin = User::factory()->admin()->create();

        // Chaque pays de la liste doit passer la validation : en ajouter un
        // sans libellé ou sans l'inscrire des deux côtés casserait la saisie.
        foreach (['DE', 'ES', 'IE', 'IT', 'NL'] as $code) {
            $order = $this->order('FR');

            $this->actingAs($admin)
                ->patch(route('admin.orders.address.shipping', $order), [
                    'first_name' => 'A', 'last_name' => 'B',
                    'line1' => 'x', 'line2' => '',
                    'postal_code' => '00000', 'city' => 'Ville',
                    'country' => $code, 'phone' => '000000000',
                ])
                ->assertSessionHasNoErrors();

            $this->assertSame($code, $order->fresh()->address_snapshot['country']);
        }
    }

    public function test_an_address_can_be_saved_with_portugal(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order('FR');

        // Le cas réel : une vente rapatriée partie au Portugal, dont l'adresse
        // doit rester corrigeable.
        $this->actingAs($admin)
            ->patch(route('admin.orders.address.shipping', $order), [
                'first_name' => 'Pedro', 'last_name' => 'Rosado',
                'line1' => 'Pct A Soc Filarm Uniao Agricola 1 D', 'line2' => '',
                'postal_code' => '2835-334', 'city' => 'Lavradio',
                'country' => 'PT', 'phone' => '000000000',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('PT', $order->fresh()->address_snapshot['country']);
    }

    public function test_the_billing_address_accepts_it_too(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order('FR');

        $this->actingAs($admin)
            ->patch(route('admin.orders.address.billing', $order), [
                'first_name' => 'Pedro', 'last_name' => 'Rosado',
                'line1' => 'Pct A Soc Filarm Uniao Agricola 1 D', 'line2' => '',
                'postal_code' => '2835-334', 'city' => 'Lavradio',
                'country' => 'PT', 'phone' => '000000000',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('PT', $order->fresh()->billing_address_snapshot['country']);
    }

    public function test_an_unknown_country_is_still_refused(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order('FR');

        // La liste reste une liste : elle s'allonge, elle ne s'ouvre pas.
        $this->actingAs($admin)
            ->patch(route('admin.orders.address.shipping', $order), [
                'first_name' => 'A', 'last_name' => 'B',
                'line1' => 'x', 'line2' => '',
                'postal_code' => '00000', 'city' => 'Nowhere',
                'country' => 'ZZ', 'phone' => '000000000',
            ])
            // Le formulaire a son propre sac d'erreurs : sans le nommer,
            // l'assertion cherche au mauvais endroit et passe à côté.
            ->assertSessionHasErrors('country', null, 'shippingAddress');
    }

    public function test_the_shop_checkout_is_untouched(): void
    {
        // La boutique n'expédie qu'en France : ouvrir le Portugal ici
        // demanderait des tarifs de port, pas une ligne de configuration.
        $this->assertSame(['FR'], config('shop.customer_countries'));
    }
}
