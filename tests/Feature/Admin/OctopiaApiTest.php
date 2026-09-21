<?php

namespace Tests\Feature\Admin;

use App\Models\OctopiaTemplate;
use App\Models\User;
use App\Services\Octopia\OctopiaClient;
use App\Support\Octopia\ApiFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Cdiscount's categories, read from Octopia's API instead of an Excel file.
 *
 * Octopia is never called: the responses are the shape its documentation
 * gives. What has to hold is the sign-in, the reading of the category list
 * and of a category's properties, and that the result lands where the rest of
 * the shop already looks for a category's attributes.
 */
class OctopiaApiTest extends TestCase
{
    use RefreshDatabase;

    private const AUTH = 'https://auth.octopia-io.net/*';

    private const API = 'https://api.octopia-io.net/seller/v2/*';

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

    /** @return list<array<string, mixed>> */
    private function properties(): array
    {
        return [
            ['propertyReference' => '46831', 'index' => 2, 'necessity' => 'Mandatory', 'label' => 'Taille', 'isRanged' => true, 'isMultiple' => false, 'isNumeric' => false, 'choices' => ['S', 'M', 'Taille unique']],
            ['propertyReference' => '3263', 'index' => 1, 'necessity' => 'Recommended', 'label' => 'Couleur(s)', 'isRanged' => false, 'isMultiple' => true, 'isNumeric' => false],
            ['propertyReference' => '9999', 'index' => 3, 'necessity' => 'Optional', 'label' => 'Poids', 'unit' => 'g', 'isNumeric' => true, 'isRanged' => false, 'isMultiple' => false],
        ];
    }

    private function fakeOctopia(): void
    {
        Http::fake([
            self::AUTH => Http::response(['access_token' => 'tok-1', 'expires_in' => 7200]),
            'https://api.octopia-io.net/seller/v2/categories/0U0O05/properties' => Http::response(['items' => $this->properties()]),
            'https://api.octopia-io.net/seller/v2/categories/0U0O05' => Http::response(['categoryReference' => '0U0O05', 'label' => 'CAGOULE TECHNIQUE', 'level' => 3]),
            self::API => Http::response(['items' => []]),
        ]);
    }

    public function test_it_signs_in_with_the_credentials_and_sends_the_seller_id(): void
    {
        $this->fakeOctopia();

        (new OctopiaClient)->properties('0U0O05');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'openid-connect/token')
            && $request['grant_type'] === 'client_credentials'
            && $request['client_id'] === 'client'
            && $request['client_secret'] === 'secret');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/categories/0U0O05/properties')
            && $request->hasHeader('Authorization', 'Bearer tok-1')
            && $request->hasHeader('SellerId', '4242')
            && $request->hasHeader('Accept-Language', 'fr-FR'));
    }

    public function test_the_token_is_kept_between_calls(): void
    {
        $this->fakeOctopia();

        $client = new OctopiaClient;
        $client->properties('0U0O05');
        $client->category('0U0O05');

        Http::assertSentCount(3);
    }

    public function test_a_token_that_octopia_refuses_is_asked_for_again(): void
    {
        Http::fake([
            self::AUTH => Http::sequence()
                ->push(['access_token' => 'old', 'expires_in' => 7200])
                ->push(['access_token' => 'new', 'expires_in' => 7200]),
            self::API => Http::sequence()
                ->push([], 401)
                ->push(['items' => $this->properties()]),
        ]);

        $this->assertCount(3, (new OctopiaClient)->properties('0U0O05'));
    }

    public function test_refused_credentials_are_said_plainly(): void
    {
        Http::fake([self::AUTH => Http::response(['error' => 'unauthorized_client'], 401)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('refused the credentials');

        (new OctopiaClient)->properties('0U0O05');
    }

    public function test_without_credentials_nothing_is_called(): void
    {
        config(['services.octopia.client_secret' => null]);
        Http::fake();

        $this->assertFalse((new OctopiaClient)->isConfigured());

        try {
            (new OctopiaClient)->categories();
            $this->fail('Expected an exception.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('OCTOPIA_CLIENT_ID', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_the_categories_are_read_page_by_page_and_only_level_three_is_kept(): void
    {
        $page = fn (int $from, int $count, int $level = 3) => array_map(
            fn (int $i): array => ['categoryReference' => sprintf('C%05d', $from + $i), 'label' => 'Cat '.($from + $i), 'level' => $level, 'isActive' => true, 'parentReferences' => ['P'.$level]],
            range(0, $count - 1),
        );

        Http::fake([
            self::AUTH => Http::response(['access_token' => 'tok', 'expires_in' => 7200]),
            // By page number: the pages are asked together, not in turn.
            self::API => fn ($request) => Http::response(['items' => match ((int) $request['pageIndex']) {
                1 => array_merge($page(0, 99), [['categoryReference' => 'TOP001', 'label' => 'Top', 'level' => 1, 'isActive' => true]]),
                2 => array_merge($page(100, 2), [['categoryReference' => 'OLD001', 'label' => 'Old', 'level' => 3, 'isActive' => false]]),
                default => [],
            }]),
        ]);

        $categories = (new OctopiaClient)->categories();

        // A first page of 100 items is a full one, level 1 among them: the
        // read goes on until a page comes back short. 99 + 2 are kept.
        $this->assertCount(101, $categories);
        $this->assertNotContains('TOP001', array_column($categories, 'code'));
        $this->assertNotContains('OLD001', array_column($categories, 'code'));
    }

    public function test_the_category_fields_are_asked_for(): void
    {
        Http::fake([
            self::AUTH => Http::response(['access_token' => 'tok', 'expires_in' => 7200]),
            self::API => Http::response(['items' => []]),
        ]);

        (new OctopiaClient)->categories();

        // Octopia answers with the reference alone otherwise.
        Http::assertSent(fn ($request) => str_contains($request->url(), '/categories')
            && ($request['fields'] ?? '') === 'label,level,isActive,isBrandMandatory,parentReferences');
    }

    public function test_an_empty_list_is_not_kept(): void
    {
        $items = [];

        Http::fake([
            self::AUTH => Http::response(['access_token' => 'tok', 'expires_in' => 7200]),
            self::API => function ($request) use (&$items) {
                return Http::response(['items' => (int) $request['pageIndex'] === 1 ? $items : []]);
            },
        ]);

        $client = new OctopiaClient;

        $this->assertSame([], $client->categories());

        // Octopia answers properly the next time: the empty one was not kept.
        $items = [['categoryReference' => '0U0O05', 'label' => 'Cagoule', 'level' => 3, 'isActive' => true]];

        $this->assertCount(1, $client->categories());
    }

    public function test_the_category_list_is_kept(): void
    {
        Http::fake([
            self::AUTH => Http::response(['access_token' => 'tok', 'expires_in' => 7200]),
            self::API => Http::response(['items' => [['categoryReference' => '0U0O05', 'label' => 'Cagoule', 'level' => 3, 'isActive' => true]]]),
        ]);

        $client = new OctopiaClient;
        $client->categories();
        $sent = count(Http::recorded());

        $client->categories();

        // The second call reads what was kept: nothing more is asked.
        Http::assertSentCount($sent);
    }

    public function test_properties_become_the_fields_the_shop_already_reads(): void
    {
        $fields = ApiFields::fromProperties($this->properties());

        // In Octopia's own order, whatever order they arrive in.
        $this->assertSame(['3263', '46831', '9999'], array_column($fields, 'code'));

        $size = $fields[1];
        $this->assertSame('Taille', $size['label']);
        $this->assertTrue($size['required']);
        $this->assertSame(['S', 'M', 'Taille unique'], $size['options']);
        $this->assertSame('monoranged', $size['kind']);

        $colour = $fields[0];
        $this->assertFalse($colour['required']);
        $this->assertSame([], $colour['options']);
        $this->assertSame('multi', $colour['kind']);

        $this->assertSame('Numérique · Unité : g', $fields[2]['constraint']);
    }

    public function test_a_category_is_read_from_the_api_and_kept(): void
    {
        $this->fakeOctopia();

        $this->actingAs($this->admin())
            ->post('/admin/marketplaces/cdiscount/categories', ['code' => '0U0O05'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $template = OctopiaTemplate::query()->firstOrFail();

        $this->assertSame('0U0O05', $template->code);
        $this->assertSame('CAGOULE TECHNIQUE', $template->name);
        $this->assertNotNull($template->synced_at);
        $this->assertCount(3, $template->fields);
        $this->assertCount(1, $template->requiredAttributeFields());
    }

    public function test_reading_a_category_again_refreshes_it(): void
    {
        $this->fakeOctopia();
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/marketplaces/cdiscount/categories', ['code' => '0U0O05']);
        $this->actingAs($admin)->post('/admin/marketplaces/cdiscount/categories', ['code' => '0U0O05']);

        $this->assertSame(1, OctopiaTemplate::query()->count());
    }

    public function test_a_malformed_code_is_refused_before_octopia_is_asked(): void
    {
        Http::fake();

        $this->actingAs($this->admin())
            ->post('/admin/marketplaces/cdiscount/categories', ['code' => 'nope'])
            ->assertSessionHasErrors('code');

        Http::assertNothingSent();
    }

    public function test_the_page_finds_categories_by_name_without_accents_or_case(): void
    {
        Http::fake([
            self::AUTH => Http::response(['access_token' => 'tok', 'expires_in' => 7200]),
            self::API => Http::response(['items' => [
                ['categoryReference' => '0U0O05', 'label' => 'Cagoule technique', 'level' => 3, 'isActive' => true],
                ['categoryReference' => 'AB12CD', 'label' => 'Sac à dos', 'level' => 3, 'isActive' => true],
            ]]),
        ]);

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/cdiscount?find=CAGOULE')
            ->assertOk()
            ->assertSee('Cagoule technique')
            ->assertSee('0U0O05')
            ->assertDontSee('Sac à dos');

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/cdiscount?find=sac a dos')
            ->assertOk()
            ->assertSee('AB12CD');
    }

    public function test_without_credentials_the_page_says_what_to_set(): void
    {
        config(['services.octopia.client_id' => null]);
        Http::fake();

        $this->actingAs($this->admin())
            ->get('/admin/marketplaces/cdiscount?find=cagoule')
            ->assertOk()
            ->assertSee('OCTOPIA_CLIENT_ID')
            ->assertDontSee('Name or 6-character code');

        Http::assertNothingSent();
    }

    public function test_a_category_can_be_removed(): void
    {
        $template = OctopiaTemplate::query()->create([
            'code' => '0U0O05', 'name' => 'API ONE', 'fields' => ApiFields::fromProperties($this->properties()), 'synced_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->delete('/admin/marketplaces/cdiscount/categories/'.$template->id)
            ->assertRedirect();

        $this->assertModelMissing($template);
    }
}
