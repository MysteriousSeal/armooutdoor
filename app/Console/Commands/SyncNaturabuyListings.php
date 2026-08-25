<?php

namespace App\Console\Commands;

use App\Services\Naturabuy\NaturabuySynchronizer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('naturabuy:sync {--prune : Delete local rows NaturaBuy no longer returns}')]
#[Description('Pull every NaturaBuy listing into the local table')]
class SyncNaturabuyListings extends Command
{
    public function handle(NaturabuySynchronizer $synchronizer): int
    {
        try {
            $result = $synchronizer->sync((bool) $this->option('prune'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(
            'Fetched '.$result['fetched'].' listings: '
            .$result['created'].' created, '.$result['updated'].' updated.'
        );

        if ($this->option('prune')) {
            $this->info('Pruned '.$result['deleted'].' listing(s) NaturaBuy no longer returns.');
        }

        return self::SUCCESS;
    }
}
