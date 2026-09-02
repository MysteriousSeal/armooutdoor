<?php

namespace App\Notifications;

use App\Models\IdentityDocument;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the shop a proof of age arrived — a restricted order may be
 * sitting held on this review, and nothing else says so out loud.
 */
class AdminIdentityDocumentSubmitted extends Notification
{
    private const KIND_LABELS = [
        'id_card' => "Carte d'identité",
        'passport' => 'Passeport',
        'driving_licence' => 'Permis de conduire',
    ];

    public function __construct(private readonly IdentityDocument $document) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $user = $this->document->user;

        return (new MailMessage)
            ->subject('Proof of age received — '.$user->name)
            ->line('**'.$user->name.'** ('.$user->email.') submitted a '.(self::KIND_LABELS[$this->document->kind] ?? $this->document->kind).'.')
            ->line('A restricted order may be waiting on this review; the document is deleted the moment a verdict is recorded.')
            ->action('Review the document', route('admin.documents.index'))
            ->line('Reviewing requires an owner account.');
    }
}
