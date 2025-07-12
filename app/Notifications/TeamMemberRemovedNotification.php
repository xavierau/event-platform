<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamMemberRemovedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $organizerName,
        public readonly string $removedUserName,
        public readonly string $removedUserEmail,
        public readonly string $removedByName,
        public readonly string $userRole,
        public readonly ?string $reason = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->line("A team member has been removed from {$this->organizerName}")
            ->line("User Details:")
            ->line("• Name: {$this->removedUserName}")
            ->line("• Email: {$this->removedUserEmail}")
            ->line("• Role: {$this->userRole}")
            ->line("• Removed by: {$this->removedByName}");

        if ($this->reason) {
            $message->line("• Reason: {$this->reason}");
        }

        return $message
            ->action('Manage Team', url('/'))
            ->line('This is a notification for team administrators.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'organizer_name' => $this->organizerName,
            'removed_user_name' => $this->removedUserName,
            'removed_user_email' => $this->removedUserEmail,
            'removed_by_name' => $this->removedByName,
            'user_role' => $this->userRole,
            'reason' => $this->reason,
        ];
    }
}
