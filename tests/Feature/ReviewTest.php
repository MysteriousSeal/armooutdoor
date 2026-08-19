<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private function shippedOrderFor(User $user, Product $product): Order
    {
        $order = Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => $user->id,
            'status' => 'shipped',
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => $product->price_cents,
            'shipping_cents' => 500,
            'discount_cents' => 0,
            'total_cents' => $product->price_cents + 500,
            'payment_method' => 'card',
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_slug' => $product->slug,
            'name' => $product->name,
            'image' => $product->image,
            'sku' => $product->sku,
            'unit_price_cents' => $product->price_cents,
            'quantity' => 1,
            'line_cents' => $product->price_cents,
        ]);

        return $order;
    }

    public function test_a_customer_who_bought_a_shipped_product_can_review_it(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $order = $this->shippedOrderFor($user, $product);

        $this->actingAs($user)
            ->post('/products/'.$product->slug.'/reviews', [
                'rating' => 5,
                'comment' => 'Excellent produit, je recommande.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('product_reviews', [
            'product_id' => $product->id,
            'user_id' => $user->id,
            'order_id' => $order->id,
            'rating' => 5,
        ]);
    }

    public function test_a_customer_who_never_bought_the_product_cannot_review_it(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)
            ->post('/products/'.$product->slug.'/reviews', ['rating' => 5])
            ->assertForbidden();

        $this->assertDatabaseCount('product_reviews', 0);
    }

    public function test_a_customer_whose_order_has_not_shipped_yet_cannot_review(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $order = $this->shippedOrderFor($user, $product);
        $order->update(['status' => 'placed']);

        $this->actingAs($user)
            ->post('/products/'.$product->slug.'/reviews', ['rating' => 5])
            ->assertForbidden();
    }

    public function test_a_customer_cannot_review_the_same_product_twice(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $this->shippedOrderFor($user, $product);

        $this->actingAs($user)->post('/products/'.$product->slug.'/reviews', ['rating' => 4]);

        $this->actingAs($user)
            ->post('/products/'.$product->slug.'/reviews', ['rating' => 2])
            ->assertForbidden();

        $this->assertSame(1, ProductReview::query()->where('product_id', $product->id)->count());
    }

    public function test_guests_cannot_submit_a_review(): void
    {
        $product = Product::factory()->create();

        $this->post('/products/'.$product->slug.'/reviews', ['rating' => 5])
            ->assertRedirect('/login');
    }

    public function test_rating_must_be_between_one_and_five(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $this->shippedOrderFor($user, $product);

        $this->actingAs($user)
            ->from('/products/'.$product->slug)
            ->post('/products/'.$product->slug.'/reviews', ['rating' => 6])
            ->assertSessionHasErrors('rating');

        $this->assertDatabaseCount('product_reviews', 0);
    }

    public function test_rating_is_required(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $this->shippedOrderFor($user, $product);

        $this->actingAs($user)
            ->from('/products/'.$product->slug)
            ->post('/products/'.$product->slug.'/reviews', [])
            ->assertSessionHasErrors('rating');
    }

    public function test_product_page_shows_the_average_rating_and_review_count(): void
    {
        $productOwner = User::factory()->create();
        $reviewer = User::factory()->create();
        $product = Product::factory()->create();
        $this->shippedOrderFor($reviewer, $product);

        ProductReview::query()->create([
            'product_id' => $product->id,
            'user_id' => $reviewer->id,
            'order_id' => Order::query()->where('user_id', $reviewer->id)->firstOrFail()->id,
            'rating' => 4,
        ]);

        $this->assertSame(4.0, $product->fresh()->averageRating());
        $this->assertSame(1, $product->fresh()->reviewsCount());

        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertSee('(1)', false);
    }
}
