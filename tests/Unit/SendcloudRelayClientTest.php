<?php

namespace Tests\Unit;

use App\Services\SendcloudRelayClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendcloudRelayClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.sendcloud.public_key' => 'test-public-key',
            'services.sendcloud.secret_key' => 'test-secret-key',
        ]);
    }

    public function test_it_returns_an_empty_array_when_credentials_are_missing(): void
    {
        config(['services.sendcloud.public_key' => null]);
        Http::fake();

        $results = (new SendcloudRelayClient)->searchByPostalCode('mondial_relay', '75004');

        $this->assertSame([], $results);
        Http::assertNothingSent();
    }

    public function test_it_returns_an_empty_array_for_a_blank_postal_code(): void
    {
        Http::fake();

        $results = (new SendcloudRelayClient)->searchByPostalCode('mondial_relay', '');

        $this->assertSame([], $results);
        Http::assertNothingSent();
    }

    public function test_it_maps_a_successful_response_into_relay_points(): void
    {
        Http::fake([
            'servicepoints.sendcloud.sc/*' => Http::response([
                [
                    'code' => 'FR12345',
                    'name' => 'Tabac des Archives',
                    'street' => 'Rue des Archives',
                    'house_number' => '14',
                    'postal_code' => '75004',
                    'city' => 'Paris',
                    'country' => 'FR',
                    'formatted_opening_times' => [],
                ],
            ], 200),
        ]);

        $results = (new SendcloudRelayClient)->searchByPostalCode('mondial_relay', '75004');

        $this->assertCount(1, $results);
        $this->assertSame('FR12345', $results[0]['num']);
        $this->assertSame('Tabac des Archives', $results[0]['name']);
        $this->assertSame('Rue des Archives 14', $results[0]['line1']);
        $this->assertSame('75004', $results[0]['postal_code']);
    }

    public function test_it_skips_points_without_a_code(): void
    {
        Http::fake([
            'servicepoints.sendcloud.sc/*' => Http::response([
                ['code' => '', 'name' => 'No code'],
                ['code' => 'FR999', 'name' => 'Has code'],
            ], 200),
        ]);

        $results = (new SendcloudRelayClient)->searchByPostalCode('mondial_relay', '75004');

        $this->assertCount(1, $results);
        $this->assertSame('FR999', $results[0]['num']);
    }

    public function test_it_returns_an_empty_array_on_an_http_error_response(): void
    {
        Http::fake([
            'servicepoints.sendcloud.sc/*' => Http::response(['error' => 'nope'], 500),
        ]);

        $results = (new SendcloudRelayClient)->searchByPostalCode('mondial_relay', '75004');

        $this->assertSame([], $results);
    }

    public function test_it_returns_an_empty_array_when_the_request_times_out(): void
    {
        Http::fake(function (): void {
            throw new ConnectionException('Connection timed out');
        });

        $results = (new SendcloudRelayClient)->searchByPostalCode('mondial_relay', '75004');

        $this->assertSame([], $results);
    }

    public function test_it_returns_an_empty_array_for_a_malformed_json_payload(): void
    {
        Http::fake([
            'servicepoints.sendcloud.sc/*' => Http::response('not-an-array', 200),
        ]);

        $results = (new SendcloudRelayClient)->searchByPostalCode('mondial_relay', '75004');

        $this->assertSame([], $results);
    }

    public function test_it_sends_the_requested_carrier_and_postal_code(): void
    {
        Http::fake(['servicepoints.sendcloud.sc/*' => Http::response([], 200)]);

        (new SendcloudRelayClient)->searchByPostalCode('chronopost', '69002', 'FR');

        Http::assertSent(function ($request): bool {
            return $request['carrier'] === 'chronopost'
                && $request['address'] === '69002'
                && $request['country'] === 'FR';
        });
    }
}
