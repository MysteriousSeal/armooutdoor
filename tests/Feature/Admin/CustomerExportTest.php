<?php

namespace Tests\Feature\Admin;

use App\Models\Address;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The customers CSV.
 *
 * It is built from the same query as the list above it, so it has to agree
 * with it on every point: the same tab, the same search, and above all the
 * same idea of what counts as an order. A spreadsheet handed to somebody
 * else is exactly where a test order or a draft would go unnoticed.
 */
class CustomerExportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function customer(array $overrides = []): User
    {
        return User::factory()->create($overrides);
    }

    private function order(User $customer, array $overrides = []): Order
    {
        return Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => $customer->id,
            'status' => 'delivered',
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

    private function export(array $query = []): string
    {
        return $this->actingAs($this->admin())
            ->get('/admin/customers/export'.($query === [] ? '' : '?'.http_build_query($query)))
            ->assertOk()
            ->streamedContent();
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function rows(string $csv): array
    {
        $rows = array_map('str_getcsv', array_filter(explode("\n", trim($csv))));

        return array_slice($rows, 1);
    }

    public function test_the_file_downloads_as_a_dated_csv(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/customers/export')->assertOk();

        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $response->assertDownload('customers-'.now()->format('Y-m-d').'.csv');
    }

    public function test_the_header_row_names_every_column(): void
    {
        $this->assertStringStartsWith(
            'Name,Email,Orders,Spent,Addresses,Joined',
            $this->export(),
        );
    }

    public function test_a_customer_is_exported_with_their_orders_spend_and_addresses(): void
    {
        $customer = $this->customer(['first_name' => 'Julien', 'last_name' => 'Marchand', 'email' => 'julien@example.com']);
        Address::factory()->create(['user_id' => $customer->id]);
        $this->order($customer, ['total_cents' => 1500]);
        $this->order($customer, ['total_cents' => 2500]);

        $row = $this->rows($this->export())[0];

        // The list writes the family name in capitals; the CSV must match it.
        $this->assertSame('Julien MARCHAND', $row[0]);
        $this->assertSame('julien@example.com', $row[1]);
        $this->assertSame('2', $row[2]);
        $this->assertSame('40.00', $row[3]);
        $this->assertSame('1', $row[4]);
        $this->assertSame($customer->created_at->format('Y-m-d'), $row[5]);
    }

    public function test_a_test_order_moves_neither_the_count_nor_the_spend(): void
    {
        $customer = $this->customer();
        $this->order($customer, ['total_cents' => 1500]);
        $this->order($customer, ['total_cents' => 9900, 'test_marked_at' => now()]);

        $row = $this->rows($this->export())[0];

        $this->assertSame('1', $row[2]);
        $this->assertSame('15.00', $row[3]);
    }

    public function test_a_draft_is_not_an_order_yet(): void
    {
        $customer = $this->customer();
        $this->order($customer, ['status' => 'draft', 'total_cents' => 4200]);

        $row = $this->rows($this->export())[0];

        $this->assertSame('0', $row[2]);
        $this->assertSame('0.00', $row[3]);
    }

    public function test_a_refund_still_counts_as_an_order_but_not_as_money_spent(): void
    {
        $customer = $this->customer();
        $this->order($customer, ['status' => 'refunded', 'total_cents' => 3000]);
        $this->order($customer, ['total_cents' => 1500]);

        $row = $this->rows($this->export())[0];

        $this->assertSame('2', $row[2]);
        $this->assertSame('15.00', $row[3]);
    }

    public function test_admins_and_marketplace_accounts_stay_out_of_the_file(): void
    {
        // An external user stands for a marketplace buyer, not somebody with
        // an account on the shop.
        $this->customer(['first_name' => 'Real', 'last_name' => 'Shopper']);
        User::factory()->admin()->create(['first_name' => 'Back', 'last_name' => 'Office']);
        $this->customer(['first_name' => 'Naturabuy', 'last_name' => 'Buyer', 'external' => true]);

        $csv = $this->export();

        $this->assertStringContainsString('Real SHOPPER', $csv);
        $this->assertStringNotContainsString('Back OFFICE', $csv);
        $this->assertStringNotContainsString('Naturabuy BUYER', $csv);
    }

    public function test_the_search_box_carries_over_to_the_file(): void
    {
        $this->customer(['first_name' => 'Julien', 'last_name' => 'Marchand']);
        $this->customer(['first_name' => 'Marc', 'last_name' => 'Vasseur']);

        $csv = $this->export(['search' => 'Marchand']);

        $this->assertStringContainsString('Julien MARCHAND', $csv);
        $this->assertStringNotContainsString('Marc VASSEUR', $csv);
    }

    public function test_the_tabs_carry_over_to_the_file(): void
    {
        $buyer = $this->customer(['first_name' => 'Has', 'last_name' => 'Ordered']);
        $this->order($buyer);
        $this->customer(['first_name' => 'Never', 'last_name' => 'Ordered']);

        $withOrders = $this->export(['tab' => 'with-orders']);
        $this->assertStringContainsString('Has ORDERED', $withOrders);
        $this->assertStringNotContainsString('Never ORDERED', $withOrders);

        $withoutOrders = $this->export(['tab' => 'no-orders']);
        $this->assertStringContainsString('Never ORDERED', $withoutOrders);
        $this->assertStringNotContainsString('Has ORDERED', $withoutOrders);
    }

    public function test_the_date_filters_carry_over_to_the_file(): void
    {
        $old = $this->customer(['first_name' => 'Long', 'last_name' => 'Standing']);
        $old->forceFill(['created_at' => now()->subYear()])->save();
        $this->customer(['first_name' => 'Just', 'last_name' => 'Joined']);

        $csv = $this->export(['date_from' => now()->subDays(7)->format('Y-m-d')]);

        $this->assertStringContainsString('Just JOINED', $csv);
        $this->assertStringNotContainsString('Long STANDING', $csv);
    }
}
