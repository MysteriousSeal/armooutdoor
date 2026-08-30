<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * How this application sends every email it owes to a web request: after the
 * response has been flushed, and behind a guard. The visitor never waits on
 * SMTP, and a mail outage is a log line, never a broken page — sent inline
 * rather than queued because no worker is supervised yet, and a queued mail
 * with no worker would sit unnoticed in the jobs table.
 */
class DeferredMail
{
    /**
     * @param  string  $failureMessage  What the log should say if the send fails.
     * @param  array<string, mixed>  $context  What the log should point at.
     */
    public static function send(string $failureMessage, array $context, Closure $send): void
    {
        app()->terminating(function () use ($failureMessage, $context, $send): void {
            try {
                $send();
            } catch (Throwable $exception) {
                Log::error($failureMessage, [...$context, 'exception' => $exception->getMessage()]);
            }
        });
    }
}
