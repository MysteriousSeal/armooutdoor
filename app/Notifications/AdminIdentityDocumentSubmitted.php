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
            ->subject('Preuve de majorité reçue — '.$user->name)
            ->line('**'.$user->name.'** ('.$user->email.') a envoyé : '.(self::KIND_LABELS[$this->document->kind] ?? $this->document->kind).'.')
            ->line("Une commande réservée aux majeurs attend peut-être cette vérification ; le document est supprimé dès que le verdict est enregistré.")
            ->action('Vérifier le document', route('admin.documents.index'))
            ->line('La vérification demande un compte propriétaire.');
    }
}
