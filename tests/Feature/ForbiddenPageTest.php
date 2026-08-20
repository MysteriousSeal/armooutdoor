<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The 403 page can be raised from either side of the app. Rendering the admin
 * layout to a customer would hand them the whole back-office nav and its
 * shop-wide badge counts, so the page picks its chrome from who is asking.
 */
class ForbiddenPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Shop-wide admin state that must never reach a customer's screen.
     */
    private function seedAdminState(): void
    {
        foreach (range(1, 3) as $i) {
            $conversation = Conversation::query()->create([
                'user_id' => User::factory()->create()->id,
                'name' => 'Client '.$i,
                'email' => 'client'.$i.'@example.com',
                'subject' => 'Question',
            ]);
            $conversation->postMessage('Bonjour', ConversationMessage::AUTHOR_CUSTOMER);
        }

        Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => User::factory()->create()->id,
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
    }

    public function test_a_customer_gets_storefront_chrome(): void
    {
        $this->seedAdminState();
        $this->actingAs(User::factory()->create());

        $html = view('errors.403')->render();

        $this->assertStringContainsString(__('store.forbidden_title'), $html);
        $this->assertStringNotContainsString('admin-nav-links', $html);
        $this->assertStringNotContainsString('This area is limited to owners', $html);
    }

    public function test_a_customer_is_never_shown_the_admin_badge_counts(): void
    {
        $this->seedAdminState();
        $this->actingAs(User::factory()->create());

        $html = view('errors.403')->render();

        // The leak this page used to have: shop-wide unread and order counts.
        $this->assertStringNotContainsString('admin-nav-badge', $html);
        $this->assertStringNotContainsString('not started yet', $html);
    }

    public function test_a_guest_gets_storefront_chrome(): void
    {
        $this->seedAdminState();

        $html = view('errors.403')->render();

        $this->assertStringContainsString(__('store.forbidden_title'), $html);
        $this->assertStringNotContainsString('admin-nav-links', $html);
    }

    public function test_an_admin_still_gets_the_back_office_page(): void
    {
        $this->seedAdminState();
        $this->actingAs(User::factory()->admin()->create());

        $html = view('errors.403')->render();

        $this->assertStringContainsString('This area is limited to owners', $html);
        $this->assertStringContainsString('admin-nav-links', $html);
    }

    public function test_staff_hitting_an_owner_only_page_still_get_the_admin_403(): void
    {
        $staff = User::factory()->staffAdmin()->create();

        $this->actingAs($staff)
            ->get('/admin/settings/admins')
            ->assertForbidden()
            ->assertSee('This area is limited to owners');
    }
}
