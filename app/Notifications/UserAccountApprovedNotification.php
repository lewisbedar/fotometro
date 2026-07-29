<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserAccountApprovedNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre compte fotométro a été approuvé')
            ->greeting("Bonjour {$notifiable->name},")
            ->line('Votre compte a été approuvé par un administrateur.')
            ->action('Se connecter', route('login'))
            ->line('Vous pouvez désormais vous connecter et publier vos photographies.');
    }
}
