<?php

namespace App\Notifications;

use App\Models\Photo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class PhotoPublishedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly Photo $photo,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre photo a été publiée')
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Bonne nouvelle : « {$this->photo->publicLabel()} » a été validée par un modérateur et est maintenant visible sur fotométro.")
            ->action('Voir la photo', route('photos.show', $this->photo));
    }
}
