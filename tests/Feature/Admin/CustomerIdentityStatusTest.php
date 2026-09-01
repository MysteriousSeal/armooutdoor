<?php

namespace Tests\Feature\Admin;

use App\Models\IdentityDocument;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Whether a customer has proved their age, on the customer page.
 *
 * The verdict is what somebody packing a restricted order needs. The document
 * itself stays on the one screen allowed to open it.
 */
class CustomerIdentityStatusTest extends TestCase
{
    use RefreshDatabase;

    private function document(User $customer, string $status): IdentityDocument
    {
        return IdentityDocument::query()->create([
            'user_id' => $customer->id,
            'kind' => 'passport',
            'original_name' => 'p.pdf',
            'mime' => 'application/pdf',
            'size_bytes' => 10,
            'path' => $status === 'pending' ? 'identity-documents/x.enc' : null,
            'status' => $status,
        ]);
    }

    private function page(User $customer): TestResponse
    {
        return $this->actingAs(User::factory()->create(['role' => 'admin', 'is_admin' => true]))
            ->get('/admin/customers/'.$customer->id)
            ->assertOk();
    }

    public function test_a_customer_who_sent_nothing_says_so(): void
    {
        $this->page(User::factory()->create())
            ->assertSee('No document', false)
            ->assertSee('doc-state--none', false);
    }

    public function test_a_document_waiting_shows_as_pending(): void
    {
        $customer = User::factory()->create();
        $this->document($customer, 'pending');

        $this->page($customer)->assertSee('Pending verification', false)->assertSee('doc-state--pending', false);
    }

    public function test_a_reviewed_document_shows_its_verdict(): void
    {
        $customer = User::factory()->create();
        $this->document($customer, 'verified')->forceFill(['reviewed_at' => now()])->save();

        $this->page($customer)->assertSee('doc-state--verified', false);
    }

    public function test_a_verdict_outranks_a_later_upload_still_waiting(): void
    {
        // Age proved once is proved; a second document waiting does not undo it.
        $customer = User::factory()->create();
        $this->document($customer, 'verified')->forceFill(['reviewed_at' => now()->subDay()])->save();
        $this->document($customer, 'pending');

        $this->assertSame('verified', $customer->identityStatus()['state']);
    }

    public function test_an_ordinary_admin_sees_the_verdict_and_no_way_in(): void
    {
        $customer = User::factory()->create();
        $this->document($customer, 'pending');

        // The status, but no route to the file from this page.
        $this->page($customer)
            ->assertSee('Pending verification', false)
            ->assertDontSee(route('admin.documents.index'), false)
            ->assertDontSee('/admin/documents/', false);
    }

    public function test_the_panel_shows_the_furthest_expiry_of_all_documents(): void
    {
        // Two verifications, one running longer: the customer is covered to
        // the later of them, whatever the other says.
        $customer = User::factory()->create();
        $this->document($customer, 'verified')->forceFill([
            'expires_at' => now()->addYear(),
            'reviewed_at' => now()->subMonth(),
        ])->save();
        $this->document($customer, 'verified')->forceFill([
            'expires_at' => now()->addYears(4),
            'reviewed_at' => now(),
        ])->save();

        $this->assertTrue(
            now()->addYears(4)->isSameDay($customer->identityStatus()['until']),
        );

        $this->page($customer)
            ->assertSee('Covered until', false)
            ->assertSee(now()->addYears(4)->format('d/m/Y'), false);
    }

    public function test_a_customer_with_nothing_is_covered_until_nothing(): void
    {
        $customer = User::factory()->create();

        $this->assertNull($customer->identityStatus()['until']);
        $this->page($customer)->assertDontSee('Covered until', false);
    }

    public function test_a_lapsed_customer_is_not_told_they_are_covered(): void
    {
        // The lapse date already says when it ran out; repeating it as cover
        // would read as though something still held.
        $customer = User::factory()->create();
        $this->document($customer, 'verified')->forceFill([
            'expires_at' => now()->subDay(),
            'reviewed_at' => now()->subYear(),
        ])->save();

        $this->page($customer)
            ->assertSee('Expired', false)
            ->assertDontSee('Covered until', false);
    }

    public function test_an_order_row_ends_with_its_status(): void
    {
        // The badges are optional and the status is not, so they share one
        // right-hand cell rather than a column each: as columns, the status
        // sat wherever the number of badges happened to leave it.
        $customer = User::factory()->create();

        Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => $customer->id,
            'status' => 'placed',
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000, 'shipping_cents' => 0, 'discount_cents' => 0, 'total_cents' => 1000,
            'payment_method' => 'card',
        ]);

        $html = $this->page($customer)->getContent();

        $this->assertStringContainsString('admin-customer-order-marks', $html);

        // The status badge is the last thing inside that cell.
        preg_match('#<div class="admin-customer-order-marks">(.*?)</div>#s', $html, $marks);
        $this->assertNotEmpty($marks);
        $this->assertStringEndsWith('</span>', trim($marks[1]));
        $this->assertStringContainsString('badge-placed', $marks[1]);
    }

    private function orderFor(User $customer, bool $restricted): Order
    {
        $product = Product::factory()->create(['is_active' => true, 'age_restricted' => $restricted]);

        $order = Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => $customer->id,
            'status' => 'placed',
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000, 'shipping_cents' => 0, 'discount_cents' => 0, 'total_cents' => 1000,
            'payment_method' => 'card',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_slug' => $product->slug,
            'name' => $product->localizedName(),
            'image' => '',
            'unit_price_cents' => 1000, 'quantity' => 1, 'line_cents' => 1000,
        ]);

        return $order;
    }

    public function test_a_restricted_order_row_carries_the_chip(): void
    {
        $customer = User::factory()->create();
        $this->orderFor($customer, true);

        $this->page($customer)
            ->assertSee('order-chip--age-none', false)
            ->assertSee('<span class="order-chip-age-mark" aria-hidden="true">-18</span>', false)
            ->assertSee('Missing', false);
    }

    public function test_an_ordinary_order_row_carries_none(): void
    {
        $customer = User::factory()->create();
        $this->orderFor($customer, false);

        $this->page($customer)->assertDontSee('order-chip--age', false);
    }

    public function test_the_chip_follows_the_customer_verdict(): void
    {
        $customer = User::factory()->create();
        $this->document($customer, 'verified')->forceFill([
            'expires_at' => now()->addYear(),
            'reviewed_at' => now(),
        ])->save();
        $this->orderFor($customer, true);

        $this->page($customer)->assertSee('order-chip--age-verified', false);
    }

    public function test_only_the_restricted_orders_are_chipped(): void
    {
        $customer = User::factory()->create();
        $this->orderFor($customer, true);
        $this->orderFor($customer, false);
        $this->orderFor($customer, false);

        $html = $this->page($customer)->getContent();

        // Counted on the mark, not the class: « order-chip--age » is a
        // substring of « order-chip--age-none » and so appears twice per chip.
        $this->assertSame(1, substr_count($html, 'order-chip-age-mark'));
    }

    public function test_the_status_still_ends_the_row(): void
    {
        // The chip joins the same cell and must not take the last place: the
        // order's own status keeps it.
        $customer = User::factory()->create();
        $this->orderFor($customer, true);

        preg_match(
            '#<div class="admin-customer-order-marks">(.*?)</div>#s',
            $this->page($customer)->getContent(),
            $marks,
        );

        $this->assertStringContainsString('order-chip--age', $marks[1]);
        $this->assertStringContainsString('badge-placed', $marks[1]);
        $this->assertLessThan(
            strpos($marks[1], 'badge-placed'),
            strpos($marks[1], 'order-chip--age'),
            'The -18 chip should come before the order status, not after it.',
        );
    }

    public function test_the_marks_in_a_row_share_one_height(): void
    {
        // .badge is fixed at 1.5rem and .order-chip is sized by its padding,
        // so the status sat visibly shorter than the -18 chip beside it.
        $css = file_get_contents(public_path('css/admin.css'));

        $this->assertMatchesRegularExpression(
            '/\.admin-customer-order-marks \.badge,\s*\.admin-customer-order-marks \.order-chip \{[^}]*height:/s',
            $css,
        );

        // Scoped to this cell: the order list builds every mark from
        // .order-chip already, and its marketplace chip pads around a logo.
        $this->assertStringNotContainsString('.admin-orders-table .order-chip {', $css);
    }
}
