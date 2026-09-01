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
 * Whether an order may be dispatched, said above the items it governs.
 *
 * Unlike the customer pages, this one keeps saying it after dispatch: the
 * back office is the record, and « was this one verified before it went » is
 * a question asked afterwards.
 */
class AdminOrderAgeCheckTest extends TestCase
{
    use RefreshDatabase;

    private function order(User $customer, bool $restricted, string $status = 'placed'): Order
    {
        $product = Product::factory()->create(['is_active' => true, 'age_restricted' => $restricted]);

        $order = Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => $customer->id,
            'status' => $status,
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

    private function page(Order $order, string $role = 'admin'): TestResponse
    {
        return $this->actingAs(User::factory()->create(['role' => $role, 'is_admin' => true]))
            ->get('/admin/orders/'.$order->number)
            ->assertOk();
    }

    public function test_an_ordinary_order_shows_no_check(): void
    {
        $this->page($this->order(User::factory()->create(), false))
            ->assertDontSee('order-age-check', false);
    }

    public function test_an_unproved_order_warns_and_names_the_article(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, true);

        $this->page($order)
            ->assertSee('order-age-check--none', false)
            ->assertSee('No proof of age on file', false)
            ->assertSee($order->items->first()->name, false);
    }

    public function test_a_verified_order_says_it_may_go(): void
    {
        $customer = User::factory()->create();
        $this->proof($customer, 'verified', now()->addYear()->toDateString());

        $this->page($this->order($customer, true))
            ->assertSee('order-age-check--verified', false)
            ->assertSee('Age verified', false)
            ->assertSee('may be dispatched', false);
    }

    public function test_a_document_in_review_says_do_not_dispatch(): void
    {
        $customer = User::factory()->create();
        $this->proof($customer, 'pending');

        $this->page($this->order($customer, true))
            ->assertSee('order-age-check--pending', false)
            ->assertSee('Do not dispatch until it is', false);
    }

    public function test_a_lapsed_proof_gives_the_date_it_lapsed(): void
    {
        $customer = User::factory()->create();
        $this->proof($customer, 'verified', now()->subDays(4)->toDateString());

        $this->page($this->order($customer, true))
            ->assertSee('order-age-check--expired', false)
            ->assertSee(now()->subDays(4)->format('d/m/Y'), false);
    }

    public function test_it_survives_dispatch_as_the_record_of_what_was_checked(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, true, 'delivered');

        $this->page($order)->assertSee('order-age-check', false);
    }

    public function test_only_an_owner_is_given_the_way_through(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, true);

        // The screen behind it answers 403 to anybody else.
        $this->page($order, 'admin')->assertDontSee(route('admin.documents.index'), false);
        $this->page($order, 'owner')->assertSee(route('admin.documents.index'), false);
    }

    public function test_a_verified_order_needs_no_way_through(): void
    {
        $customer = User::factory()->create();
        $this->proof($customer, 'verified', now()->addYear()->toDateString());

        $this->page($this->order($customer, true), 'owner')
            ->assertDontSee('order-age-check-cta', false);
    }

    public function test_the_prepare_button_is_held_until_a_proof_is_valid(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, true);

        $this->page($order, 'owner')
            ->assertSee('disabled title="A proof of age is needed', false)
            ->assertDontSee('data-modal-open="prepare-confirm-modal"', false);
    }

    public function test_a_verified_order_can_be_prepared_as_usual(): void
    {
        $customer = User::factory()->create();
        $this->proof($customer, 'verified', now()->addYear()->toDateString());

        $this->page($this->order($customer, true), 'owner')
            ->assertSee('data-modal-open="prepare-confirm-modal"', false);
    }

    public function test_an_ordinary_order_is_never_held(): void
    {
        $this->page($this->order(User::factory()->create(), false), 'owner')
            ->assertSee('data-modal-open="prepare-confirm-modal"', false);
    }

    public function test_the_route_refuses_it_too(): void
    {
        // A control that lives only in the markup is one form submission away
        // from not existing.
        $customer = User::factory()->create();
        $order = $this->order($customer, true);

        $this->actingAs(User::factory()->create(['role' => 'owner', 'is_admin' => true]))
            ->patch('/admin/orders/'.$order->number.'/prepare')
            ->assertSessionHasErrors('status');

        $this->assertSame('placed', $order->fresh()->status);
    }

    public function test_the_route_lets_a_verified_order_through(): void
    {
        $customer = User::factory()->create();
        $this->proof($customer, 'verified', now()->addYear()->toDateString());
        $order = $this->order($customer, true);

        $this->actingAs(User::factory()->create(['role' => 'owner', 'is_admin' => true]))
            ->patch('/admin/orders/'.$order->number.'/prepare')
            ->assertSessionHasNoErrors();

        $this->assertSame('preparing', $order->fresh()->status);
    }
}
