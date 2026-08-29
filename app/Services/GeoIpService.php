<?php

namespace App\Services;

use App\Support\UserAgentParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Resolve country/city for a request IP.
 * Prefers CDN headers (Cloudflare / CloudFront), then a short cached lookup.
 */
class GeoIpService
{
    /**
     * @return array{country: ?string, city: ?string}
     */
    public function resolve(Request $request): array
    {
        $fromHeaders = $this->fromHeaders($request);

        if ($fromHeaders['country'] !== null || $fromHeaders['city'] !== null) {
            return $fromHeaders;
        }

        $ip = $request->ip();

        if (! $ip || $this->isPrivateIp($ip)) {
            return [
                'country' => $this->isPrivateIp((string) $ip) ? 'Local' : null,
                'city' => null,
            ];
        }

        // Scrapers rotating through fresh IPs are exactly what would flood
        // the lookup with cache misses, and their location is worthless —
        // don't spend an HTTP call (and a worker) on them.
        if (UserAgentParser::isBot($request->userAgent())) {
            return ['country' => null, 'city' => null];
        }

        return $this->lookupCached($ip);
    }

    /**
     * @return array{country: ?string, city: ?string}
     */
    private function fromHeaders(Request $request): array
    {
        $country = $request->header('CF-IPCountry')
            ?: $request->header('CloudFront-Viewer-Country')
            ?: $request->header('X-AppEngine-Country');

        $city = $request->header('CF-IPCity')
            ?: $request->header('CloudFront-Viewer-City')
            ?: $request->header('X-AppEngine-City');

        if (is_string($country)) {
            $country = trim($country);
            if ($country === '' || strtoupper($country) === 'XX') {
                $country = null;
            } elseif (strlen($country) === 2) {
                $country = strtoupper($country);
            }
        } else {
            $country = null;
        }

        if (is_string($city)) {
            $city = trim(rawurldecode($city));
            if ($city === '' || $city === '?') {
                $city = null;
            }
        } else {
            $city = null;
        }

        return [
            'country' => $country !== null ? mb_substr($country, 0, 100) : null,
            'city' => $city !== null ? mb_substr($city, 0, 100) : null,
        ];
    }

    /**
     * @return array{country: ?string, city: ?string}
     */
    private function lookupCached(string $ip): array
    {
        try {
            /** @var array{country: ?string, city: ?string} $result */
            $result = Cache::remember("geoip:{$ip}", now()->addDay(), function () use ($ip) {
                return $this->lookupIpApi($ip);
            });

            return $result;
        } catch (Throwable $e) {
            Log::debug('GeoIP cache/lookup failed', ['ip' => $ip, 'error' => $e->getMessage()]);

            return ['country' => null, 'city' => null];
        }
    }

    /**
     * Free ip-api.com endpoint (non-commercial). Cached per IP for 24h.
     *
     * @return array{country: ?string, city: ?string}
     */
    private function lookupIpApi(string $ip): array
    {
        try {
            $response = Http::timeout(0.75)
                ->acceptJson()
                ->get("http://ip-api.com/json/{$ip}", [
                    'fields' => 'status,country,city',
                ]);

            if (! $response->successful()) {
                return ['country' => null, 'city' => null];
            }

            $data = $response->json();

            if (($data['status'] ?? null) !== 'success') {
                return ['country' => null, 'city' => null];
            }

            $country = isset($data['country']) ? trim((string) $data['country']) : '';
            $city = isset($data['city']) ? trim((string) $data['city']) : '';

            return [
                'country' => $country !== '' ? mb_substr($country, 0, 100) : null,
                'city' => $city !== '' ? mb_substr($city, 0, 100) : null,
            ];
        } catch (Throwable $e) {
            Log::debug('GeoIP HTTP lookup failed', ['ip' => $ip, 'error' => $e->getMessage()]);

            return ['country' => null, 'city' => null];
        }
    }

    private function isPrivateIp(string $ip): bool
    {
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return true;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }
}
