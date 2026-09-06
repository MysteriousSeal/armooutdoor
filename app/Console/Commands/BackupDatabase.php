<?php

namespace App\Console\Commands;

use App\Support\SiteBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * The scheduled archive: the database alone, taken every five minutes and
 * pruned to a day of history. The full archive, images and private files
 * included, stays a deliberate act in the back office.
 */
class BackupDatabase extends Command
{
    protected $signature = 'backup:database';

    protected $description = 'Archive the database alone and prune the scheduled trail';

    public function handle(): int
    {
        try {
            $name = SiteBackup::createDatabase();
        } catch (RuntimeException $exception) {
            // A failed backup must be loud in the log: the whole point of a
            // schedule is that nobody is watching it run.
            Log::error('The scheduled database backup failed.', ['message' => $exception->getMessage()]);
            $this->error('The backup could not be written: '.$exception->getMessage());

            return self::FAILURE;
        }

        $pruned = SiteBackup::pruneDatabaseArchives();

        $this->info(sprintf('Wrote %s%s.', $name, $pruned > 0 ? sprintf(', pruned %d older', $pruned) : ''));

        return self::SUCCESS;
    }
}
