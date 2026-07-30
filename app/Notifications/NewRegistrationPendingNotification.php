<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewRegistrationPendingNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly User $registrant) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nouvelle inscription en attente sur fotométro')
            ->greeting("Bonjour {$notifiable->name},")
            ->line("{$this->registrant->name} ({$this->registrant->email}) vient de créer un compte et attend une validation.")
            ->action('Voir les comptes en attente', route('admin.users.index', ['status' => 'pending']));
    }
}
