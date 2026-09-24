<?php

namespace App\Services\Naturabuy;

use App\Models\NaturabuyListing;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Counts the photos a listing shows on its public NaturaBuy page.
 *
 * Their API returns no photos at all, so the page is read instead. A
 * listing's photos live under a folder named after its own id, one file per
 * photo numbered on five digits, with resized copies under `thumbs/`:
 *
 *     /uploaded/20260428/14945316/00003_Cibles-….jpg
 *     /uploaded/20260428/14945316/thumbs/600h600f_00003_Cibles-….jpg
 *
 * The page also shows related listings and brand logos, which live under
 * other ids, so only this listing's numbers are counted, once each.
 */
class NaturabuyPhotoCounter
{
    /** Read again after this long. */
    public const RECHECK_AFTER_DAYS = 7;

    /** After a failed read, try again sooner than a week. */
    private const RETRY_AFTER_FAILURE_DAYS = 1;

    private const USER_AGENT = 'ArmoOutdoor/1.0 (+https://armooutdoor.fr)';

    /** The listing due next: never read first, then the one read longest ago. */
    public function nextDue(): ?NaturabuyListing
    {
        return NaturabuyListing::query()
            ->where('closed', false)
            ->whereNotNull('url')
            ->where(fn ($query) => $query
                ->whereNull('photos_checked_at')
                ->orWhere('photos_checked_at', '<=', now()->subDays(self::RECHECK_AFTER_DAYS)))
            ->orderByRaw('photos_checked_at is not null')
            ->orderBy('photos_checked_at')
            ->orderBy('id')
            ->first();
    }

    /**
     * Reads the listing's page and stores its photo count. Returns the count,
     * or null when the page could not be read; the listing is then tried
     * again a day later rather than every minute.
     */
    public function check(NaturabuyListing $listing): ?int
    {
        $url = $listing->publicUrl();

        try {
            $response = $url === null ? null : Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->timeout(20)
                ->get($url);
        } catch (ConnectionException $exception) {
            $response = null;
            Log::warning('NaturaBuy photo count: could not reach the page', ['listing' => $listing->naturabuy_id, 'error' => $exception->getMessage()]);
        }

        if ($response === null || ! $response->successful()) {
            if ($response !== null) {
                Log::warning('NaturaBuy photo count: page answered '.$response->status(), ['listing' => $listing->naturabuy_id]);
            }

            $listing->forceFill([
                'photos_checked_at' => now()->subDays(self::RECHECK_AFTER_DAYS - self::RETRY_AFTER_FAILURE_DAYS),
            ])->save();

            return null;
        }

        $count = self::countIn($response->body(), (int) $listing->naturabuy_id);

        $listing->forceFill(['photo_count' => $count, 'photos_checked_at' => now()])->save();

        return $count;
    }

    /** The number of distinct photos of listing `$id` referenced in `$html`. */
    public static function countIn(string $html, int $id): int
    {
        preg_match_all('#/uploaded/\d+/'.$id.'/(?:thumbs/[^/_"\']+_)?(\d{5})_#', $html, $matches);

        return count(array_unique($matches[1]));
    }
}
