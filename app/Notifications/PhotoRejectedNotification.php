<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PhotoRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ?string $reasonLabel,
        private readonly ?string $note,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Une de vos photos n’a pas été retenue')
            ->greeting("Bonjour {$notifiable->name},")
            ->line('Une photo que vous avez soumise n’a malheureusement pas été retenue par un modérateur.');

        if ($this->reasonLabel) {
            $message->line("Motif : {$this->reasonLabel}");
        }

        if ($this->note) {
            $message->line($this->note);
        }

        return $message;
    }
}
