<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteVisit;
use App\Support\DonutChart;
use App\Support\UserAgentParser;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    /** @var array<string, string> */
    private const RANGES = [
        'all' => 'All time',
        '30d' => 'Last 30 days',
        '7d' => 'Last 7 days',
        '24h' => 'Last 24 hours',
    ];

    /**
     * An IP address firing this many full-page-load requests inside
     * BURST_WINDOW_SECONDS is treated as automated traffic regardless of its
     * user agent — real browsing (and this site's infinite-scroll requests,
     * which are AJAX and never recorded here) can't sustain that rate.
     */
    private const BURST_THRESHOLD = 12;

    private const BURST_WINDOW_SECONDS = 60;

    public function index(Request $request): View
    {
        $range = $this->resolveRange($request->input('range'));
        $since = $this->sinceForRange($range);
        $hideBots = $request->input('bots') === 'hide';

        $burstyIps = $this->burstyIps($since);
        $breakdown = $this->buildCharts($since, $burstyIps, $range);

        $visitsQuery = SiteVisit::query()->with('user')->latest('created_at');
        $this->applyRange($visitsQuery, $since);

        // « Bot » n'est pas une colonne mais un verdict (signature d'agent +
        // rafales d'IP), déjà rendu ligne par ligne en construisant les
        // graphiques : le journal exclut ces mêmes lignes, pas une
        // approximation SQL du verdict.
        if ($hideBots && $breakdown['botVisitIds'] !== []) {
            $visitsQuery->whereNotIn('id', $breakdown['botVisitIds']);
        }

        $visits = $visitsQuery->paginate(50)->withQueryString();
        $ranges = self::RANGES;
        $activeNow = $this->countDistinctVisitors(Carbon::now()->subMinutes(2));
        $rangeVisitors = $this->countDistinctVisitors($since, $burstyIps);
        $burstThreshold = self::BURST_THRESHOLD;
        $burstWindowSeconds = self::BURST_WINDOW_SECONDS;

        return view('admin.analytics.index', [
            'visits' => $visits,
            'charts' => $breakdown['charts'],
            'series' => $breakdown['series'],
            'topPages' => $breakdown['topPages'],
            'topReferrers' => $breakdown['topReferrers'],
            'range' => $range,
            'ranges' => $ranges,
            'hideBots' => $hideBots,
            'activeNow' => $activeNow,
            'rangeVisitors' => $rangeVisitors,
            'burstyIps' => $burstyIps,
            'burstThreshold' => $burstThreshold,
            'burstWindowSeconds' => $burstWindowSeconds,
        ]);
    }

    /**
     * Polled from the admin nav every few seconds to keep the "active now"
     * chip live without a full page reload.
     */
    public function activeNow(): JsonResponse
    {
        $since = Carbon::now()->subMinutes(2);

        return response()->json($this->countDistinctVisitors($since, $this->burstyIps($since)));
    }

    /**
     * Distinct visitors since $since (or all time when null).
     * Bots are counted separately from human guests, combining user-agent
     * signature matching with the IP request-burst heuristic (see burstyIps()).
     *
     * @param  array<string, true>|null  $burstyIps  Pass a precomputed set to avoid recalculating it for the same range.
     * @return array{total: int, users: int, guests: int, bots: int}
     */
    private function countDistinctVisitors(?CarbonInterface $since, ?array $burstyIps = null): array
    {
        $burstyIps ??= $this->burstyIps($since);

        $query = SiteVisit::query()->select(['user_id', 'ip_address', 'user_agent']);
        $this->applyRange($query, $since);

        $userKeys = [];
        $guestKeys = [];
        $botKeys = [];

        foreach ($query->cursor() as $row) {
            $ip = $row->ip_address ?: 'unknown';
            $isBot = UserAgentParser::isBot($row->user_agent) || isset($burstyIps[$ip]);

            if ($isBot) {
                $botKeys['b:'.$ip.'|'.md5((string) $row->user_agent)] = true;

                continue;
            }

            if ($row->user_id) {
                $userKeys['u:'.$row->user_id] = true;
            } else {
                $guestKeys['g:'.$ip] = true;
            }
        }

        $users = count($userKeys);
        $guests = count($guestKeys);
        $bots = count($botKeys);

        return [
            'total' => $users + $guests + $bots,
            'users' => $users,
            'guests' => $guests,
            'bots' => $bots,
        ];
    }

    private function resolveRange(?string $range): string
    {
        // Default to 7d, not "All time" — buildCharts()/countDistinctVisitors()
        // pull every matching row into PHP, and an unbounded scan on every
        // plain page load is the main reason the page feels slow.
        return array_key_exists((string) $range, self::RANGES) ? (string) $range : '7d';
    }

    private function sinceForRange(string $range): ?CarbonInterface
    {
        return match ($range) {
            '24h' => Carbon::now()->subDay(),
            '7d' => Carbon::now()->subDays(7),
            '30d' => Carbon::now()->subDays(30),
            default => null,
        };
    }

    /**
     * @param  Builder<SiteVisit>  $query
     */
    private function applyRange(Builder $query, ?CarbonInterface $since): void
    {
        if ($since !== null) {
            $query->where('created_at', '>=', $since);
        }
    }

    /**
     * IP addresses that fired BURST_THRESHOLD+ page-load requests within any
     * BURST_WINDOW_SECONDS window in the given range — a behavioral signal
     * that catches automated traffic no matter what user agent it presents
     * (spoofed "real browser" UAs are common for scrapers and can't be caught
     * by signature matching alone).
     *
     * @return array<string, true>
     */
    private function burstyIps(?CarbonInterface $since): array
    {
        // Bursts are a short-term signal — cap the scan to the last 24h even
        // for the "All time" range, or this pulls and sorts the entire table
        // on every page load.
        $windowStart = Carbon::now()->subDay();
        $effectiveSince = ($since !== null && $since->greaterThan($windowStart)) ? $since : $windowStart;

        $query = SiteVisit::query()->select(['ip_address', 'created_at'])->whereNotNull('ip_address');
        $this->applyRange($query, $effectiveSince);

        $bursty = [];
        $currentIp = null;
        $window = [];

        foreach ($query->orderBy('ip_address')->orderBy('created_at')->cursor() as $row) {
            if ($row->ip_address !== $currentIp) {
                $currentIp = $row->ip_address;
                $window = [];
            }

            $window[] = $row->created_at->getTimestamp();

            while (count($window) > 1 && end($window) - $window[0] > self::BURST_WINDOW_SECONDS) {
                array_shift($window);
            }

            if (count($window) >= self::BURST_THRESHOLD) {
                $bursty[$currentIp] = true;
            }
        }

        return $bursty;
    }

    /**
     * Aggregate visits within the selected range: the donut charts, the
     * visits-over-time series (humans and bots apart) and the top pages and
     * referrers — one pass over the rows for the four of them.
     *
     * @param  array<string, true>  $burstyIps
     * @return array{charts: array<string, mixed>, series: list<array{label: string, humans: int, bots: int}>, topPages: list<array{path: string, count: int}>, topReferrers: list<array{host: string, count: int}>, botVisitIds: list<int>}
     */
    private function buildCharts(?CarbonInterface $since, array $burstyIps, string $range): array
    {
        $query = SiteVisit::query()->select(['id', 'user_id', 'user_agent', 'ip_address', 'country', 'created_at', 'path', 'referrer']);
        $this->applyRange($query, $since);
        $rows = $query->get();

        if ($rows->isEmpty()) {
            return ['charts' => [], 'series' => [], 'topPages' => [], 'topReferrers' => [], 'botVisitIds' => []];
        }

        // Un seau par heure sur 24 h, par jour sur 7 et 30 jours, par mois
        // au-delà : assez fin pour lire le rythme, jamais mille barres.
        $bucketFormat = match ($range) {
            '24h' => 'Y-m-d H',
            'all' => 'Y-m',
            default => 'Y-m-d',
        };
        $bucketLabel = match ($range) {
            '24h' => 'g A',
            'all' => 'M Y',
            default => 'M j',
        };
        $ownHost = (string) parse_url((string) config('app.url'), PHP_URL_HOST);

        $osCounts = [];
        $deviceCounts = [];
        $browserCounts = [];
        $countryCounts = [];
        $botNameCounts = [];
        $buckets = [];
        $pathCounts = [];
        $referrerCounts = [];
        $botVisitIds = [];

        foreach ($rows as $row) {
            $parsed = UserAgentParser::parse($row->user_agent);
            $isBot = $parsed['is_bot'] || isset($burstyIps[$row->ip_address ?: 'unknown']);

            $os = $isBot ? '—' : ($parsed['os'] === '—' ? 'Unknown' : $parsed['os']);
            $device = $isBot ? 'Bot' : $parsed['device'];
            $browser = $isBot && ! $parsed['is_bot'] ? 'Bot' : $parsed['browser'];
            $country = filled($row->country) ? (string) $row->country : 'Unknown';

            $osCounts[$os] = ($osCounts[$os] ?? 0) + 1;
            $deviceCounts[$device] = ($deviceCounts[$device] ?? 0) + 1;
            $browserCounts[$browser] = ($browserCounts[$browser] ?? 0) + 1;
            $countryCounts[$country] = ($countryCounts[$country] ?? 0) + 1;

            $bucket = $row->created_at->format($bucketFormat);
            $buckets[$bucket] ??= ['humans' => 0, 'bots' => 0];

            if ($isBot) {
                $botName = $parsed['is_bot'] ? $parsed['browser'] : 'High-volume IP';
                $botNameCounts[$botName] = ($botNameCounts[$botName] ?? 0) + 1;
                $buckets[$bucket]['bots']++;
                $botVisitIds[] = $row->id;
            } else {
                $buckets[$bucket]['humans']++;

                // Les palmarès ne comptent que les humains : un scraper qui
                // aspire le catalogue ne dit rien de ce qui intéresse.
                if (filled($row->path)) {
                    $pathCounts[$row->path] = ($pathCounts[$row->path] ?? 0) + 1;
                }

                $referrerHost = filled($row->referrer) ? (string) parse_url($row->referrer, PHP_URL_HOST) : '';
                $referrerHost = preg_replace('/^www\./', '', $referrerHost) ?? '';

                if ($referrerHost !== '' && $referrerHost !== preg_replace('/^www\./', '', $ownHost)) {
                    $referrerCounts[$referrerHost] = ($referrerCounts[$referrerHost] ?? 0) + 1;
                }
            }
        }

        ksort($buckets);
        $series = [];

        foreach ($buckets as $key => $counts) {
            $series[] = [
                'label' => Carbon::createFromFormat($bucketFormat, $key)->format($bucketLabel),
                'humans' => $counts['humans'],
                'bots' => $counts['bots'],
            ];
        }

        arsort($pathCounts);
        arsort($referrerCounts);

        $topPages = collect($pathCounts)->take(10)
            ->map(fn (int $count, string $path) => ['path' => $path, 'count' => $count])->values()->all();
        $topReferrers = collect($referrerCounts)->take(10)
            ->map(fn (int $count, string $host) => ['host' => $host, 'count' => $count])->values()->all();

        // Ni « users vs guests » ni « bot vs human » : les tuiles au-dessus
        // portent déjà ces deux répartitions en chiffres.
        $charts = [
            'country' => array_merge(
                ['title' => 'Country'],
                DonutChart::fromCounts($countryCounts),
            ),
            'os' => array_merge(
                ['title' => 'Operating system'],
                DonutChart::fromCounts($osCounts),
            ),
            'device' => array_merge(
                ['title' => 'Device'],
                DonutChart::fromCounts($deviceCounts),
            ),
            'browser' => array_merge(
                ['title' => 'Browser'],
                DonutChart::fromCounts($browserCounts),
            ),
            'botName' => array_merge(
                ['title' => 'Bot name'],
                DonutChart::fromCounts($botNameCounts),
            ),
        ];

        return ['charts' => $charts, 'series' => $series, 'topPages' => $topPages, 'topReferrers' => $topReferrers, 'botVisitIds' => $botVisitIds];
    }
}
