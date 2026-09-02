<?php

namespace App\Console\Commands;

use App\Support\CssMinifier;
use Illuminate\Console\Command;

/**
 * Run by deploy.sh on the server, after the reset and before the shop
 * comes back up: the stylesheets are minified in place, so the repository
 * keeps its readable sources and only the served copies are compacted.
 * versioned_asset() stamps by mtime, so the rewrite busts caches by itself.
 */
class MinifyCss extends Command
{
    protected $signature = 'css:minify';

    protected $description = 'Minify the public stylesheets in place';

    public function handle(): int
    {
        $before = 0;
        $after = 0;

        foreach (glob(public_path('css/*.css')) as $path) {
            $source = file_get_contents($path);
            $minified = CssMinifier::minify($source);

            $before += strlen($source);
            $after += strlen($minified);

            file_put_contents($path, $minified);
        }

        $this->info(sprintf(
            'Minified to %s from %s (saved %d%%).',
            number_format($after / 1024, 1).' KiB',
            number_format($before / 1024, 1).' KiB',
            $before > 0 ? (int) round(100 - $after / $before * 100) : 0,
        ));

        return self::SUCCESS;
    }
}
