<?php

namespace Tests\Feature\Marketplace;

use App\Models\EbayConnection;
use App\Models\EbayListing;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The shop's stock sent to eBay. eBay is faked by call name: the listings
 * read are whatever the test put in $this->listingsXml.
 */
class EbayStockTest extends TestCase
{
    use RefreshDatabase;

    private string $listingsXml = '';

    private bool $outOfStockControl = true;

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
        Http::fake(['api.ebay.com/ws/api.dll' => fn (Request $request) => Http::response($this->answer($request->header('X-EBAY-API-CALL-NAME')[0]))]);
    }

    private function answer(string $call): string
    {
        $body = match ($call) {
            'GetMyeBaySelling' => "<ActiveList><ItemArray>{$this->listingsXml}</ItemArray><PaginationResult><TotalNumberOfPages>1</TotalNumberOfPages></PaginationResult></ActiveList>",
            'GetUserPreferences' => '<OutOfStockControlPreference>'.($this->outOfStockControl ? 'true' : 'false').'</OutOfStockControlPreference>',
            default => '',
        };

        return "<?xml version=\"1.0\"?><{$call}Response xmlns=\"urn:ebay:apis:eBLBaseComponents\"><Ack>Success</Ack>{$body}</{$call}Response>";
    }

    private function connect(): void
    {
        EbayConnection::query()->create([
            'refresh_token' => 'refresh-token',
            'refresh_token_expires_at' => now()->addMonths(18),
            'access_token' => 'access-token',
            'access_token_expires_at' => now()->addHour(),
        ]);
    }

    private function plainItem(string $id, string $sku, int $available): string
    {
        return "<Item><ItemID>{$id}</ItemID><Title>Item {$id}</Title><SKU>{$sku}</SKU><QuantityAvailable>{$available}</QuantityAvailable>"
            .'<SellingStatus><CurrentPrice currencyID="EUR">10.00</CurrentPrice><QuantitySold>0</QuantitySold></SellingStatus></Item>';
    }

    /** @return list<string> every InventoryStatus sent, as "item/sku=quantity" */
    private function revisions(): array
    {
        return Http::recorded()
            ->filter(fn (array $pair): bool => $pair[0]->header('X-EBAY-API-CALL-NAME')[0] === 'ReviseInventoryStatus')
            ->flatMap(function (array $pair): array {
                $xml = simplexml_load_string($pair[0]->body());
                $sent = [];

                foreach ($xml->InventoryStatus as $status) {
                    $sent[] = $status->ItemID.'/'.($status->SKU ?? '').'='.$status->Quantity;
                }

                return $sent;
            })
            ->values()
            ->all();
    }

    public function test_only_differing_quantities_are_sent(): void
    {
        $this->connect();
        Product::factory()->create(['sku' => 'SAME', 'quantity' => 3]);
        Product::factory()->create(['sku' => 'MORE', 'quantity' => 7]);
        $this->listingsXml = $this->plainItem('1', 'SAME', 3).$this->plainItem('2', 'MORE', 2).$this->plainItem('3', 'UNKNOWN', 5);

        $this->artisan('ebay:sync-stock')->expectsOutput('1 eBay quantity updated.')->assertSuccessful();

        $this->assertSame(['2/=7'], $this->revisions());
        $this->assertSame(7, EbayListing::query()->where('item_id', '2')->value('quantity'));
    }

    public function test_variations_are_sent_by_sku(): void
    {
        $this->connect();
        $product = Product::factory()->create(['sku' => 'GLOVE']);
        ProductVariant::query()->create(['product_id' => $product->id, 'sku' => 'GLOVE-M', 'attribute_values' => [], 'quantity' => 6, 'is_active' => true]);
        ProductVariant::query()->create(['product_id' => $product->id, 'sku' => 'GLOVE-L', 'attribute_values' => [], 'quantity' => 2, 'is_active' => true]);
        $this->listingsXml = '<Item><ItemID>9</ItemID><Title>Gants</Title>'
            .'<SellingStatus><CurrentPrice currencyID="EUR">19.90</CurrentPrice><QuantitySold>0</QuantitySold></SellingStatus><Variations>'
            .'<Variation><SKU>GLOVE-M</SKU><Quantity>4</Quantity><StartPrice>19.90</StartPrice></Variation>'
            .'<Variation><SKU>GLOVE-L</SKU><Quantity>2</Quantity><StartPrice>19.90</StartPrice></Variation>'
            .'</Variations></Item>';

        $this->artisan('ebay:sync-stock')->assertSuccessful();

        $this->assertSame(['9/GLOVE-M=6'], $this->revisions());
        $listing = EbayListing::query()->where('item_id', '9')->first();
        $this->assertSame(8, $listing->quantity);
        $this->assertSame(6, $listing->variations[0]['quantity']);
    }

    public function test_changes_go_four_to_a_call(): void
    {
        $this->connect();

        foreach (range(1, 5) as $i) {
            Product::factory()->create(['sku' => "SKU-{$i}", 'quantity' => 9]);
            $this->listingsXml .= $this->plainItem((string) $i, "SKU-{$i}", 1);
        }

        $this->artisan('ebay:sync-stock')->expectsOutput('5 eBay quantities updated.')->assertSuccessful();

        Http::assertSentCount(3);
        $this->assertCount(5, $this->revisions());
    }

    public function test_a_zero_is_held_back_when_it_would_end_the_listing(): void
    {
        $this->connect();
        $this->outOfStockControl = false;
        Product::factory()->create(['sku' => 'GONE', 'quantity' => 0]);
        Product::factory()->create(['sku' => 'MORE', 'quantity' => 5]);
        $this->listingsXml = $this->plainItem('1', 'GONE', 2).$this->plainItem('2', 'MORE', 1);

        $this->artisan('ebay:sync-stock')->assertSuccessful();

        $this->assertSame(['2/=5'], $this->revisions());
    }

    public function test_a_zero_is_sent_when_the_out_of_stock_option_is_on(): void
    {
        $this->connect();
        Product::factory()->create(['sku' => 'GONE', 'quantity' => 0]);
        $this->listingsXml = $this->plainItem('1', 'GONE', 2);

        $this->artisan('ebay:sync-stock')->assertSuccessful();

        $this->assertSame(['1/=0'], $this->revisions());
    }

    public function test_nothing_is_called_before_ebay_is_connected(): void
    {
        $this->artisan('ebay:sync-stock')->expectsOutput('eBay is not connected; nothing sent.')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_the_button_pushes_and_says_what_it_did(): void
    {
        $this->connect();
        Product::factory()->create(['sku' => 'MORE', 'quantity' => 4]);
        $this->listingsXml = $this->plainItem('2', 'MORE', 1);

        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/marketplaces/ebay/stock')
            ->assertSessionHas('status', '1 eBay quantity updated.');

        $this->assertSame(['2/=4'], $this->revisions());
    }
}
