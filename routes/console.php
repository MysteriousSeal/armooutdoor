<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * The database, every five minutes.
 *
 * withoutOverlapping so a slow run is never doubled, runInBackground so the
 * minute's other work does not wait on the zip, and the trail pruned by the
 * command itself to a day of history.
 */
Schedule::command('backup:database')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();
