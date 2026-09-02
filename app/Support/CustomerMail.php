<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Sends a notice to a customer — an account holder through their account,
 * a guest at a bare address. The counterpart of AdminMail, with the same
 * rules: deferred past the response, an outage logs and never breaks the
 * action that earned the email.
 */
class CustomerMail
{
    /**
     * @param  User|string  $recipient  The account, or a guest's address.
     * @param  array<string, mixed>  $context
     */
    public static function notify(User|string $recipient, Notification $notification, string $failureMessage, array $context = []): void
    {
        DeferredMail::send($failureMessage, $context,
            fn () => $recipient instanceof User
                ? $recipient->notify($notification)
                : NotificationFacade::route('mail', $recipient)->notify($notification));
    }
}
