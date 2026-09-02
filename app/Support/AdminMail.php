<?php

namespace App\Support;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Sends a notice to the shop's own inbox — the address that wants to know
 * something needs a human. Empty address: nobody to tell, silently skip.
 * Deferred and guarded like every mail this application owes a request.
 */
class AdminMail
{
    /**
     * @param  array<string, mixed>  $context
     * @param  string|null  $address  A dedicated address — the order notices
     *                                keep their own knob — or null for the
     *                                shared admin one.
     */
    public static function notify(Notification $notification, string $failureMessage, array $context = [], ?string $address = null): void
    {
        $address ??= (string) config('shop.admin_notification_email');

        if ($address === '') {
            return;
        }

        DeferredMail::send($failureMessage, $context,
            fn () => NotificationFacade::route('mail', $address)->notify($notification));
    }
}
