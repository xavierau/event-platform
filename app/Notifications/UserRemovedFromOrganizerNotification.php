<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserRemovedFromOrganizerNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $organizerName,
        public readonly string $removedByName,
        public readonly ?string $reason = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->line("You have been removed from the organizer team: {$this->organizerName}")
            ->line("Removed by: {$this->removedByName}");

        if ($this->reason) {
            $message->line("Reason: {$this->reason}");
        }

        return $message
            ->line('If you believe this was a mistake, please contact the organizer owners.')
            ->line('Thank you for your contributions to the team.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'organizer_name' => $this->organizerName,
            'removed_by_name' => $this->removedByName,
            'reason' => $this->reason,
        ];
    }
}
