<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamMemberRoleChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $organizerName,
        public readonly string $userName,
        public readonly string $userEmail,
        public readonly string $oldRole,
        public readonly string $newRole,
        public readonly string $updatedByName,
        public readonly ?array $customPermissions = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->line("A team member's role has been updated in {$this->organizerName}")
            ->line("User Details:")
            ->line("• Name: {$this->userName}")
            ->line("• Email: {$this->userEmail}")
            ->line("Role Change:")
            ->line("• Previous Role: {$this->oldRole}")
            ->line("• New Role: {$this->newRole}")
            ->line("• Updated by: {$this->updatedByName}");

        if ($this->customPermissions && count($this->customPermissions) > 0) {
            $message->line("• Custom Permissions: " . implode(', ', $this->customPermissions));
        }

        return $message
            ->action('Manage Team', url('/'))
            ->line('This is a notification for team administrators.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'organizer_name' => $this->organizerName,
            'user_name' => $this->userName,
            'user_email' => $this->userEmail,
            'old_role' => $this->oldRole,
            'new_role' => $this->newRole,
            'updated_by_name' => $this->updatedByName,
            'custom_permissions' => $this->customPermissions,
        ];
    }
}
