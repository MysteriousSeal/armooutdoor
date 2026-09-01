<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * « Les offres du moment », between the promises and the aisles.
 *
 * Only what is genuinely reduced, and nothing at all when nothing is: an
 * offers heading over an empty row is worse than no heading.
 */
class HomeDealsTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $name): Product
    {
        return Product::factory()->create([
            'is_active' => true,
            'name' => ['fr' => $name],
            'price_cents' => 2000,
            'category_id' => Category::factory()->create()->id,
        ]);
    }

    private function discount(Product $product, ?string $endsAt = null): Discount
    {
        return Discount::query()->create([
            'product_id' => $product->id,
            'type' => 'percentage',
            'value' => 20,
            'ends_at' => $endsAt,
        ]);
    }

    public function test_nothing_reduced_means_no_block_at_all(): void
    {
        $this->product('Cible ordinaire');

        $this->get('/')
            ->assertOk()
            ->assertDontSee('home-deals', false)
            ->assertDontSee(__('store.home_deals'));
    }

    public function test_a_reduced_product_appears_in_the_strip(): void
    {
        $product = $this->product('Cible en promotion');
        $this->discount($product);

        $this->get('/')
            ->assertOk()
            ->assertSee('home-deals-row', false)
            ->assertSee(__('store.home_deals'))
            ->assertSee('Cible en promotion', false);
    }

    public function test_the_block_sits_before_the_categories(): void
    {
        // The point of it: a reason to stop, before being asked where to go.
        $this->discount($this->product('Cible en promotion'));

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertLessThan(
            strpos($html, 'id="categories"'),
            strpos($html, 'id="deals"'),
            'The offers strip should come before « Achetez par catégorie ».',
        );
    }

    public function test_an_undiscounted_product_stays_out(): void
    {
        $this->discount($this->product('Cible en promotion'));
        $this->product('Cible ordinaire');

        preg_match(
            '#<section class="home-deals".*?</section>#s',
            $this->get('/')->assertOk()->getContent(),
            $block,
        );

        $this->assertNotEmpty($block);
        $this->assertStringContainsString('Cible en promotion', $block[0]);
        $this->assertStringNotContainsString('Cible ordinaire', $block[0]);
    }

    public function test_a_discount_whose_window_has_closed_is_not_an_offer(): void
    {
        // A discount row outside its dates is not a reduction, and the
        // promotions page agrees: both ask hasDiscount(), not whereHas().
        $this->discount($this->product('Cible expirée'), now()->subDay()->toDateTimeString());

        $this->get('/')->assertOk()->assertDontSee('home-deals-row', false);
    }

    public function test_it_links_to_the_promotions_page(): void
    {
        $this->discount($this->product('Cible en promotion'));

        $this->get('/')->assertOk()->assertSee(route('products.promotions'), false);
    }

    public function test_the_row_shows_five_and_scrolls_for_the_rest(): void
    {
        // The width decides the card, not the card the width: five fill the
        // row exactly and anything beyond scrolls, rather than five fixed
        // widths leaving a ragged gap on a wide screen.
        $css = file_get_contents(public_path('css/home.css'));

        $this->assertStringContainsString('grid-auto-columns: calc((100% - 4 * 0.85rem) / 5);', $css);
        $this->assertStringContainsString('overflow-x: auto;', $css);
        $this->assertStringContainsString('scroll-snap-type: x proximity;', $css);
        // The bar is gone: nothing to scroll at five, and a track drawn under
        // the row for no reason the rest of the time.
        $this->assertStringContainsString('scrollbar-width: none;', $css);
        // overflow-x alone computes to auto on both axes, so the row scrolled
        // vertically by a few pixels for no reason.
        $this->assertStringContainsString('overflow-y: hidden;', $css);
        $this->assertStringNotContainsString('::-webkit-scrollbar-thumb', $css);
    }

    public function test_more_than_five_offers_all_reach_the_page(): void
    {
        // They scroll rather than being cut: the row is not a top five.
        foreach (range(1, 8) as $i) {
            $this->discount($this->product('Cible en promotion '.$i));
        }

        preg_match(
            '#<ul[^>]*home-deals-row.*?</ul>#s',
            $this->get('/')->assertOk()->getContent(),
            $row,
        );

        $this->assertSame(8, substr_count($row[0], 'home-deals-item'));
    }

    public function test_the_cards_wear_the_short_stock_labels_the_grids_below_use(): void
    {
        // Five across here as there, so the chip has the same room and takes
        // the same wording: the long label crowds a card this narrow.
        $product = $this->product('Cible en promotion');
        $product->forceFill(['quantity' => 2])->save();
        $this->discount($product);

        preg_match(
            '#<ul[^>]*home-deals-row.*?</ul>#s',
            $this->get('/')->assertOk()->getContent(),
            $row,
        );

        $this->assertNotEmpty($row);
        $this->assertStringContainsString('card-stock-chip', $row[0]);
        // The short form only: the full/short pair belongs to the wider cards.
        $this->assertStringNotContainsString('card-stock-chip-full', $row[0]);
    }

    public function test_no_offers_means_no_script_either(): void
    {
        // A script for a row that is not on the page is bytes spent on nothing.
        $this->product('Cible ordinaire');

        $this->get('/')->assertOk()->assertDontSee('js/home-deals.js', false);
    }

    public function test_the_row_is_wired_for_the_autoscroll(): void
    {
        $this->discount($this->product('Cible en promotion'));

        $this->get('/')
            ->assertOk()
            ->assertSee('data-deals-row', false)
            ->assertSee('js/home-deals.js', false)
            // The row moves on its own; announcing each turn would talk over
            // whatever else is being read.
            ->assertSee('aria-live="off"', false);
    }

    public function test_the_autoscroll_stops_for_the_reader(): void
    {
        $js = file_get_contents(public_path('js/home-deals.js'));

        // Motion nobody asked for, that cannot be stopped, is the one thing
        // an auto-advancing row must never be.
        $this->assertStringContainsString("matchMedia('(prefers-reduced-motion: reduce)')", $js);
        $this->assertStringContainsString("addEventListener('mouseenter', stop)", $js);
        $this->assertStringContainsString("addEventListener('focusin', stop)", $js);
        $this->assertStringContainsString('visibilitychange', $js);
    }

    public function test_it_does_not_move_a_row_with_nowhere_to_go(): void
    {
        // Five offers fill the row exactly; advancing it would only jitter.
        $js = file_get_contents(public_path('js/home-deals.js'));

        $this->assertStringContainsString('function overflows()', $js);
        $this->assertStringContainsString('!overflows()', $js);
    }

    public function test_it_returns_to_the_first_offer_rather_than_stopping(): void
    {
        $js = file_get_contents(public_path('js/home-deals.js'));

        $this->assertStringContainsString('atEnd ? 0 :', $js);
        $this->assertStringContainsString("behavior: 'smooth'", $js);
    }
}
