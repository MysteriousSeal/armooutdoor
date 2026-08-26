<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\AccountingController;
use App\Models\AccountingEntry;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Le journal des ventes d'un mois, en PDF. */
class AccountingSalesPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ShippingSeeder::class);
        $this->travelTo('2026-08-26 10:00:00');
    }

    private function order(string $placedAt, array $overrides = []): Order
    {
        $order = Order::query()->create(array_merge([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create(['first_name' => 'Camille', 'last_name' => 'Roy'])->id,
            'status' => 'delivered',
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 10000, 'shipping_cents' => 0, 'discount_cents' => 0,
            'total_cents' => 10000, 'payment_method' => 'card',
        ], $overrides));

        $order->forceFill(['created_at' => $placedAt])->save();

        return $order->refresh();
    }

    private function entry(string $on, array $overrides = []): AccountingEntry
    {
        return AccountingEntry::query()->create(array_merge([
            'section' => 'sales',
            'entered_on' => $on,
            'invoice_number' => 'INV-2026-014',
            'client' => 'Club de Nantes',
            'type' => 'prestation',
            'total_cents' => 24000,
            'fees_cents' => 1250,
            'payment_method' => 'bank_wire',
            'remark' => 'Initiation',
        ], $overrides));
    }

    private function render(string $month = '2026-03'): string
    {
        $period = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();

        // Les données viennent du contrôleur, pas d'un calcul refait ici :
        // sinon le test vérifierait sa propre arithmétique.
        $controller = app(AccountingController::class);
        $method = new \ReflectionMethod($controller, 'journalData');
        $method->setAccessible(true);

        return view('admin.accounting.sales-pdf', $method->invoke($controller, $period))->render();
    }

    public function test_the_month_downloads_under_its_own_name(): void
    {
        $this->order('2026-03-12 09:00:00');

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/sales/2026-03/pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('ventes-2026-03.pdf');
    }

    public function test_the_page_offers_the_download(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/sales/2026-03')
            ->assertOk()
            ->assertSee('/admin/accounting/sales/2026-03/pdf', false)
            ->assertSee('Download PDF');
    }

    public function test_the_journal_carries_orders_and_hand_written_entries(): void
    {
        $order = $this->order('2026-03-12 09:00:00');
        $this->entry('2026-03-14');

        $html = $this->render();

        $this->assertStringContainsString('Journal des ventes', $html);
        $this->assertStringContainsString('INV-'.$order->number, $html);
        $this->assertStringContainsString('Camille ROY', $html);
        $this->assertStringContainsString('INV-2026-014', $html);
        $this->assertStringContainsString('Club de Nantes', $html);
        $this->assertStringContainsString('Saisie', $html);
    }

    public function test_the_totals_match_the_screen(): void
    {
        $this->order('2026-03-02 09:00:00', ['payment_fee_cents' => 250]);
        $this->entry('2026-03-14');

        $html = $this->render();

        // 100 € + 240 € = 340 € ; 2,50 € + 12,50 € de frais ; 325 € perçus.
        $this->assertStringContainsString('340,00', $html);
        $this->assertStringContainsString('15,00', $html);
        $this->assertStringContainsString('325,00', $html);
    }

    public function test_a_refund_is_written_down_but_left_out_of_the_total(): void
    {
        $refunded = $this->order('2026-03-05 09:00:00', ['status' => 'refunded', 'total_cents' => 4000]);
        $this->order('2026-03-06 09:00:00', ['total_cents' => 10000]);

        $html = $this->render();

        $this->assertStringContainsString('INV-'.$refunded->number, $html);
        // La facture barrée suffit : une étiquette répéterait le trait.
        $this->assertStringNotContainsString('Remboursé', $html);
        $this->assertMatchesRegularExpression('/tr\.refunded \.col-invoice\s*\{[^}]*line-through/s', $html);
        $this->assertStringContainsString('<tr class="refunded">', $html);
        $this->assertStringContainsString('1 remboursement hors total', $html);
        // 100 € seuls : les 40 € remboursés ne s'ajoutent pas.
        $this->assertStringContainsString('100,00', $html);
        $this->assertStringNotContainsString('140,00', $html);
    }

    public function test_the_sheet_can_be_signed(): void
    {
        $this->order('2026-03-12 09:00:00');

        $html = $this->render();

        $company = CompanySetting::current()->value('company_name');

        // Le journal appartient à la société, pas à l'enseigne.
        $this->assertStringContainsString('class="signature"', $html);
        $this->assertStringContainsString('Pour '.$company, $html);
        $this->assertStringNotContainsString('Pour ArmoOutdoor', $html);
        $this->assertStringContainsString('<div class="sign-rule"></div>', $html);
    }

    public function test_only_that_month_is_on_the_sheet(): void
    {
        $inside = $this->order('2026-03-31 23:30:00');
        $outside = $this->order('2026-04-01 00:30:00');

        $html = $this->render();

        $this->assertStringContainsString('INV-'.$inside->number, $html);
        $this->assertStringNotContainsString('INV-'.$outside->number, $html);
    }

    public function test_a_month_outside_the_period_has_no_sheet(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/accounting/sales/2025-12/pdf')->assertNotFound();
        $this->actingAs($admin)->get('/admin/accounting/sales/2026-09/pdf')->assertNotFound();
    }

    public function test_a_staff_admin_cannot_download_it(): void
    {
        $this->actingAs(User::factory()->staffAdmin()->create())
            ->get('/admin/accounting/sales/2026-03/pdf')
            ->assertForbidden();
    }

    public function test_the_sheet_is_landscape(): void
    {
        $this->order('2026-03-12 09:00:00');

        $pdf = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/sales/2026-03/pdf')
            ->assertOk()
            ->getContent();

        // A4 couché : 842 points de large. Debout, les dix colonnes se
        // replieraient les unes sur les autres.
        preg_match('/MediaBox\s*\[[\d.\s]*?([\d.]+)\s+([\d.]+)\s*\]/', $pdf, $box);

        $this->assertNotEmpty($box, 'Pas de format de page dans le PDF.');
        $this->assertGreaterThan((float) $box[2], (float) $box[1]);
    }

    public function test_the_brand_never_appears_on_the_journal(): void
    {
        $this->order('2026-03-12 09:00:00');

        $html = $this->render();
        $company = CompanySetting::current()->value('company_name');

        // C'est un document de la société : ni le nom de l'enseigne ni sa
        // signature n'ont à figurer sur le livre de comptes.
        $this->assertStringNotContainsString('ArmoOutdoor', $html);
        $this->assertStringNotContainsString('Stand et terrain', $html);
        $this->assertStringContainsString($company, $html);
    }

    public function test_the_remark_column_is_gone(): void
    {
        $this->entry('2026-03-14', ['remark' => 'Initiation du samedi']);

        $html = $this->render();

        // La remarque encombrait dix colonnes déjà serrées.
        $this->assertStringNotContainsString('Remarque', $html);
        $this->assertStringNotContainsString('Initiation du samedi', $html);
    }

    public function test_the_totals_carry_their_own_headings(): void
    {
        $this->order('2026-03-12 09:00:00');

        $html = $this->render();
        $foot = substr($html, strpos($html, '<tfoot>'));

        // Sur une page longue, le bas du tableau se lit sans remonter.
        $this->assertSame(3, substr_count($foot, 'foot-label'));
        $this->assertStringContainsString('>Total<', $foot);
        $this->assertStringContainsString('>Frais<', $foot);
        $this->assertStringContainsString('>Perçu<', $foot);
    }

    public function test_the_sheet_speaks_french_throughout(): void
    {
        $this->order('2026-03-12 09:00:00');
        $this->entry('2026-03-14', ['type' => 'repair', 'payment_method' => 'cheque']);

        $html = $this->render();

        // Le mois, la nature et le règlement : un livre de comptes français
        // ne doit pas garder des mots de l'écran d'administration.
        // Le mois porte une capitale : c'est un titre, pas une phrase.
        $this->assertStringContainsString('Mars 2026', $html);
        $this->assertStringNotContainsString('mars 2026', $html);
        $this->assertStringContainsString('Vente sur stock', $html);
        $this->assertStringContainsString('Réparation', $html);
        $this->assertStringContainsString('Chèque', $html);

        foreach (['March 2026', 'Stock sale', 'Repair', 'Cheque', 'Bank wire'] as $english) {
            $this->assertStringNotContainsString($english, $html);
        }
    }

    public function test_the_payment_column_stands_apart_from_the_amounts(): void
    {
        $this->order('2026-03-12 09:00:00');

        $html = $this->render();

        // Le règlement finit au bord droit du tableau, comme la date commence
        // au bord gauche : c'est l'alignement qui l'écarte du perçu, sans
        // filet ni colonne vide.
        $this->assertMatchesRegularExpression('/\.col-payment\s*\{[^}]*text-align:\s*right/s', $html);
        $this->assertDoesNotMatchRegularExpression('/\.col-payment\s*\{[^}]*border-left:\s*[^0]/s', $html);
        $this->assertStringNotContainsString('col-gap', $html);
    }

    public function test_the_columns_fit_the_page(): void
    {
        $this->order('2026-03-12 09:00:00');

        $html = $this->render();

        preg_match_all('/\.col-[a-z-]+\s*\{[^}]*width:\s*(\d+)%/s', $html, $matches);

        // `col-money` sert deux colonnes : sa largeur compte deux fois.
        $widths = array_map('intval', $matches[1]);
        $total = array_sum($widths) + 9;

        $this->assertLessThanOrEqual(100, $total, 'Les colonnes débordent de la page.');
    }

    public function test_an_accented_month_is_capitalised_intact(): void
    {
        $this->order('2026-08-03 09:00:00');

        $html = $this->render('2026-08');

        // Le mois s'écrit en entier et garde son accent une fois capitalisé.
        $this->assertStringContainsString('Août 2026', $html);
        $this->assertStringContainsString('1 Août au 31 Août 2026', $html);
    }

    public function test_a_long_client_name_stays_on_one_line(): void
    {
        $order = $this->order('2026-03-12 09:00:00');
        $order->user->update(['first_name' => 'Briek', 'last_name' => 'Vancompernolle']);

        $html = $this->render();

        // La colonne doit tenir le nom le plus long du mois sans le replier :
        // une ligne sur deux hauteurs déforme tout le tableau.
        $this->assertStringContainsString('Briek VANCOMPERNOLLE', $html);
        preg_match('/\.col-client\s*\{[^}]*width:\s*(\d+)%/s', $html, $width);
        $this->assertGreaterThanOrEqual(15, (int) $width[1]);
    }

    public function test_the_month_in_progress_has_no_sheet(): void
    {
        // On est en août : le mois encaisse encore, deux éditions du même
        // journal ne diraient pas la même chose.
        $this->order('2026-08-03 09:00:00');

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/sales/2026-08/pdf')
            ->assertNotFound();
    }

    public function test_the_button_is_greyed_out_until_the_month_ends(): void
    {
        $admin = User::factory()->admin()->create();

        $running = $this->actingAs($admin)->get('/admin/accounting/sales/2026-08')->assertOk();
        $running->assertSee('is-disabled', false)
            ->assertSee('Available once the month has ended')
            ->assertDontSee('/admin/accounting/sales/2026-08/pdf', false);

        // Un mois clos garde son bouton vivant.
        $closed = $this->actingAs($admin)->get('/admin/accounting/sales/2026-07')->assertOk();
        $closed->assertSee('/admin/accounting/sales/2026-07/pdf', false)
            ->assertDontSee('is-disabled', false);
    }

    public function test_the_sheet_carries_the_company_address_whatever_the_settings_say(): void
    {
        CompanySetting::current()->update(['contact_email' => 'boutique@armooutdoor.fr']);

        $this->order('2026-03-12 09:00:00');

        $html = $this->render();

        // Le journal est un document de la société : le contact du magasin
        // peut changer dans les réglages sans que le livre de comptes bouge.
        $this->assertStringContainsString('hello@swiftshelf.fr', $html);
        $this->assertStringNotContainsString('boutique@armooutdoor.fr', $html);
    }
}
