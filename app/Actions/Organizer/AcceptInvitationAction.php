<?php

namespace App\Actions\Organizer;

use App\DataTransferObjects\Organizer\InviteUserData;
use App\DataTransferObjects\Organizer\OrganizerUserData;
use App\Models\Organizer;
use App\Models\User;
use App\Notifications\InvitationAcceptedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AcceptInvitationAction
{
    /**
     * Execute the invitation acceptance process.
     */
    public function execute(int $organizerId, int $userId): bool
    {
        return DB::transaction(function () use ($organizerId, $userId) {
            $organizer = $this->validateOrganizer($organizerId);
            $user = $this->validateUser($userId);
            $invitation = $this->validateInvitation($organizer, $user);

            $this->acceptInvitation($organizer, $user);
            $this->sendAcceptanceNotification($organizer, $user, $invitation);

            return true;
        });
    }

    /**
     * Execute with InviteUserData and return boolean result.
     */
    public function executeWithData(InviteUserData $inviteData, int $userId): bool
    {
        return $this->execute($inviteData->organizer_id, $userId);
    }

    /**
     * Execute with OrganizerUserData and return boolean result.
     */
    public function executeWithOrganizerUserData(OrganizerUserData $organizerUserData): bool
    {
        return $this->execute($organizerUserData->organizer_id, $organizerUserData->user_id);
    }

    /**
     * Execute and return the updated OrganizerUserData after acceptance.
     */
    public function executeAndReturnData(int $organizerId, int $userId): OrganizerUserData
    {
        $this->execute($organizerId, $userId);

        $organizer = Organizer::findOrFail($organizerId);
        $user = User::findOrFail($userId);

        $pivotRecord = $organizer->users()->where('user_id', $userId)->first();

        return OrganizerUserData::from([
            'organizer_id' => $organizerId,
            'user_id' => $userId,
            'role_in_organizer' => $pivotRecord->pivot->role_in_organizer,
            'permissions' => $pivotRecord->pivot->permissions ? json_decode($pivotRecord->pivot->permissions, true) : null,
            'joined_at' => $pivotRecord->pivot->joined_at ? Carbon::parse($pivotRecord->pivot->joined_at) : null,
            'is_active' => (bool) $pivotRecord->pivot->is_active,
            'invited_by' => $pivotRecord->pivot->invited_by,
            'invitation_accepted_at' => $pivotRecord->pivot->invitation_accepted_at ? Carbon::parse($pivotRecord->pivot->invitation_accepted_at) : null,
        ]);
    }

    /**
     * Validate that the organizer exists.
     */
    private function validateOrganizer(int $organizerId): Organizer
    {
        $organizer = Organizer::find($organizerId);

        if (!$organizer) {
            throw new InvalidArgumentException('Organizer not found');
        }

        return $organizer;
    }

    /**
     * Validate that the user exists.
     */
    private function validateUser(int $userId): User
    {
        $user = User::find($userId);

        if (!$user) {
            throw new InvalidArgumentException('User not found');
        }

        return $user;
    }

    /**
     * Validate that a pending invitation exists for the user.
     */
    private function validateInvitation(Organizer $organizer, User $user): object
    {
        $invitation = $organizer->users()
            ->where('user_id', $user->id)
            ->first();

        if (!$invitation) {
            throw new InvalidArgumentException('No pending invitation found for this user and organizer');
        }

        // Check if invitation is already accepted
        if ($invitation->pivot->invitation_accepted_at !== null) {
            throw new InvalidArgumentException('Invitation has already been accepted');
        }

        // Note: Expiration checking would be implemented here when metadata column is added
        // For now, we skip expiration validation

        // Validate that the inviter is still an active member
        $inviter = User::find($invitation->pivot->invited_by);
        if ($inviter) {
            $inviterMembership = $organizer->users()
                ->where('user_id', $inviter->id)
                ->where('is_active', true)
                ->first();

            if (!$inviterMembership) {
                throw new InvalidArgumentException('Invitation was made by an inactive user');
            }
        }

        return $invitation;
    }

    /**
     * Accept the invitation by updating the pivot record.
     */
    private function acceptInvitation(Organizer $organizer, User $user): void
    {
        $organizer->users()->updateExistingPivot($user->id, [
            'invitation_accepted_at' => now(),
            'is_active' => true, // Ensure user is active
            'updated_at' => now(),
        ]);
    }

    /**
     * Send acceptance notification to the inviter.
     */
    private function sendAcceptanceNotification(Organizer $organizer, User $acceptedUser, object $invitation): void
    {
        $inviter = User::find($invitation->pivot->invited_by);

        if ($inviter) {
            $organizerName = is_array($organizer->name)
                ? ($organizer->name['en'] ?? $organizer->name[array_key_first($organizer->name)] ?? 'Unknown Organizer')
                : $organizer->name;

            $notification = new InvitationAcceptedNotification(
                organizerName: $organizerName,
                acceptedUserName: $acceptedUser->name,
                acceptedUserEmail: $acceptedUser->email,
                role: $invitation->pivot->role_in_organizer
            );

            $inviter->notify($notification);
        }
    }
}
