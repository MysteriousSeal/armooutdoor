<?php

namespace App\Services;

use App\Services\Concerns\CondensesOpeningHours;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Relay/pickup points via Sendcloud's Service Points API, which abstracts
 * several carrier networks (Mondial Relay, Chronopost Shop2Shop, ...) behind
 * one endpoint — no per-carrier account or webservice integration needed,
 * just a Sendcloud account with Service Points activated and the carrier
 * enabled.
 */
class SendcloudRelayClient
{
    use CondensesOpeningHours;

    private const ENDPOINT = 'https://servicepoints.sendcloud.sc/api/v2/service-points';

    private const DAYS = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

    /**
     * Radius (metres) used to search around the postal code instead of a
     * strict postal-code-boundary match — small/rural postal codes often
     * have zero or one point of their own, but plenty nearby.
     */
    private const SEARCH_RADIUS_METERS = 15000;

    private const MAX_RESULTS = 40;

    /**
     * Relay points near a postal code, nearest first, with opening hours.
     * Empty when credentials are missing or the call fails.
     *
     * @return array<int, array{num: string, name: string, line1: string, postal_code: string, city: string, country: string, hours: ?string}>
     */
    public function searchByPostalCode(string $carrier, string $postalCode, string $country = 'FR'): array
    {
        $publicKey = config('services.sendcloud.public_key');
        $secretKey = config('services.sendcloud.secret_key');

        if (blank($publicKey) || blank($secretKey) || blank($postalCode)) {
            return [];
        }

        try {
            $response = Http::withBasicAuth($publicKey, $secretKey)
                ->timeout(8)
                ->get(self::ENDPOINT, [
                    'country' => strtoupper($country),
                    'carrier' => $carrier,
                    'address' => $postalCode,
                    'radius' => self::SEARCH_RADIUS_METERS,
                ]);
        } catch (\Throwable $exception) {
            Log::warning('Sendcloud relay search failed', ['carrier' => $carrier, 'message' => $exception->getMessage()]);

            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $points = $response->json();

        if (! is_array($points)) {
            return [];
        }

        $points = array_slice($points, 0, self::MAX_RESULTS);

        return array_values(array_filter(array_map(function (array $point): ?array {
            $code = trim((string) ($point['code'] ?? ''));

            if ($code === '') {
                return null;
            }

            $line1 = trim(($point['street'] ?? '').' '.($point['house_number'] ?? ''));

            return [
                'num' => $code,
                'name' => trim((string) ($point['name'] ?? '')),
                'line1' => $line1,
                'postal_code' => trim((string) ($point['postal_code'] ?? '')),
                'city' => trim((string) ($point['city'] ?? '')),
                'country' => trim((string) ($point['country'] ?? '')),
                'hours' => $this->formatHours($point['formatted_opening_times'] ?? []),
            ];
        }, $points)));
    }

    /**
     * @param  array<int|string, array<int, string>>  $openingTimes  keyed 0 (Monday) to 6 (Sunday)
     */
    private function formatHours(array $openingTimes): ?string
    {
        $days = [];

        foreach (self::DAYS as $index => $label) {
            $ranges = $openingTimes[$index] ?? $openingTimes[(string) $index] ?? [];
            $ranges = array_values(array_filter(array_map('trim', $ranges)));

            $days[] = ['label' => $label, 'range' => $ranges === [] ? null : implode(', ', $ranges)];
        }

        return $this->condenseDays($days);
    }
}
