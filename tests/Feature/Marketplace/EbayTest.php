<?php

namespace Tests\Feature\Marketplace;

use App\Models\EbayConnection;
use App\Models\EbayListing;
use App\Models\Marketplace;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * eBay in the admin. Nothing here reaches eBay: every call goes through
 * Http::fake(), so no test depends on the network or could write there.
 */
class EbayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ebay.client_id' => 'client-id',
            'services.ebay.client_secret' => 'client-secret',
            'services.ebay.ru_name' => 'Armo-RuName',
            'services.ebay.environment' => 'production',
        ]);

        Http::preventStrayRequests();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function connected(array $overrides = []): EbayConnection
    {
        return EbayConnection::query()->create(array_merge([
            'refresh_token' => 'refresh-token',
            'refresh_token_expires_at' => now()->addMonths(18),
            'access_token' => 'access-token',
            'access_token_expires_at' => now()->addHour(),
        ], $overrides));
    }

    private function sellingXml(string $items, int $pages = 1): string
    {
        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <GetMyeBaySellingResponse xmlns="urn:ebay:apis:eBLBaseComponents">
              <Ack>Success</Ack>
              <ActiveList>
                <ItemArray>{$items}</ItemArray>
                <PaginationResult><TotalNumberOfPages>{$pages}</TotalNumberOfPages></PaginationResult>
              </ActiveList>
            </GetMyeBaySellingResponse>
            XML;
    }

    private const PLAIN_ITEM = <<<'XML'
        <Item>
          <ItemID>110001</ItemID>
          <Title>Ceinture Tactique EVA Khaki</Title>
          <SKU>MOLLE-BELT-EVA-KHAKI</SKU>
          <Quantity>5</Quantity>
          <QuantityAvailable>3</QuantityAvailable>
          <SellingStatus><CurrentPrice currencyID="EUR">22.99</CurrentPrice><QuantitySold>2</QuantitySold></SellingStatus>
          <ListingDetails><ViewItemURL>https://www.ebay.fr/itm/110001</ViewItemURL></ListingDetails>
        </Item>
        XML;

    private const VARIATION_ITEM = <<<'XML'
        <Item>
          <ItemID>110002</ItemID>
          <Title>Gants tactiques</Title>
          <SellingStatus><CurrentPrice currencyID="EUR">19.90</CurrentPrice><QuantitySold>1</QuantitySold></SellingStatus>
          <Variations>
            <Variation>
              <SKU>GLOVE-M</SKU><Quantity>4</Quantity><StartPrice currencyID="EUR">19.90</StartPrice>
              <SellingStatus><QuantitySold>1</QuantitySold></SellingStatus>
              <VariationSpecifics><NameValueList><Name>Taille</Name><Value>M</Value></NameValueList></VariationSpecifics>
            </Variation>
            <Variation>
              <SKU>GLOVE-L</SKU><Quantity>2</Quantity><StartPrice currencyID="EUR">19.90</StartPrice>
              <VariationSpecifics><NameValueList><Name>Taille</Name><Value>L</Value></NameValueList></VariationSpecifics>
            </Variation>
          </Variations>
        </Item>
        XML;

    public function test_connect_sends_the_admin_to_ebay_with_a_state(): void
    {
        $response = $this->actingAs($this->admin())->post('/admin/marketplaces/ebay/connect');

        $state = session('ebay_oauth_state');
        $this->assertNotEmpty($state);

        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://auth.ebay.com/oauth2/authorize?', $location);
        $this->assertStringContainsString('redirect_uri=Armo-RuName', $location);
        $this->assertStringContainsString('state='.$state, $location);
        $this->assertStringContainsString('sell.inventory', $location);
    }

    public function test_connect_says_so_when_no_app_is_set_up(): void
    {
        config(['services.ebay.client_id' => null]);

        $this->actingAs($this->admin())
            ->from('/admin/marketplaces/ebay')
            ->post('/admin/marketplaces/ebay/connect')
            ->assertRedirect('/admin/marketplaces/ebay')
            ->assertSessionHasErrors('ebay');
    }

    public function test_the_callback_refuses_a_state_it_did_not_send(): void
    {
        $this->actingAs($this->admin())
            ->withSession(['ebay_oauth_state' => 'expected'])
            ->get('/admin/marketplaces/ebay/callback?state=forged&code=abc')
            ->assertRedirect('/admin/marketplaces/ebay')
            ->assertSessionHasErrors('ebay');

        $this->assertSame(0, EbayConnection::query()->count());
    }

    public function test_the_callback_keeps_the_tokens_encrypted(): void
    {
        Http::fake([
            'api.ebay.com/identity/v1/oauth2/token' => Http::response([
                'access_token' => 'new-access',
                'expires_in' => 7200,
                'refresh_token' => 'new-refresh',
                'refresh_token_expires_in' => 47304000,
            ]),
        ]);

        $this->actingAs($this->admin())
            ->withSession(['ebay_oauth_state' => 'good'])
            ->get('/admin/marketplaces/ebay/callback?state=good&code=the-code')
            ->assertRedirect('/admin/marketplaces/ebay')
            ->assertSessionHas('status', 'eBay connected.');

        Http::assertSent(fn (Request $request): bool => $request['grant_type'] === 'authorization_code'
            && $request['code'] === 'the-code'
            && $request['redirect_uri'] === 'Armo-RuName'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('client-id:client-secret')));

        $connection = EbayConnection::current();
        $this->assertSame('new-refresh', $connection->refresh_token);
        $this->assertNotSame('new-refresh', DB::table('ebay_connections')->value('refresh_token'));
        $this->assertTrue($connection->refresh_token_expires_at->isAfter(now()->addMonths(17)));
    }

    public function test_sync_reads_listings_and_their_variations(): void
    {
        $this->connected();
        Http::fake(['api.ebay.com/ws/api.dll' => Http::response($this->sellingXml(self::PLAIN_ITEM.self::VARIATION_ITEM))]);

        $this->actingAs($this->admin())
            ->post('/admin/marketplaces/ebay/sync')
            ->assertSessionHas('status', '2 listings fetched: 2 added, 0 updated.');

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-EBAY-API-CALL-NAME', 'GetMyeBaySelling')
            && $request->hasHeader('X-EBAY-API-SITEID', '71')
            && $request->hasHeader('X-EBAY-API-IAF-TOKEN', 'access-token'));

        $plain = EbayListing::query()->where('item_id', '110001')->first();
        $this->assertSame('MOLLE-BELT-EVA-KHAKI', $plain->sku);
        $this->assertSame(2299, $plain->price_cents);
        $this->assertSame(3, $plain->quantity);
        $this->assertSame(2, $plain->quantity_sold);
        $this->assertSame('https://www.ebay.fr/itm/110001', $plain->url);

        $gloves = EbayListing::query()->where('item_id', '110002')->first();
        $this->assertNull($gloves->sku);
        $this->assertSame(['GLOVE-M', 'GLOVE-L'], $gloves->skus());
        // Four listed less one sold, plus two.
        $this->assertSame(5, $gloves->quantity);
        $this->assertSame(['Taille' => 'M'], $gloves->variations[0]['specifics']);
    }

    public function test_sync_drops_listings_that_have_ended(): void
    {
        $this->connected();
        EbayListing::query()->create(['item_id' => '999', 'title' => 'Ended']);
        Http::fake(['api.ebay.com/ws/api.dll' => Http::response($this->sellingXml(self::PLAIN_ITEM))]);

        $this->actingAs($this->admin())->post('/admin/marketplaces/ebay/sync')
            ->assertSessionHas('status', '1 listings fetched: 1 added, 0 updated, 1 removed.');

        $this->assertSame(['110001'], EbayListing::query()->pluck('item_id')->all());
    }

    public function test_sync_renews_a_lapsed_access_token(): void
    {
        $this->connected(['access_token_expires_at' => now()->subMinute()]);
        Http::fake([
            'api.ebay.com/identity/v1/oauth2/token' => Http::response(['access_token' => 'fresh', 'expires_in' => 7200]),
            'api.ebay.com/ws/api.dll' => Http::response($this->sellingXml('')),
        ]);

        $this->actingAs($this->admin())->post('/admin/marketplaces/ebay/sync')->assertSessionHasNoErrors();

        Http::assertSent(fn (Request $request): bool => ($request->data()['grant_type'] ?? null) === 'refresh_token');
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-EBAY-API-IAF-TOKEN', 'fresh'));
        $this->assertSame('fresh', EbayConnection::current()->access_token);
    }

    public function test_an_ebay_failure_is_shown_not_hidden(): void
    {
        $this->connected();
        Http::fake(['api.ebay.com/ws/api.dll' => Http::response(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <GetMyeBaySellingResponse xmlns="urn:ebay:apis:eBLBaseComponents">
              <Ack>Failure</Ack>
              <Errors><ShortMessage>Invalid token</ShortMessage><LongMessage>The token is invalid.</LongMessage></Errors>
            </GetMyeBaySellingResponse>
            XML)]);

        $this->actingAs($this->admin())->post('/admin/marketplaces/ebay/sync')
            ->assertSessionHasErrors(['ebay' => 'eBay sync failed. eBay GetMyeBaySelling failed: The token is invalid.']);
    }

    public function test_the_page_sets_listings_beside_the_catalogue(): void
    {
        $this->connected();
        $product = Product::factory()->create(['sku' => 'MOLLE-BELT-EVA-KHAKI', 'quantity' => 4]);
        EbayListing::query()->create([
            'item_id' => '110001',
            'title' => 'Ceinture Tactique EVA Khaki',
            'sku' => 'MOLLE-BELT-EVA-KHAKI',
            'price_cents' => 2299,
            'quantity' => 3,
            'synced_at' => now(),
        ]);
        EbayListing::query()->create(['item_id' => '110003', 'title' => 'Inconnu', 'sku' => 'NOPE', 'synced_at' => now()]);

        $this->actingAs($this->admin())->get('/admin/marketplaces/ebay')
            ->assertOk()
            ->assertSee('Connected')
            ->assertSee(route('admin.products.edit', $product), false)
            ->assertSee('Not the quantity on eBay')
            ->assertSee('Not in catalogue');
    }

    public function test_the_hub_card_links_to_the_ebay_page(): void
    {
        Marketplace::query()->create(['name' => 'Ebay']);

        $this->actingAs($this->admin())->get('/admin/marketplaces')
            ->assertOk()
            ->assertSee(route('admin.marketplaces.ebay'), false)
            ->assertSee('Not connected yet');
    }
}
