<?php

namespace Tests\Feature\Admin;

use App\Models\Marketplace;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * The marketplaces an order can be attributed to.
 *
 * They are managed from the order settings page, and two things about them
 * are easy to break without noticing: the name is fixed once created —
 * orders are attributed by it — and removing one must not take the history
 * of what was sold there with it.
 */
class MarketplaceSettingsTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $existingLogos = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Logos are written to the public directory rather than a fake disk.
        $this->existingLogos = $this->logoFiles();
    }

    protected function tearDown(): void
    {
        foreach (array_diff($this->logoFiles(), $this->existingLogos) as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    /** @return array<int, string> */
    private function logoFiles(): array
    {
        return glob(public_path('images/marketplaces/*')) ?: [];
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_a_marketplace_is_created_from_the_order_settings_page(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/settings/marketplaces', ['name' => 'Vinted', 'note' => '12% commission'])
            ->assertRedirect(route('admin.settings.orders.edit'));

        $marketplace = Marketplace::query()->firstOrFail();

        $this->assertSame('Vinted', $marketplace->name);
        $this->assertSame('12% commission', $marketplace->note);
    }

    public function test_the_same_marketplace_cannot_be_added_twice(): void
    {
        Marketplace::query()->create(['name' => 'Vinted']);

        $this->actingAs($this->admin())
            ->post('/admin/settings/marketplaces', ['name' => 'Vinted'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Marketplace::query()->count());
    }

    public function test_a_marketplace_keeps_its_name_when_edited(): void
    {
        // Orders carry the channel name as a snapshot; renaming a marketplace
        // would leave those orders pointing at a name that no longer exists.
        $marketplace = Marketplace::query()->create(['name' => 'Vinted', 'note' => 'Old note']);

        $this->actingAs($this->admin())
            ->put('/admin/settings/marketplaces/'.$marketplace->id, [
                'name' => 'Renamed',
                'note' => 'New note',
            ])
            ->assertRedirect(route('admin.settings.orders.edit'));

        $marketplace->refresh();

        $this->assertSame('Vinted', $marketplace->name);
        $this->assertSame('New note', $marketplace->note);
    }

    public function test_a_logo_is_stored_and_shown_on_the_settings_page(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/settings/marketplaces', [
                'name' => 'Vinted',
                'logo' => UploadedFile::fake()->image('vinted.png', 400, 400),
            ])
            ->assertRedirect(route('admin.settings.orders.edit'));

        $logo = Marketplace::query()->firstOrFail()->logo;

        $this->assertNotNull($logo);
        $this->assertStringStartsWith('marketplaces/vinted-', $logo);
        $this->assertFileExists(public_path('images/'.$logo));
    }

    public function test_removing_a_logo_clears_the_field_and_the_file(): void
    {
        $this->actingAs($this->admin())->post('/admin/settings/marketplaces', [
            'name' => 'Vinted',
            'logo' => UploadedFile::fake()->image('vinted.png', 400, 400),
        ]);

        $marketplace = Marketplace::query()->firstOrFail();
        $file = public_path('images/'.$marketplace->logo);

        $this->actingAs($this->admin())
            ->put('/admin/settings/marketplaces/'.$marketplace->id, ['remove_logo' => '1'])
            ->assertRedirect(route('admin.settings.orders.edit'));

        $this->assertNull($marketplace->fresh()->logo);
        $this->assertFileDoesNotExist($file);
    }

    public function test_a_removed_marketplace_leaves_its_orders_readable(): void
    {
        $marketplace = Marketplace::query()->create(['name' => 'Vinted']);
        $order = Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
            'status' => 'delivered',
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000,
            'shipping_cents' => 0,
            'discount_cents' => 0,
            'total_cents' => 1000,
            'payment_method' => 'card',
            'marketplace_id' => $marketplace->id,
            'marketplace_name' => 'Vinted',
        ]);

        $this->actingAs($this->admin())
            ->delete('/admin/settings/marketplaces/'.$marketplace->id)
            ->assertRedirect(route('admin.settings.orders.edit'));

        $order->refresh();

        // The link goes, the sale stays: the channel is still named on the
        // order, which is what every figure is grouped by.
        $this->assertNull($order->marketplace_id);
        $this->assertSame('Vinted', $order->marketplace_name);
        $this->assertSame(0, Marketplace::query()->count());
    }

    public function test_the_settings_page_lists_the_marketplaces(): void
    {
        Marketplace::query()->create(['name' => 'Vinted']);

        $this->actingAs($this->admin())
            ->get('/admin/settings/orders')
            ->assertOk()
            ->assertSee('Vinted');
    }
}
