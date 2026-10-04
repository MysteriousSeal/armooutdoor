<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\AccountingController;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * A refund marked from 1 October 2026 on: the sale stays a normal line in its
 * own month, and a refund line in the month it was refunded takes it back off.
 */
class AccountingRefundLinesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ShippingSeeder::class);
    }

    /** A real sale placed on the given date, `created_at` forced as it is not fillable. */
    private function order(string $placedAt, array $overrides = []): Order
    {
        $this->travelTo($placedAt);

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

        return $order->refresh();
    }

    private function refund(Order $order, string $on): void
    {
        $this->travelTo($on);
        $order->markStatus('refunded');
    }

    private function page(string $month): TestResponse
    {
        $this->travelTo('2026-11-15 10:00:00');

        return $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/sales/'.$month)
            ->assertOk();
    }

    private function foot(TestResponse $response): string
    {
        $content = $response->getContent();

        return substr($content, strpos($content, '<tfoot>'));
    }

    public function test_the_sale_stays_whole_in_its_own_month(): void
    {
        $order = $this->order('2026-09-20 09:00:00', ['total_cents' => 4000]);
        $this->refund($order, '2026-10-04 11:00:00');

        $response = $this->page('2026-09')
            ->assertSee('INV-'.$order->number)
            ->assertDontSee('AV-'.$order->number)
            ->assertDontSee('is-refunded', false)
            ->assertDontSee('refund left out')
            ->assertSee('1 sale');

        $this->assertStringContainsString('40,00', $this->foot($response));
    }

    public function test_the_refund_month_gets_a_line_that_takes_the_sale_back_off(): void
    {
        $order = $this->order('2026-09-20 09:00:00', [
            'total_cents' => 4000,
            'payment_fee_cents' => 100,
            'marketplace_bonus_cents' => 300,
        ]);
        $this->refund($order, '2026-10-04 11:00:00');
        $this->order('2026-10-10 09:00:00', ['total_cents' => 10000, 'payment_fee_cents' => 250]);

        $response = $this->page('2026-10')
            ->assertSee('AV-'.$order->number)
            ->assertSee('04/10/2026')
            ->assertSee('Refund')
            ->assertSee('is-refund', false)
            // The refund line links to the order, like its sale line does.
            ->assertSee(route('admin.orders.show', $order), false)
            // Fees and bonus turn around: the fees come back, the bonus goes.
            ->assertSee('+1,00', false)
            ->assertSee('−3,00', false)
            ->assertSeeInOrder(['1 sale', '· 1 refund'], false);

        $foot = $this->foot($response);

        // 100 € - 40 € = 60 €; fees 2,50 € - 1 € = 1,50 €; bonus 0 - 3 € = -3 €;
        // perceived 60 - 1,50 - 3 = 55,50 €.
        $this->assertStringContainsString('60,00', $foot);
        $this->assertStringContainsString('1,50', $foot);
        $this->assertStringContainsString('−3,00', $foot);
        $this->assertStringContainsString('55,50', $foot);
    }

    public function test_a_sale_and_its_refund_in_the_same_month_cancel_out(): void
    {
        $order = $this->order('2026-10-02 09:00:00', ['total_cents' => 4000, 'payment_fee_cents' => 100]);
        $this->refund($order, '2026-10-05 11:00:00');

        $response = $this->page('2026-10')
            ->assertSee('INV-'.$order->number)
            ->assertSee('AV-'.$order->number)
            ->assertDontSee('is-refunded', false)
            ->assertSeeInOrder(['1 sale', '· 1 refund'], false);

        $this->assertStringContainsString('0,00', $this->foot($response));
        $this->assertStringNotContainsString('40,00', $this->foot($response));
    }

    public function test_a_refund_from_before_october_is_still_struck_through(): void
    {
        $order = $this->order('2026-09-05 09:00:00', ['total_cents' => 4000]);
        $this->refund($order, '2026-09-25 11:00:00');

        $this->page('2026-09')
            ->assertSee('is-refunded', false)
            ->assertSee('1 refund left out')
            ->assertDontSee('AV-'.$order->number);
    }

    public function test_the_month_list_counts_the_refund_line_in_its_month(): void
    {
        $order = $this->order('2026-09-20 09:00:00');
        $this->refund($order, '2026-10-04 11:00:00');

        $this->travelTo('2026-11-15 10:00:00');

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/accounting/sales')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('#September.*?1 entry#s', $html);
        $this->assertMatchesRegularExpression('#October.*?1 entry#s', $html);
    }

    public function test_a_test_order_gets_no_refund_line(): void
    {
        $order = $this->order('2026-09-20 09:00:00', ['test_marked_at' => '2026-09-20 10:00:00']);
        $this->refund($order, '2026-10-04 11:00:00');

        $this->page('2026-10')->assertDontSee('AV-'.$order->number);
    }

    public function test_the_journal_pdf_prints_the_refund_line_and_deducts_it(): void
    {
        $order = $this->order('2026-09-20 09:00:00', ['total_cents' => 4000]);
        $this->refund($order, '2026-10-04 11:00:00');
        $this->order('2026-10-10 09:00:00', ['total_cents' => 10000]);
        $this->travelTo('2026-11-15 10:00:00');

        // The data comes from the controller, as in the PDF test.
        $controller = app(AccountingController::class);
        $method = new \ReflectionMethod($controller, 'journalData');
        $html = view('admin.accounting.sales-pdf', $method->invoke($controller, CarbonImmutable::parse('2026-10-01')))->render();

        $this->assertStringContainsString('AV-'.$order->number, $html);
        $this->assertStringContainsString('Remboursement', $html);
        $this->assertStringContainsString('dont 1 remboursement déduit', $html);
        $this->assertStringContainsString('60,00', substr($html, strpos($html, '<tfoot>')));
    }
}
