<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The back-office reviews page: reading every review in one place, narrowing
 * by rating or by name, and deleting one for good.
 */
class AdminReviewTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->admin()->create();
    }

    private function reviewFor(Product $product, array $overrides = []): ProductReview
    {
        $customer = User::factory()->create();

        $order = Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => $customer->id,
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

        return ProductReview::query()->create([
            'product_id' => $product->id,
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'rating' => 5,
            'comment' => 'Excellent produit.',
            ...$overrides,
        ]);
    }

    public function test_the_page_lists_reviews_with_product_reviewer_and_comment(): void
    {
        $product = Product::factory()->create(['name' => ['fr' => 'Tente Ultra', 'en' => 'Ultra Tent']]);
        $review = $this->reviewFor($product, ['comment' => 'Solide sous la pluie.']);

        $this->actingAs($this->owner())
            ->get('/admin/reviews')
            ->assertOk()
            ->assertSee('Solide sous la pluie.')
            ->assertSee($review->user->name)
            ->assertSee($review->product->localizedName());
    }

    public function test_the_list_can_be_narrowed_to_one_rating(): void
    {
        $this->reviewFor(Product::factory()->create(), ['rating' => 5, 'comment' => 'Cinq etoiles.']);
        $this->reviewFor(Product::factory()->create(), ['rating' => 2, 'comment' => 'Deux etoiles.']);

        $this->actingAs($this->owner())
            ->get('/admin/reviews?rating=2')
            ->assertOk()
            ->assertSee('Deux etoiles.')
            ->assertDontSee('Cinq etoiles.');
    }

    public function test_the_list_can_be_searched_by_product_name(): void
    {
        $this->reviewFor(Product::factory()->create(['name' => ['fr' => 'Sac Bivouac', 'en' => 'Bivouac Bag']]), ['comment' => 'Pour le sac.']);
        $this->reviewFor(Product::factory()->create(['name' => ['fr' => 'Rechaud Compact', 'en' => 'Compact Stove']]), ['comment' => 'Pour le rechaud.']);

        $this->actingAs($this->owner())
            ->get('/admin/reviews?search=Bivouac')
            ->assertOk()
            ->assertSee('Pour le sac.')
            ->assertDontSee('Pour le rechaud.');
    }

    public function test_the_owner_can_delete_a_review_and_it_is_logged(): void
    {
        $review = $this->reviewFor(Product::factory()->create());

        $this->actingAs($this->owner())
            ->delete('/admin/reviews/'.$review->id)
            ->assertRedirect(route('admin.reviews.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('product_reviews', ['id' => $review->id]);
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'review.deleted']);
    }

    public function test_each_review_links_to_its_product_on_the_shop(): void
    {
        $product = Product::factory()->create();
        $this->reviewFor($product);

        // Every admin gets the link, not just the owner: reading the shop
        // isn't a destructive act.
        $this->actingAs(User::factory()->staffAdmin()->create())
            ->get('/admin/reviews')
            ->assertOk()
            ->assertSee(route('products.show', $product).'#product-reviews-title');
    }

    public function test_a_staff_admin_can_read_but_not_delete(): void
    {
        $review = $this->reviewFor(Product::factory()->create());
        $staff = User::factory()->staffAdmin()->create();

        $this->actingAs($staff)->get('/admin/reviews')->assertOk();

        $this->actingAs($staff)
            ->delete('/admin/reviews/'.$review->id)
            ->assertForbidden();

        $this->assertDatabaseHas('product_reviews', ['id' => $review->id]);
    }

    public function test_a_review_from_a_marketplace_can_be_added_by_hand(): void
    {
        $product = Product::factory()->create();

        // Staff, not owner: adding a review isn't gated like deleting one.
        $this->actingAs(User::factory()->staffAdmin()->create())
            ->post('/admin/reviews', [
                'product_id' => $product->id,
                'author_name' => 'Jean D.',
                'rating' => 4,
                'comment' => 'Vu sur Naturabuy, tres bien.',
                'source' => 'Naturabuy',
            ])
            ->assertRedirect(route('admin.reviews.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('product_reviews', [
            'product_id' => $product->id,
            'user_id' => null,
            'order_id' => null,
            'author_name' => 'Jean D.',
            'source' => 'Naturabuy',
            'rating' => 4,
        ]);
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'review.created']);
    }

    public function test_a_manual_review_can_carry_the_marketplace_date(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->owner())->post('/admin/reviews', [
            'product_id' => $product->id,
            'author_name' => 'Jean D.',
            'rating' => 5,
            'comment' => 'Parfait.',
            'posted_at' => '2026-03-15',
        ]);

        $this->assertSame(
            '2026-03-15',
            ProductReview::query()->sole()->created_at->toDateString(),
        );
    }

    public function test_a_manual_review_needs_a_valid_rating_and_a_comment(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->owner())
            ->post('/admin/reviews', [
                'product_id' => $product->id,
                'author_name' => 'Jean D.',
                'rating' => 6,
                'comment' => '',
            ])
            ->assertSessionHasErrors(['rating', 'comment']);

        $this->assertDatabaseCount('product_reviews', 0);
    }

    public function test_a_manual_review_shows_on_the_product_page_under_its_author_name(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->owner())->post('/admin/reviews', [
            'product_id' => $product->id,
            'author_name' => 'Jean D.',
            'rating' => 5,
            'comment' => 'Vu sur Naturabuy, parfait.',
        ]);

        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertSee('Jean D.')
            ->assertSee('Vu sur Naturabuy, parfait.');
    }

    public function test_a_customer_cannot_reach_the_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/reviews')
            ->assertRedirect();
    }
}
