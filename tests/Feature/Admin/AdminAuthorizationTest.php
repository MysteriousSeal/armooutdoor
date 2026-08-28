<?php

namespace Tests\Feature\Admin;

use App\Models\AccountingEntry;
use App\Models\BlogPost;
use App\Models\Carrier;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Discount;
use App\Models\DiscountCode;
use App\Models\Marketplace;
use App\Models\Order;
use App\Models\PackageType;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Support\AccountingPeriods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Every admin.* web route (except the login page/submit) must reject a
 * guest and a signed-in non-admin user, redirecting to the admin login
 * page rather than exposing any admin content. Walking the live route
 * registry (rather than a hand-maintained list) means a newly added
 * admin route is covered automatically without remembering to update
 * this test.
 */
class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private const EXCLUDED_ROUTE_NAMES = [
        'admin.login',
        'admin.login.store',
        'admin.logout',
    ];

    public function test_every_admin_route_rejects_guests_and_non_admin_users(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'label' => ['en' => 'M', 'fr' => 'M'],
            'quantity' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $discount = Discount::query()->create(['product_id' => $product->id, 'type' => 'percentage', 'value' => 10]);
        $discountCode = DiscountCode::query()->create(['code' => 'AUDIT', 'type' => 'percentage', 'value' => 10]);
        $customer = User::factory()->create();
        $targetAdmin = User::factory()->admin()->create();
        $order = Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => $customer->id,
            'status' => 'placed',
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000,
            'shipping_cents' => 500,
            'discount_cents' => 0,
            'total_cents' => 1500,
            'payment_method' => 'card',
        ]);
        $supplier = Supplier::query()->create(['name' => 'Audit Supplier']);
        $purchaseOrder = PurchaseOrder::factory()->create();
        $marketplace = Marketplace::query()->create(['name' => 'Audit Marketplace']);
        $packageType = PackageType::query()->create(['name' => 'Audit Box']);
        $conversation = Conversation::query()->create([
            'name' => 'Audit Sender',
            'email' => 'audit@example.com',
            'subject' => 'Audit',
        ]);
        $conversationMessage = $conversation->postMessage('Audit message', ConversationMessage::AUTHOR_ADMIN, $targetAdmin);
        $carrier = Carrier::query()->first() ?? Carrier::query()->create([
            'slug' => 'audit-carrier',
            'name' => ['en' => 'Audit', 'fr' => 'Audit'],
            'description' => ['en' => '', 'fr' => ''],
            'eta' => ['en' => '', 'fr' => ''],
            'method' => 'home',
            'price_cents' => 500,
            'active' => true,
            'sort_order' => 1,
        ]);

        $blogPost = BlogPost::factory()->create();

        // A manual review needs no account and no order, the cheapest kind
        // to stand in for the {review} parameter.
        $review = ProductReview::query()->create([
            'product_id' => $product->id,
            'author_name' => 'Audit Reviewer',
            'rating' => 5,
            'comment' => 'Audit review.',
        ]);

        $accountingEntry = AccountingEntry::query()->create([
            'section' => 'sales',
            'entered_on' => AccountingPeriods::FIRST.'-05',
            'type' => 'other',
            'total_cents' => 1000,
            'fees_cents' => 0,
            'payment_method' => 'bank_wire',
        ]);

        $bindings = [
            'category' => $category->id,
            'product' => $product->id,
            'variant' => $variant->id,
            'discount' => $discount->id,
            'discountCode' => $discountCode->id,
            'customer' => $customer->id,
            'order' => $order->number,
            // Lié par numéro : PurchaseOrder redéfinit getRouteKeyName().
            'purchaseOrder' => $purchaseOrder->number,
            'supplier' => $supplier->id,
            'marketplace' => $marketplace->id,
            'packageType' => $packageType->id,
            'carrier' => $carrier->id,
            'admin' => $targetAdmin->id,
            'conversation' => $conversation->id,
            'message' => $conversationMessage->id,
            'post' => $blogPost->id,
            'review' => $review->id,
            // A month inside the accounting period: outside it the route
            // does not even match, and this sweep would read a 404 as an
            // unguarded door.
            'month' => AccountingPeriods::FIRST,
            'section' => 'sales',
            'entry' => $accountingEntry->id,
        ];

        $nonAdmin = User::factory()->create();
        $exercised = 0;

        /** @var RoutingRoute $route */
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null || ! str_starts_with($name, 'admin.') || in_array($name, self::EXCLUDED_ROUTE_NAMES, true)) {
                continue;
            }

            $uri = $route->uri();

            foreach ($bindings as $param => $value) {
                $uri = str_replace('{'.$param.'}', (string) $value, $uri);
            }

            $method = collect($route->methods())->first(fn (string $m): bool => $m !== 'HEAD');
            $exercised++;

            $guestResponse = $this->call($method, '/'.ltrim($uri, '/'));
            $this->assertTrue(
                $guestResponse->isRedirect(route('admin.login')),
                "Guest request to [{$method} {$uri}] (route: {$name}) should redirect to admin login, got {$guestResponse->status()}."
            );

            $nonAdminResponse = $this->actingAs($nonAdmin)->call($method, '/'.ltrim($uri, '/'));
            $this->assertTrue(
                $nonAdminResponse->isRedirect(route('admin.login')),
                "Non-admin request to [{$method} {$uri}] (route: {$name}) should redirect to admin login, got {$nonAdminResponse->status()}."
            );
        }

        // Sanity check: make sure the loop actually walked a meaningful
        // number of routes rather than silently matching nothing.
        $this->assertGreaterThan(50, $exercised, 'Expected to exercise every admin.* route.');
    }

    public function test_admin_dashboard_is_reachable_by_an_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk();
    }
}
