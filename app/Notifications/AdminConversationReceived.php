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
            ->subject('New message from '.$this->conversation->name.' — '.$this->conversation->subject)
            ->line('**'.$this->conversation->name.'** ('.$this->conversation->email.') wrote:')
            ->line('« '.Str::limit(trim((string) $lastMessage?->body), 300).' »')
            ->action('Open the conversation', route('admin.conversations.show', $this->conversation))
            ->line('Replying from the admin marks it read; further messages on this thread will not email again until it is.');
    }
}
