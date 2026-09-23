<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two tabs after Refunded: orders still missing their package picture, and
 * orders missing their shipping label since labels started being kept.
 */
class OrderMissingFilesTabsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-23 12:00:00');
    }

    private function order(array $overrides = []): Order
    {
        // Dates and flags the model does not take in bulk are set afterwards.
        $forced = array_intersect_key($overrides, array_flip(['created_at', 'archived_at', 'test_marked_at', 'package_photo_path', 'shipping_label_path']));
        $overrides = array_diff_key($overrides, $forced);

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
            ...$overrides,
        ]);

        if ($forced !== []) {
            $order->forceFill($forced)->saveQuietly();
        }

        return $order;
    }

    private function tab(string $tab): string
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.orders.index', ['tab' => $tab]))
            ->assertOk()
            ->getContent();
    }

    public function test_missing_picture_lists_every_status_without_a_photo(): void
    {
        $placed = $this->order(['status' => 'placed']);
        $refunded = $this->order(['status' => 'refunded']);
        $old = $this->order(['status' => 'delivered', 'created_at' => '2026-01-10 10:00:00']);
        $withPhoto = $this->order(['package_photo_path' => 'orders/package-photos/a.webp']);

        $html = $this->tab('missing_photo');

        foreach ([$placed, $refunded, $old] as $order) {
            $this->assertStringContainsString($order->number, $html);
        }
        $this->assertStringNotContainsString($withPhoto->number, $html);
    }

    /** Labels from before 1 September 2026 are gone: those orders are not missing one. */
    public function test_missing_label_starts_on_the_first_of_september(): void
    {
        $before = $this->order(['created_at' => '2026-08-31 23:00:00']);
        $onTheDay = $this->order(['created_at' => '2026-09-01 08:00:00']);
        $withLabel = $this->order(['shipping_label_path' => 'orders/shipping-labels/a.pdf']);
        $letter = $this->order(['carrier_snapshot' => ['slug' => 'lettre-suivie', 'name' => ['fr' => 'Lettre suivie']]]);

        $html = $this->tab('missing_label');

        $this->assertStringContainsString($onTheDay->number, $html);
        $this->assertStringContainsString($letter->number, $html);
        $this->assertStringNotContainsString($before->number, $html);
        $this->assertStringNotContainsString($withLabel->number, $html);
    }

    /** The working list only: drafts, archived and test orders stay out. */
    public function test_drafts_archived_and_test_orders_are_left_out(): void
    {
        $draft = $this->order(['status' => 'draft']);
        $archived = $this->order(['archived_at' => now()]);
        $test = $this->order(['test_marked_at' => now()]);

        foreach (['missing_photo', 'missing_label'] as $tab) {
            $html = $this->tab($tab);

            foreach ([$draft, $archived, $test] as $order) {
                $this->assertStringNotContainsString($order->number, $html, $tab);
            }
        }
    }

    public function test_the_tabs_sit_after_refunded_with_their_counts(): void
    {
        $this->order();
        $this->order(['package_photo_path' => 'x.webp', 'shipping_label_path' => 'x.pdf']);
        $this->order(['created_at' => '2026-08-01 10:00:00']);

        $html = $this->tab('orders');

        $refunded = strpos($html, 'Refunded');
        $photo = strpos($html, 'Missing package picture');
        $label = strpos($html, 'Missing shipping label');
        $archived = strpos($html, 'Archived <span');

        $this->assertTrue($refunded < $photo && $photo < $label && $label < $archived);
        $this->assertMatchesRegularExpression('/Missing package picture <span class="admin-tab-count">2<\/span>/', $html);
        $this->assertMatchesRegularExpression('/Missing shipping label <span class="admin-tab-count">1<\/span>/', $html);
    }

    public function test_an_empty_tab_says_so(): void
    {
        $this->order(['package_photo_path' => 'x.webp', 'shipping_label_path' => 'x.pdf']);

        $this->assertStringContainsString('Every order has its package picture.', $this->tab('missing_photo'));
        $this->assertStringContainsString('Every order since 01/09/2026 has its shipping label.', $this->tab('missing_label'));
    }
}
