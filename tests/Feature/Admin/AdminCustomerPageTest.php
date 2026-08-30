<?php

namespace Tests\Feature\Admin;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The customer page as a dossier: reviews, conversations and wishlist all
 * surface there, and the manual-order button arrives with the customer
 * already picked.
 */
class AdminCustomerPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_shows_reviews_conversations_and_wishlist(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create(['name' => ['fr' => 'Tente Dossier', 'en' => 'Dossier Tent']]);

        ProductReview::query()->create([
            'product_id' => $product->id,
            'user_id' => $customer->id,
            'rating' => 4,
            'comment' => 'Tres bonne tente.',
        ]);
        WishlistItem::query()->create(['user_id' => $customer->id, 'product_id' => $product->id]);
        Conversation::query()->create([
            'user_id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'subject' => 'Question sur ma commande',
        ])->postMessage('Bonjour, où en est ma commande ?', ConversationMessage::AUTHOR_CUSTOMER, $customer);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/customers/'.$customer->id)
            ->assertOk()
            ->assertSee('Tres bonne tente.')
            ->assertSee('Question sur ma commande')
            ->assertSee('Tente Dossier')
            ->assertSee('Total spent');
    }

    public function test_a_conversation_sent_before_signing_up_is_matched_by_email(): void
    {
        $customer = User::factory()->create();

        Conversation::query()->create([
            'user_id' => null,
            'name' => 'Guest',
            'email' => $customer->email,
            'subject' => 'Avant inscription',
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/customers/'.$customer->id)
            ->assertOk()
            ->assertSee('Avant inscription');
    }

    public function test_the_reset_link_button_can_ask_in_json(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $customer = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/admin/customers/'.$customer->id.'/send-reset-link')
            ->assertOk()
            ->assertJson(['message' => 'Password reset link sent.']);

        \Illuminate\Support\Facades\Notification::assertSentTo($customer, \Illuminate\Auth\Notifications\ResetPassword::class);
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'customer.password_reset_sent']);

        // Asking again inside the broker's minute comes back as a wait, not
        // a failure — and as a 422 the page shows in a toast.
        $this->actingAs($admin)
            ->postJson('/admin/customers/'.$customer->id.'/send-reset-link')
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'Wait before resending'));
    }

    public function test_the_manual_order_form_arrives_with_the_customer_preselected(): void
    {
        $customer = User::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/orders/create?user_id='.$customer->id)
            ->assertOk()
            ->assertSee('name="customer_id" id="customer_id" value="'.$customer->id.'"', false);
    }
}
