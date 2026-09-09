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

    private function manualReview(Product $product, array $overrides = []): ProductReview
    {
        return ProductReview::query()->create([
            'product_id' => $product->id,
            'author_name' => 'Jean D.',
            'source' => 'Naturabuy',
            'rating' => 4,
            'comment' => 'Vu sur Naturabuy, tres bien.',
            ...$overrides,
        ]);
    }

    public function test_a_manual_review_can_be_corrected(): void
    {
        $product = Product::factory()->create();
        $elsewhere = Product::factory()->create();
        $review = $this->manualReview($product);

        // Staff, not owner: correcting a review the shop typed in itself is
        // the other half of typing it in, and that is not gated either.
        $this->actingAs(User::factory()->staffAdmin()->create())
            ->patch('/admin/reviews/'.$review->id, [
                'product_id' => $elsewhere->id,
                'author_name' => 'Jeanne D.',
                'rating' => 5,
                'comment' => 'Corrige : tres bien.',
                'source' => 'Amazon',
                'posted_at' => '2026-02-01',
            ])
            ->assertRedirect(route('admin.reviews.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('product_reviews', [
            'id' => $review->id,
            'product_id' => $elsewhere->id,
            'author_name' => 'Jeanne D.',
            'source' => 'Amazon',
            'rating' => 5,
            'comment' => 'Corrige : tres bien.',
        ]);
        $this->assertSame('2026-02-01', $review->fresh()->created_at->toDateString());
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'review.updated']);
    }

    public function test_editing_a_manual_review_without_a_date_leaves_it_where_it_sorts(): void
    {
        $product = Product::factory()->create();
        $review = $this->manualReview($product);
        $review->created_at = '2024-05-06';
        $review->save();

        $this->actingAs($this->owner())->patch('/admin/reviews/'.$review->id, [
            'product_id' => $product->id,
            'author_name' => 'Jean D.',
            'rating' => 4,
            'comment' => 'Une faute de frappe corrigee.',
        ]);

        // A blank date must not read as « today »: fixing a typo would move a
        // two-year-old review to the top of the product page.
        $this->assertSame('2024-05-06', $review->fresh()->created_at->toDateString());
        $this->assertNull($review->fresh()->source);
    }

    public function test_an_edit_is_refused_the_same_way_the_form_is(): void
    {
        $product = Product::factory()->create();
        $review = $this->manualReview($product);

        $this->actingAs($this->owner())
            ->patch('/admin/reviews/'.$review->id, [
                'product_id' => $product->id,
                'author_name' => 'Jean D.',
                'rating' => 9,
                'comment' => '',
            ])
            ->assertSessionHasErrors(['rating', 'comment']);

        $this->assertSame('Vu sur Naturabuy, tres bien.', $review->fresh()->comment);
    }

    public function test_a_customers_own_review_cannot_be_edited(): void
    {
        $product = Product::factory()->create();
        $review = $this->reviewFor($product, ['comment' => 'Solide sous la pluie.']);

        // Their words, under their name. The back office may take a review
        // down; it may not rewrite one.
        $this->actingAs($this->owner())
            ->patch('/admin/reviews/'.$review->id, [
                'product_id' => $product->id,
                'author_name' => 'Quelqu\'un d\'autre',
                'rating' => 1,
                'comment' => 'Des mots que le client n\'a pas ecrits.',
            ])
            ->assertNotFound();

        $this->assertSame('Solide sous la pluie.', $review->fresh()->comment);
        $this->assertSame(5, $review->fresh()->rating);
    }

    public function test_only_a_manual_review_offers_an_edit_form(): void
    {
        $product = Product::factory()->create();
        $customerReview = $this->reviewFor($product);
        $manual = $this->manualReview($product);

        $html = $this->actingAs($this->owner())->get('/admin/reviews')->assertOk()->getContent();

        $this->assertStringContainsString('id="review-edit-'.$manual->id.'"', $html);
        $this->assertStringNotContainsString('id="review-edit-'.$customerReview->id.'"', $html);
        $this->assertStringContainsString(route('admin.reviews.update', $manual), $html);
    }

    public function test_the_page_counts_reviews_by_channel(): void
    {
        $product = Product::factory()->create();

        $this->reviewFor($product);
        $this->reviewFor($product);
        $this->manualReview($product, ['source' => 'Naturabuy']);
        $this->manualReview($product, ['source' => 'Naturabuy']);
        $this->manualReview($product, ['source' => 'Naturabuy']);
        $this->manualReview($product, ['source' => 'Amazon']);
        $this->manualReview($product, ['source' => null]);

        $channels = $this->actingAs($this->owner())
            ->get('/admin/reviews')
            ->assertOk()
            ->viewData('channels');

        // Ordered by weight, so the channel carrying the shop's reputation
        // is the one at the top rather than the one typed in first.
        $this->assertSame(
            [
                ['key' => 'source:Naturabuy', 'label' => 'Naturabuy', 'total' => 3, 'direct' => false],
                ['key' => 'direct', 'label' => 'Direct', 'total' => 2, 'direct' => true],
                ['key' => 'source:Amazon', 'label' => 'Amazon', 'total' => 1, 'direct' => false],
                ['key' => 'unattributed', 'label' => 'Unattributed', 'total' => 1, 'direct' => false],
            ],
            $channels->values()->all(),
        );
    }

    public function test_the_average_is_shown_to_two_decimals(): void
    {
        $product = Product::factory()->create();

        // 4, 4, 5 averages 4.333…, which one decimal rounds to a figure that
        // sits still for weeks. Two say which way it is drifting.
        $this->manualReview($product, ['rating' => 4]);
        $this->manualReview($product, ['rating' => 4]);
        $this->manualReview($product, ['rating' => 5]);

        $response = $this->actingAs($this->owner())->get('/admin/reviews')->assertOk();

        $this->assertSame(4.33, $response->viewData('average'));
        $response->assertSee('4.33');
    }

    public function test_a_channel_with_nothing_in_it_is_not_listed(): void
    {
        $product = Product::factory()->create();
        $this->manualReview($product, ['source' => 'Naturabuy']);

        // No customer has posted here yet, so « Direct : 0 » would be a line
        // of noise on a page whose whole job is the shape of the reviews.
        $channels = $this->actingAs($this->owner())
            ->get('/admin/reviews')
            ->assertOk()
            ->viewData('channels');

        $this->assertSame(['Naturabuy'], $channels->pluck('label')->all());
    }

    public function test_the_list_can_be_narrowed_to_one_channel(): void
    {
        $product = Product::factory()->create(['name' => ['fr' => 'Tente Ultra', 'en' => 'Ultra Tent']]);

        $this->reviewFor($product, ['comment' => 'Poste sur la boutique.']);
        $this->manualReview($product, ['source' => 'Naturabuy', 'comment' => 'Vu sur Naturabuy.']);
        $this->manualReview($product, ['source' => 'Amazon', 'comment' => 'Vu sur Amazon.']);
        $this->manualReview($product, ['source' => null, 'comment' => 'Sans marketplace.']);

        $owner = $this->owner();

        $this->actingAs($owner)->get('/admin/reviews?channel='.urlencode('source:Naturabuy'))
            ->assertOk()
            ->assertSee('Vu sur Naturabuy.')
            ->assertDontSee('Vu sur Amazon.')
            ->assertDontSee('Poste sur la boutique.')
            ->assertDontSee('Sans marketplace.');

        $this->actingAs($owner)->get('/admin/reviews?channel=direct')
            ->assertOk()
            ->assertSee('Poste sur la boutique.')
            ->assertDontSee('Vu sur Naturabuy.');

        // The unnamed channel is a channel too, and « no source » is not the
        // same query as « any source ».
        $this->actingAs($owner)->get('/admin/reviews?channel=unattributed')
            ->assertOk()
            ->assertSee('Sans marketplace.')
            ->assertDontSee('Vu sur Amazon.');
    }

    public function test_a_marketplace_named_like_a_reserved_channel_still_filters_to_itself(): void
    {
        $product = Product::factory()->create();

        $this->reviewFor($product, ['comment' => 'Poste sur la boutique.']);
        $this->manualReview($product, ['source' => 'direct', 'comment' => 'Une place de marche appelee direct.']);

        // The source keys are prefixed for exactly this: unprefixed, a
        // marketplace called « direct » would answer for the shop's own.
        $this->actingAs($this->owner())->get('/admin/reviews?channel='.urlencode('source:direct'))
            ->assertOk()
            ->assertSee('Une place de marche appelee direct.')
            ->assertDontSee('Poste sur la boutique.');
    }

    public function test_the_channel_filter_composes_with_the_rating_and_the_search(): void
    {
        $product = Product::factory()->create();

        $this->manualReview($product, ['source' => 'Naturabuy', 'rating' => 5, 'comment' => 'Cinq etoiles Naturabuy.']);
        $this->manualReview($product, ['source' => 'Naturabuy', 'rating' => 2, 'comment' => 'Deux etoiles Naturabuy.']);
        $this->manualReview($product, ['source' => 'Amazon', 'rating' => 5, 'comment' => 'Cinq etoiles Amazon.']);

        $this->actingAs($this->owner())
            ->get('/admin/reviews?channel='.urlencode('source:Naturabuy').'&rating=5')
            ->assertOk()
            ->assertSee('Cinq etoiles Naturabuy.')
            ->assertDontSee('Deux etoiles Naturabuy.')
            ->assertDontSee('Cinq etoiles Amazon.');
    }

    public function test_a_channel_that_no_longer_exists_shows_everything(): void
    {
        $product = Product::factory()->create();
        $this->manualReview($product, ['source' => 'Naturabuy', 'comment' => 'Vu sur Naturabuy.']);

        // A bookmarked filter on a marketplace the shop has left must not
        // strand the page on an empty list with no way back.
        $response = $this->actingAs($this->owner())
            ->get('/admin/reviews?channel='.urlencode('source:Ebay'))
            ->assertOk()
            ->assertSee('Vu sur Naturabuy.');

        $this->assertSame('', $response->viewData('channel'));
    }

    public function test_the_tiles_describe_the_shop_whatever_the_filter_is(): void
    {
        $product = Product::factory()->create();

        $this->reviewFor($product);
        $this->manualReview($product, ['source' => 'Naturabuy']);

        // The list below answers the filter; the tiles above answer « what
        // does the shop look like », and a filter must not rewrite them.
        $response = $this->actingAs($this->owner())
            ->get('/admin/reviews?channel=direct')
            ->assertOk();

        $this->assertSame(2, $response->viewData('total'));
        $this->assertSame(['Direct', 'Naturabuy'], $response->viewData('channels')->pluck('label')->all());
    }

    public function test_a_manual_review_is_found_by_its_author_and_its_marketplace(): void
    {
        $product = Product::factory()->create(['name' => ['fr' => 'Tente Ultra', 'en' => 'Ultra Tent']]);

        $this->manualReview($product, ['author_name' => 'Ghislaine P.', 'source' => 'NaturaBuy', 'comment' => 'Vu sur NaturaBuy.']);
        $this->reviewFor($product, ['comment' => 'Poste sur la boutique.']);

        $owner = $this->owner();

        // The reviews the shop types in itself carry no customer row, so a
        // search that only joined users could not reach the one kind of
        // review the shop is most likely to go looking for.
        $this->actingAs($owner)->get('/admin/reviews?search=Ghislaine')
            ->assertOk()
            ->assertSee('Vu sur NaturaBuy.')
            ->assertDontSee('Poste sur la boutique.');

        $this->actingAs($owner)->get('/admin/reviews?search=NaturaBuy')
            ->assertOk()
            ->assertSee('Vu sur NaturaBuy.')
            ->assertDontSee('Poste sur la boutique.');
    }

    public function test_the_page_says_how_much_of_the_catalogue_has_been_reviewed(): void
    {
        $reviewed = Product::factory()->create();
        $alsoReviewed = Product::factory()->create();
        Product::factory()->count(3)->create();

        // Two reviews on one product still count that product once: the
        // question is how many products have anything said about them.
        $this->manualReview($reviewed);
        $this->manualReview($reviewed);
        $this->manualReview($alsoReviewed);

        $response = $this->actingAs($this->owner())->get('/admin/reviews')->assertOk();

        $this->assertSame(2, $response->viewData('reviewedProducts'));
        $this->assertSame(5, $response->viewData('productCount'));
        $response->assertSee('Products reviewed');
        $response->assertSee('Coverage');
    }

    public function test_a_customer_cannot_reach_the_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/reviews')
            ->assertRedirect();
    }
}
