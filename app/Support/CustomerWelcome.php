<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\AdminCustomerRegistered;
use App\Notifications\Welcome;
use Illuminate\Auth\Events\Registered;

/**
 * What follows a new customer account, whichever door it came through: the
 * register form or Google. Kept in one place so the two can't drift apart.
 */
class CustomerWelcome
{
    public static function send(User $user): void
    {
        event(new Registered($user));

        AdminMail::notify(
            new AdminCustomerRegistered($user),
            'Could not email the new-customer notice.',
            ['user_id' => $user->id],
        );

        CustomerMail::notify($user, new Welcome($user),
            'Could not email the welcome.', ['user_id' => $user->id]);
    }
}
