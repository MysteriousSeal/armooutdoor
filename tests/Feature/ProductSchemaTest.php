<?php

namespace Tests\Feature;

use App\Models\Discount;
use App\Models\Carrier;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\ProductSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La fiche produit en JSON-LD.
 *
 * C'est la seule page de la boutique qui a un prix, une disponibilité et une
 * note à annoncer, et c'était la seule à ne rien déclarer.
 */
class ProductSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attributes = []): Product
    {
        return Product::factory()->create($attributes + ['is_active' => true, 'quantity' => 10]);
    }

    /** @return array<string, mixed> */
    private function schema(Product $product): array
    {
        return ProductSchema::for($product->fresh()->load('category', 'images', 'variants', 'reviews.user', 'discount'));
    }

    private function review(Product $product, int $rating): void
    {
        $user = User::factory()->create();

        // Un avis est rattaché à la commande qui y donne droit : la colonne
        // n'accepte pas NULL, et c'est la règle métier de la boutique.
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

        ProductReview::query()->create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'order_id' => $order->id,
            'rating' => $rating,
            'comment' => 'Un avis.',
        ]);
    }

    public function test_the_page_carries_the_product_and_its_breadcrumb(): void
    {
        $product = $this->product(['slug' => 'cible-ronde']);

        $html = $this->get('/products/cible-ronde')->assertOk()->getContent();

        $this->assertStringContainsString('application/ld+json', $html);
        $this->assertStringContainsString('"@type":"Product"', $html);
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
        $this->assertStringContainsString($product->localizedName(), $html);
    }

    public function test_the_offer_states_a_price_and_a_currency(): void
    {
        $offers = $this->schema($this->product(['price_cents' => 1590]))['offers'];

        // Un nombre décimal, pas « 15,90 € » : le point, pas de symbole.
        $this->assertSame('15.90', $offers['price']);
        $this->assertSame('EUR', $offers['priceCurrency']);
        $this->assertSame('https://schema.org/InStock', $offers['availability']);
    }

    public function test_the_discounted_price_is_the_one_announced(): void
    {
        $product = $this->product(['price_cents' => 2000]);
        $discount = Discount::query()->create([
            'product_id' => $product->id,
            'type' => 'percentage',
            'value' => 20,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addWeek(),
        ]);

        $offers = $this->schema($product)['offers'];

        $this->assertSame('16.00', $offers['price']);
        // Passée cette date, le prix annoncé n'est plus celui de la page.
        $this->assertSame($discount->ends_at->toDateString(), $offers['priceValidUntil']);
    }

    public function test_a_price_without_an_end_writes_no_empty_key(): void
    {
        $this->assertArrayNotHasKey('priceValidUntil', $this->schema($this->product())['offers']);
    }

    public function test_an_empty_stock_is_declared_as_such(): void
    {
        $offers = $this->schema($this->product(['quantity' => 0]))['offers'];

        $this->assertSame('https://schema.org/OutOfStock', $offers['availability']);
    }

    public function test_a_range_of_prices_becomes_an_aggregate_offer(): void
    {
        $product = $this->product(['price_cents' => 1000, 'quantity' => 0]);
        $this->variant($product, 'S', 1500);
        $this->variant($product, 'M', 2500);

        $offers = $this->schema($product)['offers'];

        // Annoncer le prix de la première taille ferait afficher un prix
        // qu'on ne pratique pas.
        $this->assertSame('AggregateOffer', $offers['@type']);
        $this->assertSame('15.00', $offers['lowPrice']);
        $this->assertSame('25.00', $offers['highPrice']);
        $this->assertSame(2, $offers['offerCount']);
    }

    public function test_variants_at_one_price_stay_a_single_offer(): void
    {
        $product = $this->product(['price_cents' => 1000, 'quantity' => 0]);
        $this->variant($product, 'S', 1500);
        $this->variant($product, 'M', 1500);

        $this->assertSame('Offer', $this->schema($product)['offers']['@type']);
    }

    public function test_the_rating_appears_only_once_someone_has_rated(): void
    {
        $product = $this->product();

        // Avec zéro avis, `reviewCount` vaudrait zéro et Google jetterait la
        // fiche entière plutôt que ce seul bloc.
        $this->assertArrayNotHasKey('aggregateRating', $this->schema($product));

        $this->review($product, 4);
        $this->review($product, 3);

        $schema = $this->schema($product);
        $this->assertSame('3.5', $schema['aggregateRating']['ratingValue']);
        $this->assertSame(2, $schema['aggregateRating']['reviewCount']);
        $this->assertCount(2, $schema['review']);
    }

    public function test_every_photograph_is_listed_once(): void
    {
        $images = $this->schema($this->product(['image' => 'products/cible.webp']))['image'];

        $this->assertNotEmpty($images);
        $this->assertSame(array_values(array_unique($images)), $images);
        // Des adresses absolues : un chemin relatif ne se résout pas côté moteur.
        $this->assertStringStartsWith('http', $images[0]);
    }

    private function variant(Product $product, string $label, int $priceCents): void
    {
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'label' => ['en' => $label, 'fr' => $label],
            'attribute_values' => [['label' => 'Taille', 'value' => $label]],
            'sku' => 'V-'.$product->id.'-'.$label,
            'price_cents' => $priceCents,
            'quantity' => 3,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_the_offer_ships_at_the_cheapest_allowed_carrier_rate(): void
    {
        Carrier::query()->create(['slug' => 'colissimo-home', 'name' => ['fr' => 'Colissimo'], 'description' => ['fr' => ''], 'eta' => ['fr' => '2–4 jours'], 'method' => 'home', 'price_cents' => 690, 'active' => true]);
        Carrier::query()->create(['slug' => 'lettre-suivie', 'name' => ['fr' => 'Lettre suivie'], 'description' => ['fr' => ''], 'eta' => ['fr' => '2–3 jours'], 'method' => 'home', 'price_cents' => 350, 'active' => true]);

        $shipping = $this->schema($this->product())['offers']['shippingDetails'];

        $this->assertSame('OfferShippingDetails', $shipping['@type']);
        $this->assertSame('3.50', $shipping['shippingRate']['value']);
        $this->assertSame('FR', $shipping['shippingDestination']['addressCountry']);
        // Transit days read off the carrier's own eta figures.
        $this->assertSame(2, $shipping['deliveryTime']['transitTime']['minValue']);
        $this->assertSame(3, $shipping['deliveryTime']['transitTime']['maxValue']);
    }

    public function test_the_shipping_rate_respects_the_products_own_carrier_rules(): void
    {
        $cheap = Carrier::query()->create(['slug' => 'lettre-suivie', 'name' => ['fr' => 'Lettre suivie'], 'description' => ['fr' => ''], 'eta' => ['fr' => '2–3 jours'], 'method' => 'home', 'price_cents' => 350, 'active' => true]);
        $allowed = Carrier::query()->create(['slug' => 'colissimo-home', 'name' => ['fr' => 'Colissimo'], 'description' => ['fr' => ''], 'eta' => ['fr' => '2–4 jours'], 'method' => 'home', 'price_cents' => 690, 'active' => true]);

        // The product refuses the cheapest carrier: the rate must follow.
        $product = $this->product(['carrier_ids' => [$allowed->id]]);

        $this->assertSame('6.90', $this->schema($product)['offers']['shippingDetails']['shippingRate']['value']);
        $this->assertTrue($cheap->exists);
    }

    public function test_shipping_is_free_once_the_product_alone_crosses_the_threshold(): void
    {
        $carrier = Carrier::query()->create(['slug' => 'mondial-relay', 'name' => ['fr' => 'Mondial Relay'], 'description' => ['fr' => ''], 'eta' => ['fr' => '3–5 jours'], 'method' => 'relay', 'price_cents' => 390, 'active' => true]);
        \App\Models\ShippingSetting::current()->update([
            'free_shipping_threshold_cents' => 4900,
            'free_shipping_carrier_ids' => [$carrier->id],
        ]);

        // 176,49 € on its own is past the 49 € bar: the page must not
        // promise a 3,90 € postage the cart would never charge.
        $rich = $this->schema($this->product(['price_cents' => 17649]));
        $cheap = $this->schema($this->product(['price_cents' => 990]));

        $this->assertSame('0.00', $rich['offers']['shippingDetails']['shippingRate']['value']);
        $this->assertSame('3.90', $cheap['offers']['shippingDetails']['shippingRate']['value']);
    }

    public function test_no_carrier_means_no_shipping_details(): void
    {
        $this->assertArrayNotHasKey('shippingDetails', $this->schema($this->product())['offers']);
    }
}
