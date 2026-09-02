<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The first email a customer receives: what their new account actually
 * does for them, and where to write when something is unclear. Warm and
 * practical — no promise the shop does not make elsewhere.
 */
class Welcome extends Notification
{
    public function __construct(private readonly User $user) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('store.welcome_subject'))
            ->markdown('emails.welcome', ['user' => $this->user]);
    }
}
