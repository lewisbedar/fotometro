<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserAccountRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ?string $reason) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Votre inscription fotométro n’a pas été retenue')
            ->greeting("Bonjour {$notifiable->name},")
            ->line('Votre inscription n’a malheureusement pas été retenue par un administrateur.');

        if ($this->reason) {
            $message->line("Motif : {$this->reason}");
        }

        return $message;
    }
}
