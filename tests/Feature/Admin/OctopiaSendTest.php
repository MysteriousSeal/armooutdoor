<?php

namespace Tests\Feature\Admin;

use App\Models\CdiscountListing;
use App\Models\OctopiaSubmission;
use App\Models\OctopiaTemplate;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Sending products to Octopia's catalogue, and asking what became of them.
 *
 * Octopia is never called. What has to hold is the shape of what leaves, that
 * nothing incomplete or unreachable is sent, and that the answer Octopia gives
 * afterwards is kept against the lines it concerns.
 */
class OctopiaSendTest extends TestCase
{
    use RefreshDatabase;

    private const AUTH = 'https://auth.octopia-io.net/*';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        URL::forceScheme('https');
        URL::forceRootUrl('https://shop.test');
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
        return OctopiaTemplate::query()->create([
            'code' => '0U0O05',
            'name' => 'CAGOULE TECHNIQUE',
            'fields' => [
                ['column' => null, 'code' => '3263', 'label' => 'Couleur(s)', 'required' => true, 'kind' => 'multi', 'constraint' => '', 'options' => []],
                ['column' => null, 'code' => '46831', 'label' => 'Taille', 'required' => true, 'kind' => 'monoranged', 'constraint' => '', 'options' => ['M']],
            ],
        ]);
    }

    private function product(array $overrides = []): Product
    {
        return Product::factory()->create($overrides + [
            'name' => ['fr' => 'Cagoule désert', 'en' => 'Desert balaclava'],
            'sku' => 'CAG-DESERT',
            'gtin' => '3760452700039',
            'brand' => 'Armo',
            'image' => 'products/cagoule.webp',
            'description' => ['fr' => '<p>Une cagoule <strong>respirante</strong>.</p>', 'en' => '<p>A balaclava.</p>'],
        ]);
    }

    private function listing(Product $product, OctopiaTemplate $template, array $values): CdiscountListing
    {
        return CdiscountListing::query()->create([
            'product_id' => $product->id,
            'octopia_template_id' => $template->id,
            'values' => $values,
            'per_variant' => [],
        ]);
    }

    private function fakeOctopia(array $extra = []): void
    {
        Http::fake($extra + [
            self::AUTH => Http::response(['access_token' => 'tok', 'expires_in' => 7200]),
            'https://api.octopia-io.net/seller/v2/products-integration' => Http::response(['packageId' => 'pkg-1'], 202),
        ]);
    }

    public function test_the_product_leaves_in_the_shape_octopia_takes(): void
    {
        $template = $this->category();
        $product = $this->product();
        $this->listing($product, $template, ['3263' => 'Beige;Noir', '46831' => 'M']);
        $this->fakeOctopia();

        $this->actingAs($this->admin())
            ->post('/admin/marketplaces/cdiscount/categories/'.$template->id.'/send', ['lines' => [$product->id.':0']])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        Http::assertSent(function ($request) {
            if (! str_ends_with($request->url(), '/products-integration')) {
                return false;
            }

            $sent = $request['products'][0];

            return $request->method() === 'POST'
                && $request->hasHeader('SellerId', '4242')
                && count($request['products']) === 1
                // An integer, not a string.
                && $sent['gtin'] === 3760452700039
                && $sent['sellerProductReference'] === 'CAG-DESERT'
                && $sent['title'] === 'Cagoule désert'
                && $sent['description'] === 'Une cagoule respirante.'
                && $sent['brand'] === 'Armo'
                && $sent['categoryCode'] === '0U0O05'
                && $sent['sellerPictureUrls'][0]['index'] === 1
                && str_starts_with($sent['sellerPictureUrls'][0]['url'], 'https://')
                // A property that takes several values is split on semicolons.
                && $sent['attributes'] === [
                    ['propertyReference' => '3263', 'values' => ['Beige', 'Noir']],
                    ['propertyReference' => '46831', 'values' => ['M']],
                ]
                && ! isset($sent['variantGroupReference']);
        });

        $submission = OctopiaSubmission::query()->firstOrFail();
        $this->assertSame('pkg-1', $submission->package_id);
        $this->assertSame('3760452700039', $submission->lines[0]['gtin']);
    }

    public function test_the_meta_description_is_what_octopia_reads_when_there_is_one(): void
    {
        $template = $this->category();
        $product = $this->product(['meta_description' => 'Cagoule respirante en polyester, pour le sport et l\'outdoor.']);
        $this->listing($product, $template, ['3263' => 'Beige', '46831' => 'M']);
        $this->fakeOctopia();

        $this->actingAs($this->admin())
            ->post('/admin/marketplaces/cdiscount/categories/'.$template->id.'/send', ['lines' => [$product->id.':0']])
            ->assertSessionHasNoErrors();

        // Not the long description, which is what sent it to another category.
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/products-integration')
            && $request['products'][0]['description'] === 'Cagoule respirante en polyester, pour le sport et l\'outdoor.');
    }

    public function test_the_long_description_stands_in_when_there_is_no_meta_description(): void
    {
        $template = $this->category();
        $product = $this->product(['meta_description' => '  ']);
        $this->listing($product, $template, ['3263' => 'Beige', '46831' => 'M']);
        $this->fakeOctopia();

        $this->actingAs($this->admin())
            ->post('/admin/marketplaces/cdiscount/categories/'.$template->id.'/send', ['lines' => [$product->id.':0']]);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/products-integration')
            && $request['products'][0]['description'] === 'Une cagoule respirante.');
    }

    public function test_a_meta_description_alone_is_enough_for_the_description(): void
    {
        $template = $this->category();
        $product = $this->product(['description' => ['fr' => ''], 'meta_description' => 'Cagoule respirante.']);
        $this->listing($product, $template, ['3263' => 'Beige', '46831' => 'M']);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/cdiscount')
            ->assertOk()
            ->assertDontSee('Description, ')
            ->assertDontSee('>Description<', false);
    }

    public function test_each_variant_is_its_own_line_tied_by_a_group_reference(): void
    {
        $template = $this->category();
        $product = $this->product();
        $this->listing($product, $template, ['3263' => 'Beige', '46831' => 'M']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'CAG-DESERT-M', 'gtin' => '3760452700046', 'quantity' => 2]);
        $this->fakeOctopia();

        $this->actingAs($this->admin())
            ->post('/admin/marketplaces/cdiscount/categories/'.$template->id.'/send', ['lines' => [$product->id.':'.$variant->id]])
            ->assertSessionHasNoErrors();

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/products-integration')
            && $request['products'][0]['gtin'] === 3760452700046
            && $request['products'][0]['sellerProductReference'] === 'CAG-DESERT-M'
            && $request['products'][0]['variantGroupReference'] === 'CAG-DESERT');
    }

    public function test_a_line_with_something_missing_is_not_sent(): void
    {
        $template = $this->category();
        $product = $this->product(['gtin' => null]);
        $this->listing($product, $template, ['3263' => 'Beige']);
        $this->fakeOctopia();

        $this->actingAs($this->admin())
            ->post('/admin/marketplaces/cdiscount/categories/'.$template->id.'/send', ['lines' => [$product->id.':0']])
            ->assertSessionHasErrors('lines');

        Http::assertNothingSent();
        $this->assertSame(0, OctopiaSubmission::query()->count());
    }

    public function test_pictures_that_are_not_https_are_not_sent(): void
    {
        URL::forceScheme('http');
        URL::forceRootUrl('http://127.0.0.1:8000');
        $template = $this->category();
        $product = $this->product();
        $this->listing($product, $template, ['3263' => 'Beige', '46831' => 'M']);
        $this->fakeOctopia();

        $this->actingAs($this->admin())
            ->post('/admin/marketplaces/cdiscount/categories/'.$template->id.'/send', ['lines' => [$product->id.':0']])
            ->assertSessionHasErrors('lines');

        Http::assertNothingSent();
    }

    public function test_a_line_without_a_description_or_a_photo_says_so(): void
    {
        $template = $this->category();
        $product = $this->product(['description' => ['fr' => ''], 'image' => '']);
        $this->listing($product, $template, ['3263' => 'Beige', '46831' => 'M']);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/cdiscount')
            ->assertOk()
            ->assertSee('Description, Photo');
    }

    public function test_octopia_refusing_the_batch_is_reported_and_nothing_is_kept(): void
    {
        $template = $this->category();
        $product = $this->product();
        $this->listing($product, $template, ['3263' => 'Beige', '46831' => 'M']);
        $this->fakeOctopia(['https://api.octopia-io.net/seller/v2/products-integration' => Http::response(['message' => 'bad gtin'], 400)]);

        $this->actingAs($this->admin())
            ->post('/admin/marketplaces/cdiscount/categories/'.$template->id.'/send', ['lines' => [$product->id.':0']])
            ->assertSessionHasErrors('lines');

        $this->assertSame(0, OctopiaSubmission::query()->count());
    }

    public function test_a_write_is_never_sent_twice_on_its_own(): void
    {
        $template = $this->category();
        $product = $this->product();
        $this->listing($product, $template, ['3263' => 'Beige', '46831' => 'M']);
        $this->fakeOctopia(['https://api.octopia-io.net/seller/v2/products-integration' => Http::response('', 500)]);

        $this->actingAs($this->admin())
            ->post('/admin/marketplaces/cdiscount/categories/'.$template->id.'/send', ['lines' => [$product->id.':0']]);

        Http::assertSentCount(2);
    }

    public function test_the_report_is_kept_against_the_lines_it_concerns(): void
    {
        $template = $this->category();
        $submission = $template->submissions()->create([
            'package_id' => 'pkg-1',
            'lines' => [
                ['gtin' => '3760452700039', 'reference' => 'A', 'title' => 'Cagoule A'],
                ['gtin' => '3760452700046', 'reference' => 'B', 'title' => 'Cagoule B'],
            ],
        ]);
        $this->fakeOctopia(['https://api.octopia-io.net/seller/v2/products-integration-reports*' => Http::response(['items' => [
            ['gtin' => '3760452700039', 'status' => 'Integrated', 'operationType' => 'Creation', 'errors' => [], 'warnings' => []],
            ['gtin' => '3760452700046', 'status' => 'Refused', 'errors' => [['code' => 'E1', 'field' => 'title', 'message' => 'Titre trop long']]],
        ]])]);

        $this->actingAs($this->admin())
            ->post('/admin/marketplaces/cdiscount/submissions/'.$submission->id.'/check')
            ->assertSessionHasNoErrors();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'products-integration-reports')
            && $request['packageId'] === 'pkg-1');

        $outcomes = $submission->fresh()->outcomes();
        $this->assertSame('Integrated', $outcomes[0]['status']);
        $this->assertSame('Refused', $outcomes[1]['status']);
        $this->assertTrue($submission->fresh()->isSettled());

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/cdiscount?template='.$template->id)
            ->assertOk()
            ->assertSee('Integrated')
            ->assertSee('Refused')
            ->assertSee('Titre trop long');
    }

    public function test_a_batch_still_being_processed_is_not_settled(): void
    {
        $submission = $this->category()->submissions()->create([
            'package_id' => 'pkg-2',
            'lines' => [['gtin' => '3760452700039', 'reference' => 'A', 'title' => 'Cagoule A']],
            'report' => [['gtin' => '3760452700039', 'status' => 'Validated']],
        ]);

        $this->assertFalse($submission->isSettled());
    }

    public function test_the_page_lists_a_ready_line_as_tickable_and_a_late_one_as_not(): void
    {
        $template = $this->category();
        $ready = $this->product();
        $this->listing($ready, $template, ['3263' => 'Beige', '46831' => 'M']);
        $late = $this->product(['sku' => 'LATE', 'gtin' => null]);
        $this->listing($late, $template, []);

        $html = $this->actingAs($this->admin())->get('/admin/marketplaces/cdiscount')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#value="'.$ready->id.':0"\s+checked\s+aria-label#', $html);
        $this->assertMatchesRegularExpression('#value="'.$late->id.':0"\s+disabled\s+aria-label#', $html);
    }
}
