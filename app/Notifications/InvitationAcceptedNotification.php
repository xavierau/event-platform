<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationAcceptedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $organizerName,
        public readonly string $acceptedUserName,
        public readonly string $acceptedUserEmail,
        public readonly string $role
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->line("Great news! {$this->acceptedUserName} has accepted your invitation to join {$this->organizerName}.")
            ->line("User Details:")
            ->line("• Name: {$this->acceptedUserName}")
            ->line("• Email: {$this->acceptedUserEmail}")
            ->line("• Role: {$this->role}")
            ->action('View Team', url('/'))
            ->line('They are now an active member of your organizer team.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'organizer_name' => $this->organizerName,
            'accepted_user_name' => $this->acceptedUserName,
            'accepted_user_email' => $this->acceptedUserEmail,
            'role' => $this->role,
        ];
    }
}
