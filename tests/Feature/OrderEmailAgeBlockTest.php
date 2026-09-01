<?php

namespace Tests\Feature;

use App\Models\IdentityDocument;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\AdminOrderPlaced;
use App\Notifications\OrderConfirmed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the two confirmation emails say about a proof of age.
 *
 * Both state the status as it was when sent, and say so: an email is read
 * days later, and a customer who has since sent a document should not be left
 * thinking the shop missed it.
 */
class OrderEmailAgeBlockTest extends TestCase
{
    use RefreshDatabase;

    private function order(User $customer, bool $restricted): Order
    {
        $product = Product::factory()->create(['is_active' => true, 'age_restricted' => $restricted, 'name' => ['fr' => 'Réplique Umarex']]);

        $order = Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => $customer->id,
            'status' => 'placed',
            'address_snapshot' => ['first_name' => 'Colas', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'Colas', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000, 'shipping_cents' => 0, 'discount_cents' => 0, 'total_cents' => 1000,
            'payment_method' => 'card',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_slug' => $product->slug,
            // The checkout stores the translated array, not a string: a fixture
            // that stores a string hides every bug in how the name is read.
            'name' => $product->name,
            'image' => '',
            'unit_price_cents' => 1000, 'quantity' => 1, 'line_cents' => 1000,
        ]);

        return $order->fresh('items');
    }

    private function proof(User $user, string $status, ?string $expires = null): void
    {
        IdentityDocument::query()->create([
            'user_id' => $user->id, 'kind' => 'passport', 'original_name' => 'p.pdf',
            'mime' => 'application/pdf', 'size_bytes' => 10, 'path' => null, 'status' => $status,
        ])->forceFill([
            'expires_at' => $expires,
            'reviewed_at' => $status === 'pending' ? null : now(),
        ])->save();
    }

    private function customerMail(Order $order): string
    {
        return (string) (new OrderConfirmed($order))->toMail($order->user)->render();
    }

    private function adminMail(Order $order): string
    {
        return (string) (new AdminOrderPlaced($order))->toMail($order->user)->render();
    }

    public function test_an_ordinary_order_says_nothing_in_either_email(): void
    {
        $order = $this->order(User::factory()->create(), false);

        $this->assertStringNotContainsString('réservé aux majeurs', $this->customerMail($order));
        $this->assertStringNotContainsString('Reserved to adults', $this->adminMail($order));
    }

    public function test_the_customer_is_told_a_proof_is_missing_and_where_to_send_it(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, true);

        $mail = $this->customerMail($order);

        // Asserted inside the block, not merely somewhere in the email: the
        // article is listed in the items table too, so a bare
        // assertStringContainsString passes even when the block names nothing.
        $this->assertStringContainsString('Article réservé aux majeurs', $mail);
        $this->assertMatchesRegularExpression(
            '/Article réservé aux majeurs[^(]*\(Réplique Umarex\)/u',
            strip_tags($mail),
        );
        $this->assertStringContainsString(route('account.documents.index'), $mail);
        $this->assertStringContainsString('à la date de cet e-mail', $mail);
    }

    public function test_a_verified_customer_is_not_asked_for_another(): void
    {
        // Every extra document is a passport on disk until somebody deletes it.
        $customer = User::factory()->create();
        $this->proof($customer, 'verified', now()->addYear()->toDateString());
        $order = $this->order($customer, true);

        $mail = $this->customerMail($order);

        $this->assertStringContainsString('vérifiée', $mail);
        $this->assertStringNotContainsString(route('account.documents.index'), $mail);
    }

    public function test_a_document_in_review_asks_for_nothing_either(): void
    {
        $customer = User::factory()->create();
        $this->proof($customer, 'pending');
        $order = $this->order($customer, true);

        $mail = $this->customerMail($order);

        $this->assertStringContainsString('en cours de vérification', $mail);
        $this->assertStringNotContainsString(route('account.documents.index'), $mail);
    }

    public function test_a_lapsed_proof_gives_the_date_and_asks_again(): void
    {
        $customer = User::factory()->create();
        $this->proof($customer, 'verified', now()->subDays(5)->toDateString());
        $order = $this->order($customer, true);

        $mail = $this->customerMail($order);

        $this->assertStringContainsString(now()->subDays(5)->translatedFormat('d F Y'), $mail);
        $this->assertStringContainsString(route('account.documents.index'), $mail);
    }

    public function test_the_admin_email_says_whether_it_may_be_dispatched(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, true);

        $mail = $this->adminMail($order);

        $this->assertStringContainsString('Reserved to adults', $mail);
        // The item name is a translated array on the order line, so it has to
        // go through localizedName(): plucked raw it renders as nothing.
        $this->assertMatchesRegularExpression(
            '/Reserved to adults[^\n]*Réplique Umarex/u',
            strip_tags($mail),
        );
        $this->assertStringContainsString('No proof of age on file', $mail);
        $this->assertStringContainsString('as at the time of sending', $mail);
    }

    public function test_the_admin_email_clears_a_verified_order(): void
    {
        $customer = User::factory()->create();
        $this->proof($customer, 'verified', now()->addYear()->toDateString());
        $order = $this->order($customer, true);

        $this->assertStringContainsString('may be dispatched', $this->adminMail($order));
    }
}
