<?php

namespace Tests\Feature\Admin;

use App\Models\AdminActivityLog;
use App\Models\Carrier;
use App\Models\Marketplace;
use App\Models\Order;
use App\Models\PackageType;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every admin write that changes something meaningful — order status,
 * tracking, addresses, settings, suppliers, marketplaces, and admin
 * accounts — must leave a row behind saying who did it and when.
 */
class AdminActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ShippingSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function order(string $status = 'placed'): Order
    {
        $customer = User::factory()->create();

        return Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => $customer->id,
            'status' => $status,
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 5000,
            'shipping_cents' => 500,
            'discount_cents' => 0,
            'total_cents' => 5500,
            'payment_method' => 'card',
        ]);
    }

    private function assertLogged(string $action, User $admin): void
    {
        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => $action,
            'user_id' => $admin->id,
        ]);
    }

    public function test_order_status_transitions_are_logged(): void
    {
        $admin = $this->admin();
        $order = $this->order('placed');

        $this->actingAs($admin)->patch('/admin/orders/'.$order->number.'/prepare');
        $this->assertLogged('order.preparing', $admin);

        $this->actingAs($admin)->patch('/admin/orders/'.$order->number.'/ship');
        $this->assertLogged('order.shipped', $admin);

        $this->actingAs($admin)->patch('/admin/orders/'.$order->number.'/refund');
        $this->assertLogged('order.refunded', $admin);
    }

    public function test_tracking_update_is_logged(): void
    {
        $admin = $this->admin();
        $order = $this->order('preparing');

        $this->actingAs($admin)->patch('/admin/orders/'.$order->number.'/tracking', [
            'tracking_number' => 'TRACK123',
        ]);

        $this->assertLogged('order.tracking_updated', $admin);
    }

    public function test_shipping_and_billing_address_updates_are_logged(): void
    {
        $admin = $this->admin();
        $order = $this->order('placed');
        $address = ['first_name' => 'New', 'last_name' => 'Name', 'line1' => '1 rue', 'postal_code' => '75001', 'city' => 'Paris', 'country' => 'FR', 'phone' => '0600000000'];

        $this->actingAs($admin)->patch('/admin/orders/'.$order->number.'/shipping-address', $address);
        $this->assertLogged('order.shipping_address_updated', $admin);

        $this->actingAs($admin)->patch('/admin/orders/'.$order->number.'/billing-address', $address);
        $this->assertLogged('order.billing_address_updated', $admin);
    }

    public function test_supplier_crud_is_logged(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/settings/suppliers', ['name' => 'Acme Supplier']);
        $this->assertLogged('supplier.created', $admin);

        $supplier = Supplier::query()->where('name', 'Acme Supplier')->firstOrFail();

        $this->actingAs($admin)->put('/admin/settings/suppliers/'.$supplier->id, ['name' => 'Acme Supplier Renamed']);
        $this->assertLogged('supplier.updated', $admin);

        $this->actingAs($admin)->delete('/admin/settings/suppliers/'.$supplier->id);
        $this->assertLogged('supplier.deleted', $admin);
    }

    public function test_marketplace_crud_is_logged(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/settings/marketplaces', ['name' => 'Vinted']);
        $this->assertLogged('marketplace.created', $admin);

        $marketplace = Marketplace::query()->where('name', 'Vinted')->firstOrFail();

        $this->actingAs($admin)->put('/admin/settings/marketplaces/'.$marketplace->id, ['note' => 'New note']);
        $this->assertLogged('marketplace.updated', $admin);

        $this->actingAs($admin)->delete('/admin/settings/marketplaces/'.$marketplace->id);
        $this->assertLogged('marketplace.deleted', $admin);
    }

    public function test_package_type_crud_is_logged(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/settings/package-types', ['name' => 'Small Box']);
        $this->assertLogged('package_type.created', $admin);

        $packageType = PackageType::query()->where('name', 'Small Box')->firstOrFail();

        $this->actingAs($admin)->delete('/admin/settings/package-types/'.$packageType->id);
        $this->assertLogged('package_type.deleted', $admin);
    }

    public function test_shipping_company_and_invoice_settings_updates_are_logged(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put('/admin/settings/shipping', ['free_shipping_threshold' => '50']);
        $this->assertLogged('shipping_setting.updated', $admin);

        $this->actingAs($admin)->put('/admin/settings/company', ['company_name' => 'Armo Outdoor SAS']);
        $this->assertLogged('company_setting.updated', $admin);

        $this->actingAs($admin)->put('/admin/settings/invoice', ['invoice_footer_text' => 'Thanks!']);
        $this->assertLogged('invoice_setting.updated', $admin);
    }

    public function test_carrier_price_tier_update_is_logged(): void
    {
        $admin = $this->admin();
        $carrier = Carrier::query()->firstOrFail();

        $this->actingAs($admin)->put('/admin/settings/carriers/'.$carrier->id.'/price-tiers', [
            'default_price' => '5.90',
        ]);

        $this->assertLogged('carrier.price_tiers_updated', $admin);
    }

    public function test_admin_management_actions_are_logged(): void
    {
        $owner = $this->admin();

        $this->actingAs($owner)->post('/admin/settings/admins', [
            'first_name' => 'New',
            'last_name' => 'Admin',
            'email' => 'newadmin@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'staff',
        ]);
        $this->assertLogged('admin.created', $owner);

        $target = User::query()->where('email', 'newadmin@example.com')->firstOrFail();

        $this->actingAs($owner)->put('/admin/settings/admins/'.$target->id, [
            'first_name' => $target->first_name,
            'last_name' => $target->last_name,
            'email' => $target->email,
            'role' => 'owner',
        ]);
        $this->assertLogged('admin.updated', $owner);
        $this->assertLogged('admin.role_changed', $owner);

        $this->actingAs($owner)->patch('/admin/settings/admins/'.$target->id.'/deactivate');
        $this->assertLogged('admin.deactivated', $owner);

        $this->actingAs($owner)->patch('/admin/settings/admins/'.$target->id.'/reactivate');
        $this->assertLogged('admin.reactivated', $owner);
    }

    public function test_activity_log_records_who_did_it(): void
    {
        $admin = $this->admin();
        $order = $this->order('placed');

        $this->actingAs($admin)->patch('/admin/orders/'.$order->number.'/refund');

        $log = AdminActivityLog::query()->where('action', 'order.refunded')->firstOrFail();
        $this->assertTrue($log->user->is($admin));
        $this->assertStringContainsString($order->number, $log->description);
    }
}
