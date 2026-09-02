<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the shop a customer just created an account. Informational — it
 * waits on nobody — but a small shop likes to know who walked in.
 */
class AdminCustomerRegistered extends Notification
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
            ->subject('Nouveau compte client — '.$this->user->name)
            ->line('**'.$this->user->name.'** ('.$this->user->email.') vient de créer un compte.')
            ->action('Voir la fiche client', route('admin.customers.show', $this->user));
    }
}
