<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizerInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $organizerName,
        public readonly string $role,
        public readonly string $inviterName,
        public readonly ?string $customMessage = null,
        public readonly ?string $invitationUrl = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->line("You have been invited to join {$this->organizerName} as a {$this->role}.")
            ->line("Invited by: {$this->inviterName}")
            ->action('Accept Invitation', $this->invitationUrl ?? url('/'))
            ->line('Thank you for using our application!');

        if ($this->customMessage) {
            $message->line($this->customMessage);
        }

        return $message;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'organizer_name' => $this->organizerName,
            'role' => $this->role,
            'inviter_name' => $this->inviterName,
            'custom_message' => $this->customMessage,
            'invitation_url' => $this->invitationUrl,
        ];
    }
}
