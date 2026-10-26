<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Address suggestions for the checkout and « Mes adresses », from the Géoplateforme. */
class AddressSearchTest extends TestCase
{
    use RefreshDatabase;

    private function feature(string $type, string $name, string $postcode, string $city): array
    {
        return ['type' => 'Feature', 'properties' => [
            'type' => $type,
            'name' => $name,
            'postcode' => $postcode,
            'city' => $city,
            'label' => "{$name} {$postcode} {$city}",
        ]];
    }

    public function test_it_returns_street_level_matches_ready_to_fill_the_form(): void
    {
        Http::fake(['data.geopf.fr/*' => Http::response(['features' => [
            $this->feature('housenumber', '12 Rue Anita Conti', '44300', 'Nantes'),
            $this->feature('street', 'Rue Anita Conti', '56000', 'Vannes'),
            // A town alone has no street to fill the line with.
            $this->feature('municipality', 'Nantes', '44000', 'Nantes'),
        ]])]);

        $this->actingAs(User::factory()->create())
            ->getJson('/address-search?q=12 rue anita con')
            ->assertOk()
            ->assertExactJson(['results' => [
                ['label' => '12 Rue Anita Conti 44300 Nantes', 'line1' => '12 Rue Anita Conti', 'postal_code' => '44300', 'city' => 'Nantes'],
                ['label' => 'Rue Anita Conti 56000 Vannes', 'line1' => 'Rue Anita Conti', 'postal_code' => '56000', 'city' => 'Vannes'],
            ]]);

        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://data.geopf.fr/geocodage/search')
            && $request['q'] === '12 rue anita con'
            && $request['index'] === 'address');
    }

    public function test_a_short_query_asks_nothing(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->getJson('/address-search?q=12')
            ->assertOk()
            ->assertExactJson(['results' => []]);

        Http::assertNothingSent();
    }

    public function test_a_failing_service_gives_no_suggestions_and_is_not_cached(): void
    {
        Http::fake(['data.geopf.fr/*' => Http::response('down', 503)]);
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/address-search?q=rue de rivoli')->assertOk()->assertExactJson(['results' => []]);

        $this->assertNull(Cache::get('address-search:'.md5('rue de rivoli')));
    }

    public function test_answers_are_cached(): void
    {
        Http::fake(['data.geopf.fr/*' => Http::response(['features' => [
            $this->feature('housenumber', '10 Rue de Rivoli', '75004', 'Paris'),
        ]])]);
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/address-search?q=10 rue de rivoli')->assertOk();
        // Same address, other case and spacing: the same cache entry.
        $this->actingAs($user)->getJson('/address-search?'.http_build_query(['q' => ' 10  Rue de Rivoli ']))->assertOk()->assertJsonPath('results.0.city', 'Paris');

        Http::assertSentCount(1);
    }

    public function test_guests_cannot_use_it(): void
    {
        $this->getJson('/address-search?q=rue de rivoli')->assertUnauthorized();
    }

    public function test_the_address_fields_carry_the_suggestions(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/account/addresses')
            ->assertOk()
            ->assertSee('data-address-autocomplete="'.route('address-search').'"', false)
            ->assertSee('js/address-autocomplete.js', false);
    }
}
