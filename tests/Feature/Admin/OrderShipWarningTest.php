<?php

namespace Tests\Feature\Admin;

use App\Models\AdminActivityLog;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Before an order is marked as shipped, the confirmation says which of the
 * shipping label and package photo are still missing. A reminder, not a lock.
 */
class OrderShipWarningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-25 12:00:00');
    }

    private function order(array $forced = []): Order
    {
        $order = Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
            'status' => 'preparing',
            'address_snapshot' => [
                'first_name' => 'Julien', 'last_name' => 'Marchand', 'line1' => '4 rue des Lilas',
                'postal_code' => '31000', 'city' => 'Toulouse', 'country' => 'FR',
            ],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['slug' => 'colissimo', 'name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 990,
            'shipping_cents' => 350,
            'discount_cents' => 0,
            'total_cents' => 1340,
            'payment_method' => 'card',
        ]);

        if ($forced !== []) {
            $order->forceFill($forced)->saveQuietly();
        }

        return $order->refresh();
    }

    private function page(Order $order): string
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->getContent();
    }

    /** The modal's own part of the page, so other blocks cannot answer for it. */
    private function shipModal(Order $order): string
    {
        $html = $this->page($order);
        $start = strpos($html, 'id="ship-confirm-modal"');
        $this->assertNotFalse($start);

        return substr($html, $start, strpos($html, '</dialog>', $start) - $start);
    }

    public function test_both_missing_are_listed_with_a_link_to_each(): void
    {
        $modal = $this->shipModal($this->order());

        $this->assertStringContainsString('Still missing on this order:', $modal);
        $this->assertStringContainsString('href="#order-shipping-label"', $modal);
        $this->assertStringContainsString('href="#order-package-photo"', $modal);
        $this->assertStringContainsString('Mark as shipped anyway', $modal);
    }

    public function test_nothing_missing_means_the_usual_modal(): void
    {
        $modal = $this->shipModal($this->order([
            'shipping_label_path' => 'orders/shipping-labels/a.pdf',
            'package_photo_path' => 'orders/package-photos/a.webp',
        ]));

        $this->assertStringNotContainsString('Still missing', $modal);
        $this->assertStringNotContainsString('anyway', $modal);
        $this->assertStringContainsString('>Mark as shipped</button>', $modal);
    }

    /** "I have no picture available" is an answer: no photo reminder. */
    public function test_the_no_picture_mark_counts_as_having_the_photo(): void
    {
        $modal = $this->shipModal($this->order([
            'shipping_label_path' => 'orders/shipping-labels/a.pdf',
            'package_photo_unavailable_at' => now(),
        ]));

        $this->assertStringNotContainsString('Still missing', $modal);
    }

    /** Same rule as the Missing shipping label tab: labels before 1 September 2026 are gone. */
    public function test_an_order_from_before_labels_were_kept_is_not_asked_for_one(): void
    {
        $modal = $this->shipModal($this->order([
            'created_at' => '2026-08-20 10:00:00',
            'package_photo_path' => 'orders/package-photos/a.webp',
        ]));

        $this->assertStringNotContainsString('Still missing', $modal);
    }

    public function test_only_the_missing_one_is_listed(): void
    {
        $modal = $this->shipModal($this->order(['shipping_label_path' => 'orders/shipping-labels/a.pdf']));

        $this->assertStringNotContainsString('href="#order-shipping-label"', $modal);
        $this->assertStringContainsString('href="#order-package-photo"', $modal);
    }

    /** The links land on the two upload blocks. */
    public function test_the_upload_blocks_carry_the_anchors(): void
    {
        $html = $this->page($this->order());

        $this->assertStringContainsString('id="order-shipping-label"', $html);
        $this->assertStringContainsString('id="order-package-photo"', $html);
    }

    /** Shipping anyway still ships, and the log says what was missing. */
    public function test_shipping_anyway_is_recorded(): void
    {
        $order = $this->order(['package_photo_path' => 'orders/package-photos/a.webp']);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.orders.ship', $order))
            ->assertRedirect();

        $this->assertSame('shipped', $order->refresh()->status);
        $this->assertSame(
            'Marked order '.$order->number.' as shipped without shipping label',
            AdminActivityLog::query()->where('action', 'order.shipped')->value('description'),
        );
    }

    public function test_a_complete_order_logs_as_before(): void
    {
        $order = $this->order([
            'shipping_label_path' => 'orders/shipping-labels/a.pdf',
            'package_photo_path' => 'orders/package-photos/a.webp',
        ]);

        $this->actingAs(User::factory()->admin()->create())->patch(route('admin.orders.ship', $order));

        $this->assertSame(
            'Marked order '.$order->number.' as shipped',
            AdminActivityLog::query()->where('action', 'order.shipped')->value('description'),
        );
    }

    /** The one-order check and the tab's query never disagree. */
    public function test_the_order_check_matches_the_tabs(): void
    {
        $orders = [
            $this->order(),
            $this->order(['shipping_label_path' => 'a.pdf']),
            $this->order(['package_photo_unavailable_at' => now()]),
            $this->order(['created_at' => '2026-08-31 23:59:00']),
            $this->order(['created_at' => '2026-09-01 00:00:00']),
        ];

        foreach ($orders as $order) {
            $this->assertSame($order->isMissingShippingLabel(), Order::query()->missingShippingLabel()->whereKey($order->id)->exists(), $order->number.' label');
            $this->assertSame($order->isMissingPackagePhoto(), Order::query()->missingPackagePhoto()->whereKey($order->id)->exists(), $order->number.' photo');
        }
    }
}
