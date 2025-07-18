<?php

namespace App\Actions\Organizer;

use App\DataTransferObjects\Organizer\InviteUserData;
use App\Enums\OrganizerRoleEnum;
use App\Exceptions\UnauthorizedOperationException;
use App\Models\Organizer;
use App\Models\User;
use App\Notifications\OrganizerInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

class InviteUserToOrganizerAction
{
    /**
     * Execute the invitation process.
     */
    public function execute(InviteUserData $inviteData): bool
    {
        return DB::transaction(function () use ($inviteData) {
            // Validate organizer exists
            $organizer = $this->validateOrganizer($inviteData->organizer_id);

            // Validate inviter exists and has permission
            $inviter = $this->validateInviter($inviteData->invited_by, $organizer);

            // Get or create the user
            $user = $this->getOrCreateUser($inviteData);

            // Check if user is already a member (unless they're inactive)
            $this->validateMembership($organizer, $user);

            // Create or update organizer-user relationship
            $this->createOrganizerUserRelationship($organizer, $user, $inviteData);

            // Send invitation notification
            $this->sendInvitationNotification($user, $organizer, $inviter, $inviteData);

            return true;
        });
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
     * Validate that the inviter exists and has permission to invite.
     */
    private function validateInviter(int $inviterId, Organizer $organizer): User
    {
        $inviter = User::find($inviterId);

        if (!$inviter) {
            throw new InvalidArgumentException('Inviter not found');
        }

        // Check if inviter is a member of the organizer
        $inviterMembership = $organizer->users()
            ->where('user_id', $inviterId)
            ->where('is_active', true)
            ->first();

        if (!$inviterMembership) {
            throw new UnauthorizedOperationException('User is not a member of this organizer');
        }

        // Check if inviter has permission to invite (owners, managers can invite)
        $inviterRole = OrganizerRoleEnum::tryFrom($inviterMembership->pivot->role_in_organizer);

        if (!$inviterRole || !$this->canInviteUsers($inviterRole)) {
            throw new UnauthorizedOperationException('User does not have permission to invite others to this organizer');
        }

        return $inviter;
    }

    /**
     * Check if a role can invite users.
     */
    private function canInviteUsers(OrganizerRoleEnum $role): bool
    {
        return in_array($role, [
            OrganizerRoleEnum::OWNER,
            OrganizerRoleEnum::MANAGER,
        ]);
    }

    /**
     * Get existing user or create new user based on invitation data.
     */
    private function getOrCreateUser(InviteUserData $inviteData): User
    {
        // If existing user ID is provided, use that
        if ($inviteData->existing_user_id) {
            $user = User::find($inviteData->existing_user_id);
            if (!$user) {
                throw new InvalidArgumentException("User with ID {$inviteData->existing_user_id} not found");
            }
            return $user;
        }

        // Check if a user with this email already exists
        $existingUser = User::where('email', $inviteData->email)->first();
        if ($existingUser) {
            // Email exists but not provided as existing user - handle gracefully
            return $existingUser;
        }

        // Create new user
        return $this->createNewUser($inviteData->email);
    }

    /**
     * Create a new user with default settings.
     */
    private function createNewUser(string $email): User
    {
        return User::create([
            'email' => $email,
            'name' => $this->generateDefaultName($email),
            'password' => Hash::make(Str::random(12)), // Random password - user will reset
            'email_verified_at' => null, // Will verify when accepting invitation
        ]);
    }

    /**
     * Generate a default name from email.
     */
    private function generateDefaultName(string $email): string
    {
        $localPart = explode('@', $email)[0];
        return ucwords(str_replace(['.', '_', '-'], ' ', $localPart));
    }

    /**
     * Validate that the user isn't already an active member.
     */
    private function validateMembership(Organizer $organizer, User $user): void
    {
        $existingMembership = $organizer->users()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if ($existingMembership) {
            throw new InvalidArgumentException('User is already a member of this organizer');
        }
    }

    /**
     * Create or update the organizer-user relationship.
     */
    private function createOrganizerUserRelationship(
        Organizer $organizer,
        User $user,
        InviteUserData $inviteData
    ): void {
        // Check if there's an existing inactive relationship
        $existingRelationship = $organizer->users()
            ->where('user_id', $user->id)
            ->first();

        if ($existingRelationship) {
            // Update existing relationship
            $organizer->users()->updateExistingPivot($user->id, [
                'role_in_organizer' => $inviteData->role_in_organizer,
                'permissions' => $inviteData->custom_permissions ? json_encode($inviteData->custom_permissions) : null,
                'is_active' => true,
                'invited_by' => $inviteData->invited_by,
                'joined_at' => now(),
                'invitation_accepted_at' => null, // Reset acceptance status
                'updated_at' => now(),
            ]);
        } else {
            // Create new relationship
            $organizer->users()->attach($user->id, [
                'role_in_organizer' => $inviteData->role_in_organizer,
                'permissions' => $inviteData->custom_permissions ? json_encode($inviteData->custom_permissions) : null,
                'is_active' => true,
                'invited_by' => $inviteData->invited_by,
                'joined_at' => now(),
                'invitation_accepted_at' => null, // Pending acceptance
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Send invitation notification to the user.
     */
    private function sendInvitationNotification(
        User $user,
        Organizer $organizer,
        User $inviter,
        InviteUserData $inviteData
    ): void {
        $organizerName = is_array($organizer->name)
            ? ($organizer->name['en'] ?? $organizer->name[array_key_first($organizer->name)] ?? 'Unknown Organizer')
            : $organizer->name;

        $notification = new OrganizerInvitationNotification(
            organizerName: $organizerName,
            role: $inviteData->role_in_organizer,
            inviterName: $inviter->name,
            customMessage: $inviteData->invitation_message
        );

        $user->notify($notification);
    }
}
