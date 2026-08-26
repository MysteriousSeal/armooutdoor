<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ce que la fiche produit dit de ses avis.
 *
 * La boutique n'accepte un avis que d'un client dont la commande est partie.
 * La page appliquait la règle sans jamais la dire, et les étoiles du haut ne
 * menaient pas aux avis du bas.
 */
class ProductReviewTrustTest extends TestCase
{
    use RefreshDatabase;

    private function reviewedProduct(int $rating = 4): Product
    {
        $product = Product::factory()->create(['is_active' => true, 'quantity' => 5]);
        $user = User::factory()->create();

        $order = Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => $user->id,
            'status' => 'delivered',
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000, 'shipping_cents' => 0, 'discount_cents' => 0,
            'total_cents' => 1000, 'payment_method' => 'card',
        ]);

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

        ProductReview::query()->create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'order_id' => $order->id,
            'rating' => $rating,
            'comment' => 'Bonne visibilité des impacts.',
        ]);

        return $product;
    }

    /** Un client dont la commande est partie : le formulaire s'affiche pour lui. */
    private function eligibleBuyer(Product $product): User
    {
        $user = User::factory()->create();

        $order = Order::query()->create([
            'number' => Order::generateNumber(),
            'user_id' => $user->id,
            'status' => 'delivered',
            'address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'billing_address_snapshot' => ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR'],
            'carrier_method' => 'home',
            'carrier_snapshot' => ['name' => ['fr' => 'Colissimo']],
            'subtotal_cents' => 1000, 'shipping_cents' => 0, 'discount_cents' => 0,
            'total_cents' => 1000, 'payment_method' => 'card',
        ]);

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

        return $user;
    }

    private function page(Product $product): string
    {
        return $this->get('/products/'.$product->slug)->assertOk()->getContent();
    }

    public function test_the_stars_at_the_top_lead_to_the_reviews(): void
    {
        $html = $this->page($this->reviewedProduct());

        $this->assertMatchesRegularExpression(
            '#<a class="product-detail-rating" href="\#product-reviews-title"#',
            $html
        );
        $this->assertStringContainsString('id="product-reviews-title"', $html);
    }

    public function test_the_rating_is_spoken_and_not_only_drawn(): void
    {
        $html = $this->page($this->reviewedProduct(4));

        // Les étoiles sont un dessin : sans texte, un lecteur d'écran
        // n'annonçait que « (1) ».
        $this->assertStringContainsString('4,0 sur 5, 1 avis', $html);
    }

    public function test_a_product_without_reviews_still_says_so(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'quantity' => 5]);

        $this->assertStringContainsString('Aucun avis pour ce produit', $this->page($product));
    }

    public function test_the_page_says_who_may_leave_a_review(): void
    {
        $html = $this->page($this->reviewedProduct());

        $this->assertStringContainsString('reviews-gate', $html);
        $this->assertStringContainsString('Seuls les clients ayant reçu ce produit', $html);
    }

    public function test_a_review_tied_to_an_order_is_marked_verified(): void
    {
        $html = $this->page($this->reviewedProduct());

        $this->assertStringContainsString('review-verified', $html);
        $this->assertStringContainsString('Achat vérifié', $html);
    }

    public function test_the_form_offers_its_stars_from_one_to_five(): void
    {
        $product = $this->reviewedProduct();
        $buyer = $this->eligibleBuyer($product);

        $html = $this->actingAs($buyer)->get('/products/'.$product->slug)->assertOk()->getContent();

        preg_match_all('/id="rating-(\d)"/', $html, $matches);

        // Écrites de 5 à 1 et retournées en CSS, les flèches du clavier
        // parcouraient les notes à l'envers.
        $this->assertSame(['1', '2', '3', '4', '5'], $matches[1]);
    }

    public function test_each_star_says_what_it_stands_for(): void
    {
        $product = $this->reviewedProduct();
        $buyer = $this->eligibleBuyer($product);

        $html = $this->actingAs($buyer)->get('/products/'.$product->slug)->assertOk()->getContent();

        // Sans cela, chaque bouton s'annonçait « ★ ».
        $this->assertStringContainsString('1 étoile sur 5', $html);
        $this->assertStringContainsString('5 étoiles sur 5', $html);
    }

    public function test_the_stars_of_a_review_are_spoken(): void
    {
        $html = $this->page($this->reviewedProduct(4));

        $this->assertStringContainsString('4 étoiles sur 5', $html);
    }

    public function test_the_distribution_shows_a_row_per_rating(): void
    {
        $html = $this->page($this->reviewedProduct(4));

        $this->assertStringContainsString('reviews-distribution', $html);
        // Cinq lignes, y compris les notes que personne n'a données : une
        // barre absente se confondrait avec une barre courte.
        $this->assertSame(5, substr_count($html, 'reviews-distribution-bar'));
        $this->assertStringContainsString('1 avis pour 4 étoiles', $html);
        $this->assertStringContainsString('0 avis pour 1 étoiles', $html);
    }

    public function test_the_bars_are_sized_by_share(): void
    {
        $product = $this->reviewedProduct(4);

        $this->assertSame([5 => 0, 4 => 1, 3 => 0, 2 => 0, 1 => 0], $product->ratingDistribution());
        $this->assertStringContainsString('--share: 100%', $this->page($product));
    }

    public function test_a_product_without_reviews_draws_no_chart(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'quantity' => 5]);

        $this->assertStringNotContainsString('reviews-distribution', $this->page($product));
    }

    public function test_the_first_review_is_asked_for(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'quantity' => 5]);
        $buyer = $this->eligibleBuyer($product);

        $html = $this->actingAs($buyer)->get('/products/'.$product->slug)->assertOk()->getContent();

        // Le seul moment où la page a une raison de demander.
        $this->assertStringContainsString('Soyez le premier à donner votre avis', $html);
        $this->assertStringContainsString('review-form is-first', $html);
        // Et elle ne dit pas deux fois qu'il n'y a pas d'avis. La phrase vit
        // aussi dans le lien des étoiles, en haut : c'est la ligne de la
        // section qui doit disparaître, pas le mot.
        $this->assertStringNotContainsString('reviews-empty', $html);
    }

    public function test_a_buyer_of_an_already_reviewed_product_gets_the_plain_form(): void
    {
        $product = $this->reviewedProduct();
        $buyer = $this->eligibleBuyer($product);

        $html = $this->actingAs($buyer)->get('/products/'.$product->slug)->assertOk()->getContent();

        $this->assertStringContainsString('Laisser un avis', $html);
        $this->assertStringNotContainsString('Soyez le premier', $html);
    }

    public function test_a_passer_by_still_reads_that_there_is_nothing(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'quantity' => 5]);

        // Sans droit d'écrire, on n'a pas d'invitation : on a l'état.
        $this->assertStringContainsString('class="reviews-empty"', $this->page($product));
    }

    public function test_a_gallery_thumbnail_reserves_its_place(): void
    {
        $product = $this->reviewedProduct();
        $product->images()->create(['image' => 'products/autre.webp', 'sort_order' => 1]);

        $html = $this->page($product->fresh());

        $this->assertMatchesRegularExpression('#product-detail-thumb.*?width="400"\s+height="400"#s', $html);
    }
}
