<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WishlistItem;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * A product taken off sale keeps its page, shown as unavailable.
 *
 * The address stays up for search engines and shared links, but nothing on
 * it can be bought or saved, and it stays out of every list that invites a
 * purchase.
 */
class ProductUnavailablePageTest extends TestCase
{
    use RefreshDatabase;

    private function inactiveProduct(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge(['is_active' => false, 'quantity' => 0], $attributes));
    }

    private function named(string $name, array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'slug' => str($name)->slug()->toString(),
            'name' => ['en' => $name, 'fr' => $name],
        ], $attributes));
    }

    /**
     * The Product node of the page's JSON-LD.
     *
     * @return array<string, mixed>
     */
    private function productSchema(TestResponse $response): array
    {
        preg_match_all('#<script type="application/ld\+json">\s*(.*?)\s*</script>#s', $response->getContent(), $blocks);

        foreach ($blocks[1] as $json) {
            $node = json_decode($json, true);

            if (($node['@type'] ?? null) === 'Product') {
                return $node;
            }
        }

        $this->fail('The page carries no Product JSON-LD.');
    }

    /** The unavailable notice alone, so the header's own links do not count. */
    private function notice(TestResponse $response): string
    {
        $this->assertMatchesRegularExpression('#<div class="product-unavailable-notice"[^>]*>.*?</div>#s', $response->getContent());
        preg_match('#<div class="product-unavailable-notice"[^>]*>.*?</div>#s', $response->getContent(), $notice);

        return $notice[0];
    }

    private function deliveredOrder(User $user, Product ...$products): Order
    {
        $address = ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'];

        $order = Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => $user->id,
            'status' => 'delivered',
            'address_snapshot' => $address,
            'billing_address_snapshot' => $address,
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000, 'shipping_cents' => 0, 'discount_cents' => 0,
            'total_cents' => 1000, 'payment_method' => 'card',
        ]);

        foreach ($products as $product) {
            OrderItem::query()->create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_slug' => $product->slug,
                'name' => $product->name,
                'image' => $product->image,
                'quantity' => 1,
                'unit_price_cents' => 1000,
                'line_cents' => 1000,
            ]);
        }

        return $order;
    }

    // --- The page ----------------------------------------------------------

    public function test_the_page_answers_and_says_the_product_is_unavailable(): void
    {
        $product = $this->inactiveProduct();

        $response = $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertSee($product->localizedName())
            ->assertSee(__('store.product_unavailable_title'))
            ->assertSee(__('store.product_unavailable_text'))
            ->assertSee('class="product-unavailable-notice"', false);

        $this->assertMatchesRegularExpression(
            '#<span class="stock-badge is-out-of-stock" id="product-stock-badge">\s*'.preg_quote(__('store.product_unavailable_badge'), '#').'\s*</span>#',
            $response->getContent()
        );
    }

    public function test_the_page_offers_no_way_to_buy_or_save_the_product(): void
    {
        $product = $this->inactiveProduct(['quantity' => 5]);

        $this->actingAs(User::factory()->create())
            ->get('/products/'.$product->slug)
            ->assertOk()
            ->assertDontSee('add-to-cart-form', false)
            ->assertDontSee('product-detail-wishlist-form', false)
            ->assertDontSee('name="product_id"', false);
    }

    public function test_search_engines_may_still_index_the_page(): void
    {
        $product = $this->inactiveProduct(['price_cents' => 2490]);

        $response = $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertHeaderMissing('X-Robots-Tag')
            ->assertDontSee('<meta name="robots"', false);

        preg_match('#<link rel="canonical" href="([^"]+)"#', $response->getContent(), $canonical);
        $this->assertSame(url('/products/'.$product->slug), $canonical[1] ?? null);

        $offers = $this->productSchema($response)['offers'];
        $this->assertSame('https://schema.org/OutOfStock', $offers['availability']);
        $this->assertSame('24.90', $offers['price']);
    }

    public function test_a_variant_product_shows_no_variant_picker_and_stays_out_of_stock(): void
    {
        $product = $this->inactiveProduct();

        foreach ([['M', 1500], ['L', 1900]] as [$size, $price]) {
            ProductVariant::query()->create([
                'product_id' => $product->id,
                'attribute_values' => [['label' => 'Taille', 'value' => $size]],
                'price_cents' => $price,
                'quantity' => 5,
                'is_active' => true,
            ]);
        }

        $response = $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertSee(__('store.product_unavailable_title'))
            ->assertDontSee('class="product-variants"', false)
            ->assertDontSee('name="variant_id"', false);

        $offers = $this->productSchema($response)['offers'];
        $this->assertSame('AggregateOffer', $offers['@type']);
        $this->assertSame('https://schema.org/OutOfStock', $offers['availability']);
        $this->assertSame('15.00', $offers['lowPrice']);
        $this->assertSame('19.00', $offers['highPrice']);
    }

    public function test_no_supplier_lead_time_is_promised(): void
    {
        $supplier = Supplier::query()->create(['name' => 'Fournisseur', 'lead_time_days' => 5]);
        $atSupplier = ['quantity' => 0, 'supplier_id' => $supplier->id, 'available_at_supplier' => true];

        $inactive = $this->get('/products/'.$this->inactiveProduct($atSupplier)->slug)->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/id="product-supplier-notice"\s+hidden/', $inactive);
        $this->assertMatchesRegularExpression('/id="product-lead-time"\s+hidden/', $inactive);

        // The same product on sale does promise it, so the check above means something.
        $active = $this->get('/products/'.Product::factory()->create($atSupplier)->slug)->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/id="product-lead-time"\s+hidden/', $active);
    }

    public function test_an_active_discount_is_neither_counted_down_nor_advertised(): void
    {
        $discounted = function (Product $product): Product {
            Discount::query()->create([
                'product_id' => $product->id,
                'type' => 'percentage',
                'value' => 20,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addWeek(),
            ]);

            return $product;
        };

        $response = $this->get('/products/'.$discounted($this->inactiveProduct(['price_cents' => 2000]))->slug)->assertOk();
        $html = $response->getContent();

        $this->assertMatchesRegularExpression('/id="discount-countdown"\s+data-ends-at=""/', $html);
        $this->assertMatchesRegularExpression('/data-label-seconds="[^"]*"\s+hidden/', $html);
        $this->assertMatchesRegularExpression('/id="product-detail-discount-badge"\s+hidden/', $html);
        $this->assertMatchesRegularExpression('/id="product-detail-price-original"\s+hidden/', $html);
        $this->assertSame('https://schema.org/OutOfStock', $this->productSchema($response)['offers']['availability']);

        // On sale, the same discount is counted down and advertised.
        $active = $this->get('/products/'.$discounted(Product::factory()->create(['price_cents' => 2000]))->slug)->getContent();
        $this->assertDoesNotMatchRegularExpression('/id="discount-countdown"\s+data-ends-at=""/', $active);
        $this->assertDoesNotMatchRegularExpression('/id="product-detail-discount-badge"\s+hidden/', $active);
    }

    public function test_an_age_restricted_product_does_not_mention_ordering(): void
    {
        $this->get('/products/'.$this->inactiveProduct(['age_restricted' => true])->slug)
            ->assertOk()
            ->assertSee(__('store.age_restricted_notice'))
            ->assertDontSee(__('store.age_restricted_proof_notice'));

        $this->get('/products/'.Product::factory()->create(['age_restricted' => true])->slug)
            ->assertSee(__('store.age_restricted_proof_notice'));
    }

    public function test_reviews_still_show_and_stay_in_the_structured_data(): void
    {
        $product = $this->inactiveProduct();
        $user = User::factory()->create();
        ProductReview::query()->create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'order_id' => $this->deliveredOrder($user, $product)->id,
            'rating' => 4,
            'comment' => 'Solide et bien fini.',
        ]);

        $response = $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertSee('Solide et bien fini.');

        $schema = $this->productSchema($response);
        $this->assertSame(1, $schema['aggregateRating']['reviewCount']);
        $this->assertNotEmpty($schema['review']);
    }

    public function test_a_buyer_who_received_it_can_still_review_it(): void
    {
        // Intended: a review of a product the customer actually received
        // stays legitimate after the product is taken off sale.
        $product = $this->inactiveProduct();
        $buyer = User::factory()->create();
        $this->deliveredOrder($buyer, $product);

        $this->actingAs($buyer)
            ->get('/products/'.$product->slug)
            ->assertOk()
            ->assertSee('class="review-form', false);

        $this->actingAs($buyer)
            ->from('/products/'.$product->slug)
            ->post('/products/'.$product->slug.'/reviews', ['rating' => 5, 'comment' => 'Toujours content.'])
            ->assertRedirect('/products/'.$product->slug)
            ->assertSessionHas('status');

        $this->assertDatabaseHas('product_reviews', ['product_id' => $product->id, 'user_id' => $buyer->id, 'rating' => 5]);
    }

    public function test_a_product_without_a_picture_still_renders(): void
    {
        $response = $this->get('/products/'.$this->inactiveProduct(['image' => ''])->slug)
            ->assertOk()
            ->assertSee(__('store.product_unavailable_title'));

        // No picture, so none is announced to search engines.
        $this->assertArrayNotHasKey('image', $this->productSchema($response));
    }

    // --- Where the notice leads ---------------------------------------------

    public function test_the_notice_leads_to_a_category_that_still_sells_something(): void
    {
        $category = Category::factory()->create();
        $product = $this->inactiveProduct(['category_id' => $category->id]);
        $sibling = $this->named('Gourde Alpha', ['category_id' => $category->id]);
        $retiredSibling = $this->named('Gourde Bravo', ['category_id' => $category->id, 'is_active' => false]);

        $response = $this->get('/products/'.$product->slug)
            ->assertOk()
            // The related strip lists what can still be bought, and only that.
            ->assertSee($sibling->localizedName())
            ->assertDontSee($retiredSibling->localizedName());

        $notice = $this->notice($response);
        $this->assertStringContainsString('href="'.url('/categories/'.$category->slug).'"', $notice);
        $this->assertStringContainsString(e(__('store.product_unavailable_category', ['category' => $category->localizedName()])), $notice);
        $this->assertStringNotContainsString(e(__('store.product_unavailable_all')), $notice);
    }

    public function test_the_notice_skips_a_category_with_nothing_left_on_sale(): void
    {
        $category = Category::factory()->create();
        $product = $this->inactiveProduct(['category_id' => $category->id]);
        $this->inactiveProduct(['category_id' => $category->id]);

        $notice = $this->notice($this->get('/products/'.$product->slug)->assertOk());

        $this->assertStringNotContainsString('/categories/', $notice);
        $this->assertStringContainsString('href="'.url('/produits').'">'.e(__('store.product_unavailable_all')).'</a>', $notice);
    }

    // --- Nothing can be bought or saved -------------------------------------

    public function test_the_cart_refuses_an_inactive_product(): void
    {
        $product = $this->inactiveProduct(['quantity' => 5]);

        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 1])->assertNotFound();
        // The cart modal posts with fetch and asks for JSON.
        $this->postJson('/cart', ['product_id' => $product->id, 'quantity' => 1])->assertNotFound();
        $this->assertSame(0, app(Cart::class)->quantity());

        $user = User::factory()->create();
        $this->actingAs($user)->post('/cart', ['product_id' => $product->id, 'quantity' => 1])->assertNotFound();
        $this->assertDatabaseMissing('cart_items', ['user_id' => $user->id]);
    }

    public function test_an_inactive_product_cannot_be_added_to_a_wishlist(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_active' => false]);

        // Refused as the cart refuses it.
        $this->actingAs($user)
            ->from('/products/'.$product->slug)
            ->post('/wishlist', ['product_id' => $product->id])
            ->assertNotFound()
            ->assertSessionMissing('status');
        $this->actingAs($user)->postJson('/wishlist', ['product_id' => $product->id])->assertNotFound();

        $this->assertDatabaseMissing('wishlist_items', ['user_id' => $user->id, 'product_id' => $product->id]);
        $this->assertDatabaseCount('wishlist_items', 0);
    }

    public function test_an_inactive_product_already_in_a_wishlist_can_still_be_removed(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        WishlistItem::query()->create(['user_id' => $user->id, 'product_id' => $product->id]);
        $product->update(['is_active' => false]);

        $this->actingAs($user)
            ->get('/account/wishlist')
            ->assertOk()
            ->assertDontSee($product->localizedName());

        $this->actingAs($user)
            ->from('/account/wishlist')
            ->delete('/wishlist/'.$product->slug)
            ->assertRedirect('/account/wishlist')
            ->assertSessionHas('status');

        $this->assertDatabaseCount('wishlist_items', 0);
    }

    // --- Addresses ----------------------------------------------------------

    public function test_old_addresses_follow_the_product_and_unknown_ones_do_not(): void
    {
        $product = Product::factory()->create(['slug' => 'ancien']);
        $product->update(['slug' => 'nouveau']);
        $product->update(['is_active' => false]);

        $this->get('/products/ancien')->assertStatus(301)->assertRedirect('/products/nouveau');
        $this->get('/products/nouveau')->assertOk()->assertSee(__('store.product_unavailable_title'));
        $this->get('/products/jamais-vu')->assertNotFound();

        $gone = Product::factory()->create(['slug' => 'disparu']);
        $gone->update(['slug' => 'disparu-2']);
        $gone->delete();
        $this->get('/products/disparu')->assertNotFound();
    }

    // --- Lists that invite a purchase ----------------------------------------

    public function test_sitemaps_and_the_merchant_feed_leave_it_out(): void
    {
        $active = $this->named('Gourde Alpha', ['quantity' => 5]);
        $inactive = $this->named('Gourde Bravo', ['quantity' => 5, 'is_active' => false]);

        foreach (['/sitemap-products.xml', '/plan-du-site', '/feed/google.xml'] as $url) {
            $body = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('/products/'.$active->slug, $body, $url);
            $this->assertStringNotContainsString('/products/'.$inactive->slug, $body, $url);
        }
    }

    public function test_search_and_the_shop_listings_leave_it_out(): void
    {
        $active = $this->named('Gourde Alpha', ['quantity' => 5]);
        $inactive = $this->named('Gourde Bravo', ['quantity' => 5, 'is_active' => false]);

        foreach ([$active, $inactive] as $product) {
            Discount::query()->create(['product_id' => $product->id, 'type' => 'percentage', 'value' => 10]);
        }
        $this->deliveredOrder(User::factory()->create(), $active, $inactive);

        foreach (['/search?q=Gourde', '/produits', '/promotions', '/nouveautes', '/meilleures-ventes'] as $url) {
            $body = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('Gourde Alpha', $body, $url);
            $this->assertStringNotContainsString('Gourde Bravo', $body, $url);
        }
    }

    // --- A product on sale is unchanged ----------------------------------------

    public function test_an_active_product_page_is_unchanged(): void
    {
        $product = Product::factory()->create(['quantity' => 20]);

        $response = $this->actingAs(User::factory()->create())
            ->get('/products/'.$product->slug)
            ->assertOk()
            ->assertDontSee(__('store.product_unavailable_title'))
            ->assertDontSee(__('store.product_unavailable_badge'))
            ->assertSee('add-to-cart-form', false)
            ->assertSee('product-detail-wishlist-form', false)
            ->assertSee(__('store.in_stock'));

        $this->assertSame('https://schema.org/InStock', $this->productSchema($response)['offers']['availability']);
    }
}
