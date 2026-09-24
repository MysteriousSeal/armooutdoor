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
 * withoutOverlapping so a slow run is never doubled - with an expiry, or a
 * run killed mid-zip would hold its lock for a day and quietly stop every
 * backup behind it - runInBackground so the minute's other work does not
 * wait, and the trail pruned by the command itself to a day of history.
 */
Schedule::command('backup:database')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->runInBackground();

/*
 * One NaturaBuy listing page a minute, for its photo count: their API does
 * not give it. Each listing is read again after a week.
 */
Schedule::command('naturabuy:count-photos')
    ->everyMinute()
    ->withoutOverlapping(5);
