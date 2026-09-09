<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Support\Guides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The two things a catalogue cannot say about itself: what customers
 * said, and what the shop wrote. Both sit on the page that gets the most
 * visits rather than in the footer alone.
 */
class HomeVoicesAndReadingsTest extends TestCase
{
    use RefreshDatabase;

    private function review(Product $product, int $rating, string $comment): ProductReview
    {
        $user = User::factory()->create(['first_name' => 'Jean', 'last_name' => 'martin']);

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

        return ProductReview::query()->create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'order_id' => $order->id,
            'rating' => $rating,
            'comment' => $comment,
        ]);
    }

    public function test_the_home_quotes_five_star_reviews_and_names_their_product(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'quantity' => 5]);
        $this->review($product, 5, 'Cibles parfaites, impacts visibles de loin.');
        $this->review($product, 3, 'Correct sans plus.');

        $this->get('/')->assertOk()
            ->assertSee('Ce que disent nos clients')
            ->assertSee('Cibles parfaites, impacts visibles de loin.')
            // First name and last initial, the reviews' own privacy rule.
            ->assertSee('Jean M.')
            // The same name again as a monogram, in the avatar beside it.
            ->assertSee('<span class="home-voice-avatar" aria-hidden="true">JM</span>', false)
            // The product is named in the foot, not pictured beside the quote.
            ->assertSee($product->localizedName())
            // A middling review is not a testimonial.
            ->assertDontSee('Correct sans plus.');
    }

    public function test_the_voices_line_ends_on_the_shops_own_score(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'quantity' => 5]);
        $this->review($product, 5, 'Cibles parfaites.');
        $this->review($product, 4, 'Bien.');
        $this->review($product, 3, 'Correct sans plus.');

        // Four, five and three: an average of 4,00 over three reviews.
        $this->get('/')->assertOk()
            ->assertSee('>4,00<', false)
            ->assertSee('/ 5')
            ->assertSee('3 avis')
            // Painted to the rounded figure, so the stars cannot say one
            // thing while the number says another.
            ->assertSee('--home-rating-fill: 80%', false);
    }

    public function test_the_score_is_given_to_the_hundredth(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'quantity' => 5]);

        // Nineteen fives and a four: 4,95, which one decimal rounds to 5,0
        // and the page then printed as « 5 / 5 ». The shop was claiming a
        // perfect record it does not have, and saying something the back
        // office contradicted on the very same figure.
        for ($i = 0; $i < 19; $i++) {
            $this->review($product, 5, 'Parfait '.$i.'.');
        }

        $this->review($product, 4, 'Bien.');

        $this->get('/')->assertOk()
            ->assertSee('>4,95<', false)
            ->assertDontSee('>5,00<', false)
            ->assertSee('20 avis');
    }

    public function test_the_score_counts_every_review_but_quotes_only_what_a_visitor_can_open(): void
    {
        $shown = Product::factory()->create(['is_active' => true, 'quantity' => 5]);
        $retired = Product::factory()->create(['is_active' => false]);

        $this->review($shown, 5, 'Excellent.');
        $this->review($shown, 4, 'Bien.');
        // Five stars, so nothing but the retirement keeps it out of the
        // quotes: a four would have been dropped by the rating filter and
        // the test would have proved nothing.
        $this->review($retired, 5, 'Cinq etoiles sur un produit retire.');

        // The score is the shop's whole record, and the same number the back
        // office reports: counting only what is still on sale made the two
        // pages disagree by however many reviews sat on a retired product.
        $this->get('/')->assertOk()
            ->assertSee('3 avis')
            ->assertSee('>4,67<', false)
            // The quote is the other half, and it is not shown: a
            // testimonial links the product it judged, and a link to a
            // product nobody can open reads like an invention.
            ->assertDontSee('Cinq etoiles sur un produit retire.')
            ->assertSee('Excellent.');
    }

    public function test_the_line_carries_no_score_before_the_first_review(): void
    {
        Product::factory()->create(['is_active' => true, 'quantity' => 5]);

        // Nothing to average yet, and "0 / 5" would read as a verdict.
        $this->get('/')->assertOk()->assertDontSee('home-rating-stars', false);
    }

    public function test_the_home_links_the_days_guides_and_the_latest_article(): void
    {
        $post = BlogPost::factory()->create();
        $response = $this->get('/')->assertOk()
            ->assertSee('À lire avant de commander')
            ->assertSee(route('guides.index'))
            ->assertSee(route('blog.show', $post->slug))
            ->assertSee($post->localizedTitle());

        // Which two guides is the day's business; that there are two of them
        // and that they are real ones is not.
        $shown = collect(Guides::all())
            ->filter(fn (array $guide): bool => str_contains($response->getContent(), $guide['url']));

        $this->assertCount(2, $shown);
    }

    public function test_each_card_says_what_it_is_and_which_rayon_it_advises_on(): void
    {
        // The factory files its posts under « Conseils ».
        BlogPost::factory()->create();

        $html = $this->get('/')->assertOk()->getContent();

        // « Guide » alone did not say which shelf, and « Conseils » alone did
        // not say the article came from the blog.
        $this->assertSame(2, substr_count($html, '<span class="home-reading-kind">Guide</span>'));

        foreach (Guides::ofTheDay() as $guide) {
            $this->assertStringContainsString('<span class="home-reading-topic">'.$guide['topic'].'</span>', $html);
        }

        $this->assertStringContainsString('<span class="home-reading-kind">Blog</span>', $html);
        $this->assertStringContainsString('<span class="home-reading-topic">Conseils</span>', $html);
    }

    public function test_a_shop_with_nothing_published_still_shows_its_guides(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // Both guides keep their rayon; there is simply no article card.
        $this->assertSame(2, substr_count($html, 'home-reading-kind'));
        $this->assertStringNotContainsString('>Blog</span>', $html);
    }

    public function test_the_product_is_named_in_the_signature_not_pictured(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'quantity' => 5]);
        $this->review($product, 5, 'Cibles parfaites.');

        $html = $this->get('/')->assertOk()->getContent();

        preg_match('/<div class="home-voice-quote">.*?<\/div>/s', $html, $quote);
        preg_match('/<div class="home-voice-foot">.*?<\/div>/s', $html, $foot);

        $this->assertStringNotContainsString('home-voice-thumb', $html);
        $this->assertStringContainsString($product->localizedName(), $foot[0] ?? '');
        $this->assertStringNotContainsString($product->localizedName(), $quote[0] ?? '');
        // The monogram stays with the name it belongs to.
        $this->assertStringContainsString('home-voice-avatar', $foot[0] ?? '');
    }

    public function test_a_shop_without_reviews_shows_no_empty_strip(): void
    {
        $this->get('/')->assertOk()
            ->assertDontSee('home-voices', false)
            // The readings never depend on customer data, so they stay.
            ->assertSee('home-readings', false);
    }

    public function test_the_h1_names_the_aisles(): void
    {
        $this->get('/')->assertOk()->assertSee('Cibles, entretien', false);
    }
}
