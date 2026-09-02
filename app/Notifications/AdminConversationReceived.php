<?php

namespace App\Notifications;

use App\Models\Conversation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Tells the shop a conversation is waiting on it — sent when a customer
 * message turns a thread unread, not on every message, so a burst of
 * follow-ups is one email.
 */
class AdminConversationReceived extends Notification
{
    public function __construct(private readonly Conversation $conversation) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lastMessage = $this->conversation->messages()->latest('id')->first();

        return (new MailMessage)
            ->subject('Nouveau message de '.$this->conversation->name.' — '.$this->conversation->subject)
            ->line('**'.$this->conversation->name.'** ('.$this->conversation->email.') écrit :')
            ->line('« '.Str::limit(trim((string) $lastMessage?->body), 300).' »')
            ->action('Ouvrir la conversation', route('admin.conversations.show', $this->conversation))
            ->line("Répondre depuis l'admin la marque lue ; les messages suivants sur ce fil n'enverront pas d'autre e-mail tant qu'elle ne l'est pas.");
    }
}
