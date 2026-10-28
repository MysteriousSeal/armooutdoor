<?php

namespace App\Console\Commands;

use App\Models\EbayConnection;
use App\Services\Ebay\EbayStockPusher;
use App\Services\Ebay\EbaySynchronizer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('ebay:sync-stock')]
#[Description('Read the eBay listings, then send the shop stock to those that differ')]
class SyncEbayStock extends Command
{
    public function handle(EbaySynchronizer $synchronizer, EbayStockPusher $pusher): int
    {
        $connection = EbayConnection::current();

        // Scheduled before eBay is connected: nothing to do, and no failure to report.
        if ($connection === null || $connection->hasExpired()) {
            $this->info('eBay is not connected; nothing sent.');

            return self::SUCCESS;
        }

        try {
            $synchronizer->sync();
            $result = $pusher->push();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(EbayStockPusher::summary($result));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
