<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Address suggestions from the Géoplateforme (IGN), which serves the Base
 * Adresse Nationale: free, no account or key, France only, which is where
 * the shop delivers. It replaced api-adresse.data.gouv.fr, retired in 2026.
 * Called from the server so the visitor's IP never reaches it and the
 * site's CSP stays closed.
 */
class AddressSearchClient
{
    private const ENDPOINT = 'https://data.geopf.fr/geocodage/search';

    private const MAX_RESULTS = 6;

    /** Below this the service matches whole towns, not addresses. */
    public const MIN_QUERY_LENGTH = 3;

    /**
     * Street-level matches for what the visitor has typed so far. Empty
     * when the query is too short or the service fails: the field then
     * simply stays a plain text field.
     *
     * @return array<int, array{label: string, line1: string, postal_code: string, city: string}>
     */
    public function search(string $query): array
    {
        $query = trim((string) preg_replace('/\s+/u', ' ', $query));

        if (mb_strlen($query) < self::MIN_QUERY_LENGTH) {
            return [];
        }

        $key = 'address-search:'.md5(mb_strtolower($query));
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

        $results = $this->fetch($query);

        // Only answers are kept: a failure is retried on the next keystroke.
        if ($results !== []) {
            Cache::put($key, $results, now()->addDay());
        }

        return $results;
    }

    /** @return array<int, array{label: string, line1: string, postal_code: string, city: string}> */
    private function fetch(string $query): array
    {
        try {
            $response = Http::acceptJson()
                ->timeout(3)
                ->get(self::ENDPOINT, [
                    'q' => $query,
                    'index' => 'address',
                    'limit' => self::MAX_RESULTS,
                ]);
        } catch (\Throwable $exception) {
            Log::warning('Address search failed', ['message' => $exception->getMessage()]);

            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $features = $response->json('features');

        if (! is_array($features)) {
            return [];
        }

        $results = [];

        foreach ($features as $feature) {
            $properties = is_array($feature) ? ($feature['properties'] ?? []) : [];

            // A town alone is no street address to fill the line with.
            if (! in_array($properties['type'] ?? null, ['housenumber', 'street', 'locality'], true)) {
                continue;
            }

            $line1 = trim((string) ($properties['name'] ?? ''));
            $postalCode = trim((string) ($properties['postcode'] ?? ''));
            $city = trim((string) ($properties['city'] ?? ''));

            if ($line1 === '' || $postalCode === '' || $city === '') {
                continue;
            }

            $results[] = [
                'label' => trim((string) ($properties['label'] ?? "{$line1} {$postalCode} {$city}")),
                'line1' => $line1,
                'postal_code' => $postalCode,
                'city' => $city,
            ];
        }

        return $results;
    }
}
