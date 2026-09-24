<?php

namespace App\Console\Commands;

use App\Models\NaturabuyListing;
use App\Services\Naturabuy\NaturabuyPhotoCounter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Reads one NaturaBuy listing page and stores how many photos it shows.
 * Scheduled every minute, so the site is read one page at a time, and a
 * listing is not read again for a week.
 */
#[Signature('naturabuy:count-photos {--listing= : NaturaBuy id of a listing to read now, due or not}')]
#[Description('Count the photos of the next NaturaBuy listing due for a check')]
class CountNaturabuyPhotos extends Command
{
    public function handle(NaturabuyPhotoCounter $counter): int
    {
        $listing = $this->option('listing')
            ? NaturabuyListing::query()->where('naturabuy_id', $this->option('listing'))->first()
            : $counter->nextDue();

        if ($listing === null) {
            $this->info($this->option('listing') ? 'No such listing.' : 'Every open listing was checked in the last '.NaturabuyPhotoCounter::RECHECK_AFTER_DAYS.' days.');

            return self::SUCCESS;
        }

        $count = $counter->check($listing);

        $this->info($count === null
            ? "Could not read {$listing->naturabuy_id}; it will be tried again tomorrow."
            : "{$listing->naturabuy_id}: {$count} photo(s).");

        return self::SUCCESS;
    }
}
