<?php

namespace Tests\Feature\Admin;

use App\Models\Carrier;
use App\Models\Order;
use App\Models\PackageType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Avant de télécharger une facture, l'admin est prévenu si le suivi (le
 * transporteur, le numéro, le type de colis) n'est pas encore renseigné —
 * une confirmation, pas un blocage : le téléchargement reste possible.
 */
class OrderInvoiceWarningTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function carrier(): Carrier
    {
        return Carrier::query()->create([
            'slug' => 'colissimo-home',
            'name' => ['en' => 'Colissimo', 'fr' => 'Colissimo'],
            'description' => ['en' => '', 'fr' => ''],
            'eta' => ['en' => '', 'fr' => ''],
            'method' => 'home',
            'price_cents' => 500,
            'active' => true,
            'sort_order' => 1,
        ]);
    }

    private function order(array $overrides = []): Order
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
            ...$overrides,
        ]);
    }

    public function test_the_warning_lists_every_missing_field(): void
    {
        $order = $this->order();

        $html = $this->actingAs($this->admin())
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-confirm-modal="invoice-warning-modal"', $html);
        $this->assertStringContainsString('id="invoice-warning-modal"', $html);
        $this->assertStringContainsString('Tracking carrier', $html);
        $this->assertStringContainsString('Tracking number', $html);
        $this->assertStringContainsString('Package type', $html);
    }

    public function test_the_warning_lists_only_what_is_actually_missing(): void
    {
        $carrier = $this->carrier();
        $packageType = PackageType::query()->create(['name' => 'Box']);

        $order = $this->order([
            'tracking_carrier_id' => $carrier->id,
            'package_type_id' => $packageType->id,
            'tracking_number' => null,
        ]);

        $html = $this->actingAs($this->admin())
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-confirm-modal="invoice-warning-modal"', $html);
        $this->assertStringContainsString('Tracking number', $html);

        // Un solde d'écran ne suffit pas : sans balise fermante, "Tracking
        // number" contiendrait aussi la sous-chaîne "Tracking carrier" par
        // hasard d'affichage — on isole donc le compte des <li>.
        $this->assertSame(1, substr_count($html, '<li>Tracking'));
    }

    public function test_no_warning_when_everything_is_filled_in(): void
    {
        $carrier = $this->carrier();
        $packageType = PackageType::query()->create(['name' => 'Box']);

        $order = $this->order([
            'tracking_carrier_id' => $carrier->id,
            'package_type_id' => $packageType->id,
            'tracking_number' => 'ABC123',
        ]);

        $html = $this->actingAs($this->admin())
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('data-confirm-modal="invoice-warning-modal"', $html);
        $this->assertStringNotContainsString('id="invoice-warning-modal"', $html);
    }

    public function test_no_warning_when_the_invoice_is_not_even_available_yet(): void
    {
        $order = $this->order(['status' => 'placed']);

        $html = $this->actingAs($this->admin())
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Download invoice', $html);
        $this->assertStringNotContainsString('invoice-warning-modal', $html);
    }

    public function test_the_download_still_works_despite_missing_fields(): void
    {
        $order = $this->order();

        $this->actingAs($this->admin())
            ->get(route('admin.orders.invoice', $order))
            ->assertOk();
    }

    /**
     * "data-invoice-confirm" apparaît aussi tel quel dans le script inline
     * de la page (le sélecteur querySelectorAll) : une sous-chaîne ne
     * prouve donc rien, il faut vraiment compter les éléments qui portent
     * l'attribut dans le tableau.
     */
    private function invoiceConfirmLinks(string $html): \DOMNodeList
    {
        $document = new \DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();

        return (new \DOMXPath($document))->query('//table//a[@data-invoice-confirm]');
    }

    public function test_the_orders_list_flags_the_same_missing_fields(): void
    {
        $order = $this->order();

        $html = $this->actingAs($this->admin())
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->getContent();

        $links = $this->invoiceConfirmLinks($html);

        $this->assertSame(1, $links->length);
        $this->assertSame(
            'Tracking carrier,Tracking number,Package type',
            $links->item(0)->getAttribute('data-missing'),
        );
    }

    public function test_the_orders_list_stays_quiet_when_everything_is_filled_in(): void
    {
        $carrier = $this->carrier();
        $packageType = PackageType::query()->create(['name' => 'Box']);

        $this->order([
            'tracking_carrier_id' => $carrier->id,
            'package_type_id' => $packageType->id,
            'tracking_number' => 'ABC123',
        ]);

        $html = $this->actingAs($this->admin())
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(0, $this->invoiceConfirmLinks($html)->length);
    }
}
