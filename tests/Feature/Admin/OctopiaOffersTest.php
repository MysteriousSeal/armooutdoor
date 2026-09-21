<?php

namespace Tests\Feature\Admin;

use App\Models\CdiscountListing;
use App\Models\Discount;
use App\Models\OctopiaSubmission;
use App\Models\OctopiaTemplate;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\Octopia\Exporter;
use App\Support\Octopia\Readiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Putting products on sale on Cdiscount: the offer's settings kept on the
 * product's page, the offer built from them, and the package that carries it
 * to Octopia.
 *
 * Octopia is never called. What has to hold is that a price, a stock and a
 * delivery leave in the shape its offer packages take, that an offer without
 * what Octopia requires is not sent, and that its answer is kept against the
 * lines it concerns.
 */
class OctopiaOffersTest extends TestCase
{
    use RefreshDatabase;

    private const AUTH = 'https://auth.octopia-io.net/*';

    private const BASE = 'https://api.octopia-io.net/seller/v2';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config([
            'services.octopia.client_id' => 'client',
            'services.octopia.client_secret' => 'secret',
            'services.octopia.seller_id' => '4242',
        ]);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function category(): OctopiaTemplate
    {
        return OctopiaTemplate::query()->create(['code' => '0U0O05', 'name' => 'CAGOULE TECHNIQUE', 'fields' => []]);
    }

    private function product(array $overrides = []): Product
    {
        return Product::factory()->create($overrides + [
            'name' => ['fr' => 'Cagoule désert', 'en' => 'Desert balaclava'],
            'sku' => 'CAG-DESERT',
            'gtin' => '3760452700039',
            'price_cents' => 1000,
            'quantity' => 7,
        ]);
    }

    /** @param array<string, mixed> $offer */
    private function listing(Product $product, OctopiaTemplate $template, ?array $offer = null): CdiscountListing
    {
        return CdiscountListing::query()->create([
            'product_id' => $product->id,
            'octopia_template_id' => $template->id,
            'values' => [],
            'per_variant' => [],
            'offer' => $offer,
        ]);
    }

    /** @return array<string, mixed> */
    private function offer(array $overrides = []): array
    {
        return $overrides + [
            'condition' => 'New',
            'markup' => 10,
            'preparation_days' => 2,
            'delivery' => ['THD' => ['cost' => 4.9, 'additional' => 1.5], 'PPMR' => ['cost' => 3.5, 'additional' => null]],
        ];
    }

    /** @return array<string, mixed> the first line's offer payload */
    private function payload(Product $product, OctopiaTemplate $template): array
    {
        $listings = $template->listings()->with(['variants', 'product.variants', 'product.images'])->get();

        return (new Exporter($template))->lines($listings)[0]['offer'];
    }

    // -------------------------------------------------------------- the settings

    public function test_the_offer_settings_are_saved_on_the_products_page(): void
    {
        $template = $this->category();
        $product = $this->product();

        $this->actingAs($this->admin())
            ->put('/admin/products/'.$product->id.'/cdiscount', [
                'octopia_template_id' => $template->id,
                'offer' => [
                    'condition' => 'UsedLikeNew',
                    'markup' => '12.5',
                    'preparation_days' => '3',
                    'delivery' => [
                        'THD' => ['enabled' => '1', 'cost' => '4.90', 'additional' => '1.50'],
                        // Ticked but without a cost: not a delivery mode.
                        'SHD' => ['enabled' => '1', 'cost' => ''],
                        // A cost but not ticked: not offered.
                        'PPMR' => ['cost' => '3.50'],
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        $settings = $product->fresh()->cdiscountListing->offerSettings();

        $this->assertSame('UsedLikeNew', $settings['condition']);
        $this->assertSame(12.5, $settings['markup']);
        $this->assertSame(3, $settings['preparation_days']);
        $this->assertSame(['THD' => ['cost' => 4.9, 'additional' => 1.5]], $settings['delivery']);
    }

    public function test_a_free_delivery_is_a_cost_of_zero_and_counts(): void
    {
        $template = $this->category();
        $product = $this->product();

        $this->actingAs($this->admin())
            ->put('/admin/products/'.$product->id.'/cdiscount', [
                'octopia_template_id' => $template->id,
                'offer' => ['preparation_days' => '1', 'delivery' => ['THD' => ['enabled' => '1', 'cost' => '0']]],
            ]);

        $this->assertSame([], $product->fresh()->cdiscountListing->offerMissing());
    }

    public function test_nonsense_in_the_offer_is_refused(): void
    {
        $template = $this->category();
        $product = $this->product();

        $this->actingAs($this->admin())
            ->put('/admin/products/'.$product->id.'/cdiscount', [
                'octopia_template_id' => $template->id,
                'offer' => ['condition' => 'Pristine', 'markup' => '900', 'preparation_days' => '-1'],
            ])
            ->assertSessionHasErrors(['offer.condition', 'offer.markup', 'offer.preparation_days']);
    }

    public function test_a_product_starts_from_the_default_offer(): void
    {
        $template = $this->category();
        $product = $this->product();
        $this->listing($product, $template);

        $html = $this->actingAs($this->admin())
            ->get('/admin/products/'.$product->id.'/cdiscount')
            ->assertOk()
            ->assertSee('Tracked home delivery')
            ->assertSee('Signed home delivery')
            ->assertSee('Mondial Relay pickup point')
            // 10,00 € in the shop, and 40% more on Cdiscount.
            ->assertSee('Sold on Cdiscount at')
            ->assertSee('14,00')
            ->assertSee('shop price 10,00')
            ->getContent();

        $this->assertMatchesRegularExpression('#name="offer\[markup\]"[^>]*value="40"#', $html);
        // No VAT to set: every tax goes out as zero.
        $this->assertStringNotContainsString('name="offer[vat]"', $html);
        $this->assertStringContainsString('The VAT, the eco-tax and the D3E tax are all sent as 0', $html);
        $this->assertMatchesRegularExpression('#name="offer\[preparation_days\]"[^>]*value="1"#', $html);

        // Every way of delivering is offered, at its cost.
        foreach (['THD' => '3', 'SHD' => '5', 'PPMR' => '0'] as $code => $cost) {
            $this->assertMatchesRegularExpression('#name="offer\[delivery\]\['.$code.'\]\[enabled\]"[^>]*checked#', $html);
            $this->assertMatchesRegularExpression('#name="offer\[delivery\]\['.$code.'\]\[cost\]"[^>]*value="'.$cost.'"#', $html);
        }
    }

    public function test_the_default_offer_goes_out_without_the_seller_having_saved_anything(): void
    {
        $template = $this->category();
        $product = $this->product();
        $this->listing($product, $template);

        $offer = $this->payload($product, $template);

        $this->assertSame([], $offer['missing']);
        $this->assertSame(1400, $offer['price_cents']);
        $this->assertSame(14.0, $offer['payload']['price']['price']);
        $this->assertSame([
            ['code' => 'VAT', 'value' => 0.0],
            ['code' => 'Ecotax', 'value' => 0.0],
            ['code' => 'Deatax', 'value' => 0.0],
        ], $offer['payload']['price']['taxes']);
        $this->assertSame(1, $offer['payload']['preparationTime']);
        $this->assertSame([
            ['code' => 'THD', 'cost' => 3.0],
            ['code' => 'SHD', 'cost' => 5.0],
            ['code' => 'PPMR', 'cost' => 0.0],
        ], $offer['payload']['deliveryModes']);
    }

    public function test_each_variant_shows_its_price_on_cdiscount(): void
    {
        $template = $this->category();
        $product = $this->product();
        ProductVariant::create(['product_id' => $product->id, 'attribute_values' => [['label' => 'Taille', 'value' => 'M']], 'sku' => 'CAG-M', 'gtin' => '3760452700046', 'price_cents' => 2000, 'quantity' => 1]);
        ProductVariant::create(['product_id' => $product->id, 'attribute_values' => [['label' => 'Taille', 'value' => 'L']], 'sku' => 'CAG-L', 'gtin' => '3760452700053', 'quantity' => 1]);
        $this->listing($product, $template);

        $this->actingAs($this->admin())
            ->get('/admin/products/'.$product->id.'/cdiscount')
            ->assertOk()
            // 20 € raised by 40%, and the variant without a price of its own at the product's 10 €.
            ->assertSee('28,00')
            ->assertSee('14,00');
    }

    public function test_the_offer_is_a_mark_on_the_product_page(): void
    {
        $template = $this->category();
        $product = $this->product();
        $this->listing($product, $template, ['delivery' => []]);

        $this->assertFalse(Readiness::checks($product->fresh())['Offer']);

        $product->cdiscountListing->update(['offer' => $this->offer()]);

        $this->assertTrue(Readiness::checks($product->fresh())['Offer']);
    }

    // ------------------------------------------------------------ the offer built

    public function test_the_offer_is_the_shop_price_raised_by_the_markup_with_the_shops_stock(): void
    {
        $template = $this->category();
        $product = $this->product();
        $this->listing($product, $template, $this->offer());

        $offer = $this->payload($product, $template);

        $this->assertSame([], $offer['missing']);
        $this->assertSame(1100, $offer['price_cents']);
        $this->assertSame([
            'sellerExternalReference' => 'CAG-DESERT',
            'product' => ['gtin' => '3760452700039', 'reference' => 'CAG-DESERT'],
            'condition' => 'New',
            // Cdiscount refuses an offer without its eco-tax and D3E, even at zero.
            'price' => ['price' => 11.0, 'taxes' => [
                ['code' => 'VAT', 'value' => 0.0],
                ['code' => 'Ecotax', 'value' => 0.0],
                ['code' => 'Deatax', 'value' => 0.0],
            ]],
            'deliveryModes' => [
                ['code' => 'THD', 'cost' => 4.9, 'additionalCost' => 1.5],
                ['code' => 'PPMR', 'cost' => 3.5],
            ],
            'preparationTime' => 2,
            'quantity' => 7,
        ], $offer['payload']);
    }

    public function test_every_tax_is_zero_whatever_an_old_setting_says(): void
    {
        $template = $this->category();
        $product = $this->product();
        // A listing saved before the VAT setting went: its 20 is not sent.
        $this->listing($product, $template, $this->offer(['vat' => 20]));

        $taxes = $this->payload($product, $template)['payload']['price']['taxes'];

        $this->assertSame(['VAT', 'Ecotax', 'Deatax'], array_column($taxes, 'code'));
        $this->assertSame([0.0, 0.0, 0.0], array_column($taxes, 'value'));
    }

    public function test_a_promotion_sends_the_undiscounted_price_struck_through(): void
    {
        $template = $this->category();
        $product = $this->product();
        Discount::query()->create(['product_id' => $product->id, 'type' => 'percentage', 'value' => 20]);
        $this->listing($product, $template, $this->offer(['markup' => 0]));

        $price = $this->payload($product->fresh(), $template)['payload']['price'];

        $this->assertSame(8.0, $price['price']);
        $this->assertSame(10.0, $price['originPrice']);
    }

    public function test_each_variant_is_offered_at_its_own_price_and_stock(): void
    {
        $template = $this->category();
        $product = $this->product();
        ProductVariant::create(['product_id' => $product->id, 'sku' => 'CAG-DESERT-M', 'gtin' => '3760452700046', 'price_cents' => 1500, 'quantity' => 2]);
        $this->listing($product, $template, $this->offer(['markup' => 0]));

        $offer = $this->payload($product->fresh(), $template);

        $this->assertSame('CAG-DESERT-M', $offer['payload']['sellerExternalReference']);
        $this->assertSame('3760452700046', $offer['payload']['product']['gtin']);
        $this->assertSame(15.0, $offer['payload']['price']['price']);
        $this->assertSame(2, $offer['payload']['quantity']);
    }

    public function test_an_offer_without_a_preparation_time_or_a_tracked_delivery_says_so(): void
    {
        $template = $this->category();
        $product = $this->product();
        $this->listing($product, $template, ['delivery' => ['PPMR' => ['cost' => 3.5]]]);

        $this->assertSame(['Preparation time', 'Tracked delivery'], $this->payload($product, $template)['missing']);
    }

    // ------------------------------------------------------------ the package

    /** @param array<string, mixed> $extra */
    private function fakeOffers(array $extra = []): void
    {
        Http::fake($extra + [
            self::AUTH => Http::response(['access_token' => 'tok', 'expires_in' => 7200]),
            self::BASE.'/offer-packages' => Http::response('', 201, ['Content-Location' => 'offers-packages/off-1']),
            self::BASE.'/offer-packages/off-1/offer-requests' => Http::response('', 201),
            self::BASE.'/offer-packages/off-1' => fn ($request) => $request->method() === 'PATCH'
                ? Http::response('', 204)
                : Http::response(['state' => 'WaitingForCompletion']),
        ]);
    }

    public function test_offers_go_in_a_package_that_is_opened_filled_and_closed(): void
    {
        $template = $this->category();
        $product = $this->product();
        $this->listing($product, $template, $this->offer());
        $this->fakeOffers();

        $this->actingAs($this->admin())
            ->post('/admin/marketplaces/cdiscount/categories/'.$template->id.'/offers', ['lines' => [$product->id.':0']])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        // Opened, on the sales channel, for upserts.
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === self::BASE.'/offer-packages'
            && $request->hasHeader('salesChannelId', 'CDISFR')
            && $request->hasHeader('SellerId', '4242')
            && $request['packageType'] === 'Upsert');

        // Filled: a list of offers, not an object.
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/offer-packages/off-1/offer-requests')
            && array_is_list($request->data())
            && $request->data()[0]['sellerExternalReference'] === 'CAG-DESERT'
            && $request->data()[0]['quantity'] === 7);

        // Closed: only then does Octopia process it.
        Http::assertSent(fn ($request) => $request->method() === 'PATCH'
            && str_ends_with($request->url(), '/offer-packages/off-1')
            && $request['state'] === 'Ready');

        $submission = OctopiaSubmission::query()->firstOrFail();
        $this->assertSame('offers', $submission->kind);
        $this->assertSame('off-1', $submission->package_id);
        $this->assertSame('CAG-DESERT', $submission->lines[0]['reference']);
    }

    public function test_an_offer_missing_what_octopia_requires_is_not_sent(): void
    {
        $template = $this->category();
        $product = $this->product();
        $this->listing($product, $template, ['delivery' => []]);
        $this->fakeOffers();

        $this->actingAs($this->admin())
            ->post('/admin/marketplaces/cdiscount/categories/'.$template->id.'/offers', ['lines' => [$product->id.':0']])
            ->assertSessionHasErrors('lines');

        Http::assertNothingSent();
        $this->assertSame(0, OctopiaSubmission::query()->count());
    }

    public function test_a_line_without_an_ean_is_not_offered(): void
    {
        $template = $this->category();
        $product = $this->product(['gtin' => null]);
        $this->listing($product, $template, $this->offer());
        $this->fakeOffers();

        $this->actingAs($this->admin())
            ->post('/admin/marketplaces/cdiscount/categories/'.$template->id.'/offers', ['lines' => [$product->id.':0']])
            ->assertSessionHasErrors('lines');

        Http::assertNothingSent();
    }

    public function test_a_package_that_fails_after_it_was_opened_names_it(): void
    {
        $template = $this->category();
        $product = $this->product();
        $this->listing($product, $template, $this->offer());
        $this->fakeOffers([self::BASE.'/offer-packages/off-1/offer-requests' => Http::response(['error' => 'bad offer'], 400)]);

        $this->actingAs($this->admin())
            ->post('/admin/marketplaces/cdiscount/categories/'.$template->id.'/offers', ['lines' => [$product->id.':0']])
            ->assertSessionHasErrors('lines');

        $this->assertStringContainsString('off-1', session('errors')->first('lines'));
        // Nothing is kept as sent: it was not completed.
        $this->assertSame(0, OctopiaSubmission::query()->count());
    }

    public function test_the_results_are_kept_against_the_offers_they_concern(): void
    {
        $template = $this->category();
        $submission = $template->submissions()->create([
            'kind' => 'offers',
            'package_id' => 'off-1',
            'lines' => [
                ['gtin' => '3760452700039', 'reference' => 'REF-A', 'title' => 'Cagoule A'],
                ['gtin' => '3760452700046', 'reference' => 'REF-B', 'title' => 'Cagoule B'],
            ],
        ]);
        $this->fakeOffers([
            self::BASE.'/offer-packages/off-1' => Http::response(['state' => 'Integrated']),
            self::BASE.'/offer-packages/off-1/offer-requests-results*' => Http::response(['items' => [
                ['sellerExternalReference' => 'REF-A', 'integrationStatus' => 'Integrated', 'results' => [['resultCode' => '8000', 'message' => 'Offer created']]],
                ['sellerExternalReference' => 'REF-B', 'integrationStatus' => 'Rejected', 'results' => [['resultCode' => '4001', 'message' => 'Unknown product']]],
            ]]),
        ]);

        $this->actingAs($this->admin())
            ->post('/admin/marketplaces/cdiscount/submissions/'.$submission->id.'/check')
            ->assertSessionHasNoErrors();

        $outcomes = $submission->fresh()->outcomes();
        $this->assertSame('Integrated', $outcomes[0]['status']);
        // The confirmation of an integrated offer is not an error.
        $this->assertSame([], $outcomes[0]['errors']);
        $this->assertSame('Rejected', $outcomes[1]['status']);
        $this->assertSame('Unknown product', $outcomes[1]['errors'][0]['message']);
        $this->assertTrue($submission->fresh()->isSettled());

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/cdiscount?template='.$template->id)
            ->assertOk()
            ->assertSee('Offers')
            ->assertSee('Rejected')
            ->assertSee('Unknown product');
    }

    public function test_results_are_not_asked_for_while_octopia_is_still_processing_the_package(): void
    {
        $template = $this->category();
        $submission = $template->submissions()->create(['kind' => 'offers', 'package_id' => 'off-1', 'lines' => []]);
        $this->fakeOffers([
            self::BASE.'/offer-packages/off-1' => Http::response(['state' => 'IntegrationPending']),
            // Asked too early, Octopia answers with an error.
            self::BASE.'/offer-packages/off-1/offer-requests-results*' => Http::response(['title' => 'The retrieval of the offer requests results failed'], 400),
        ]);

        $this->actingAs($this->admin())
            ->post('/admin/marketplaces/cdiscount/submissions/'.$submission->id.'/check')
            ->assertSessionHasErrors('submission');

        $this->assertStringContainsString('still processing', session('errors')->first('submission'));
        $this->assertStringContainsString('IntegrationPending', session('errors')->first('submission'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'offer-requests-results'));
        $this->assertNull($submission->fresh()->report);
    }

    public function test_octopias_refusal_is_said_in_its_own_words(): void
    {
        $template = $this->category();
        $submission = $template->submissions()->create(['kind' => 'products', 'package_id' => 'pkg-1', 'lines' => []]);
        $this->fakeOffers([self::BASE.'/products-integration-reports*' => Http::response([
            'type' => 'https://datatracker.ietf.org/doc/html/rfc9110#name-400-bad-request',
            'status' => 400,
            'title' => 'The retrieval failed',
            'traceId' => '831ed20e',
        ], 400)]);

        $this->actingAs($this->admin())
            ->post('/admin/marketplaces/cdiscount/submissions/'.$submission->id.'/check')
            ->assertSessionHasErrors('submission');

        // The title, not a screenful of JSON.
        $this->assertSame('Octopia responded 400: The retrieval failed', session('errors')->first('submission'));
    }

    public function test_a_rejected_offer_shows_octopias_reason_without_a_stray_colon(): void
    {
        $template = $this->category();
        $template->submissions()->create([
            'kind' => 'offers',
            'package_id' => 'off-1',
            'lines' => [['gtin' => '3760452700039', 'reference' => 'REF-A', 'title' => 'Cagoule A']],
            'report' => [['sellerExternalReference' => 'REF-A', 'integrationStatus' => 'Rejected', 'results' => [
                ['resultCode' => '1008', 'message' => "EcoTax : Champ obligatoire pour la création d'une offre"],
            ]]],
        ]);

        $html = $this->actingAs($this->admin())
            ->get('/admin/marketplaces/cdiscount?template='.$template->id)
            ->assertOk()
            ->assertSee('Rejected')
            ->getContent();

        $this->assertStringContainsString('EcoTax : Champ obligatoire', $html);
        // No field name to put in front of it.
        $this->assertDoesNotMatchRegularExpression('#octopia-missing">: #', $html);
    }

    public function test_the_results_are_followed_page_after_page(): void
    {
        $template = $this->category();
        $submission = $template->submissions()->create(['kind' => 'offers', 'package_id' => 'off-1', 'lines' => []]);
        // The second page first: the first matching pattern answers, and the
        // wildcard would match it too.
        $this->fakeOffers([
            self::BASE.'/offer-packages/off-1' => Http::response(['state' => 'Integrated']),
            self::BASE.'/offer-packages/off-1/offer-requests-results?cursor=2' => Http::response(
                ['items' => [['sellerExternalReference' => 'B', 'integrationStatus' => 'Rejected']]],
            ),
            self::BASE.'/offer-packages/off-1/offer-requests-results*' => Http::response(
                ['items' => [['sellerExternalReference' => 'A', 'integrationStatus' => 'Integrated']]],
                200,
                ['Link' => '<'.self::BASE.'/offer-packages/off-1/offer-requests-results?cursor=2>; rel="next"'],
            ),
        ]);

        $this->actingAs($this->admin())->post('/admin/marketplaces/cdiscount/submissions/'.$submission->id.'/check');

        $this->assertSame(['A', 'B'], array_column($submission->fresh()->report, 'sellerExternalReference'));
    }

    public function test_the_page_puts_the_offer_beside_each_line(): void
    {
        $template = $this->category();
        $ready = $this->product();
        $this->listing($ready, $template, $this->offer());
        $late = $this->product(['sku' => 'LATE', 'gtin' => '3760452700053']);
        $this->listing($late, $template, ['delivery' => []]);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/cdiscount')
            ->assertOk()
            ->assertSee('11,00')
            ->assertSee('7 in stock')
            ->assertSee('Preparation time, Tracked delivery')
            ->assertSee('Put them on sale');
    }
}
