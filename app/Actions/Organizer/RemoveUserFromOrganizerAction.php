<?php

namespace App\Actions\Organizer;

use App\Exceptions\UnauthorizedOperationException;
use App\Models\Organizer;
use App\Models\User;
use App\Notifications\TeamMemberRemovedNotification;
use App\Notifications\UserRemovedFromOrganizerNotification;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RemoveUserFromOrganizerAction
{
    /**
     * Execute the user removal process.
     */
    public function execute(int $organizerId, int $userToRemoveId, int $removedBy): bool
    {
        return DB::transaction(function () use ($organizerId, $userToRemoveId, $removedBy) {
            $organizer = $this->validateOrganizer($organizerId);
            $userToRemove = $this->validateUser($userToRemoveId, 'User to remove not found');
            $remover = $this->validateUser($removedBy, 'User performing removal not found');

            $this->validateMemberships($organizer, $userToRemove, $remover);
            $this->validateRemovalPermissions($organizer, $userToRemove, $remover);
            $this->validateLastOwnerRestriction($organizer, $userToRemove);

            $this->performRemoval($organizer, $userToRemove, $remover);
            $this->sendNotifications($organizer, $userToRemove, $remover);

            return true;
        });
    }

    /**
     * Execute removal with optional reason.
     */
    public function executeWithReason(int $organizerId, int $userToRemoveId, int $removedBy, ?string $reason = null): bool
    {
        return DB::transaction(function () use ($organizerId, $userToRemoveId, $removedBy, $reason) {
            $organizer = $this->validateOrganizer($organizerId);
            $userToRemove = $this->validateUser($userToRemoveId, 'User to remove not found');
            $remover = $this->validateUser($removedBy, 'User performing removal not found');

            $this->validateMemberships($organizer, $userToRemove, $remover);
            $this->validateRemovalPermissions($organizer, $userToRemove, $remover);
            $this->validateLastOwnerRestriction($organizer, $userToRemove);

            $this->performRemoval($organizer, $userToRemove, $remover);
            $this->sendNotifications($organizer, $userToRemove, $remover, $reason);

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
     * Validate that a user exists.
     */
    private function validateUser(int $userId, string $errorMessage): User
    {
        $user = User::find($userId);

        if (!$user) {
            throw new InvalidArgumentException($errorMessage);
        }

        return $user;
    }

    /**
     * Validate that both users are members of the organizer.
     */
    private function validateMemberships(Organizer $organizer, User $userToRemove, User $remover): void
    {
        // Check if user to remove is a member
        $targetMembership = $organizer->users()->where('user_id', $userToRemove->id)->first();
        if (!$targetMembership) {
            throw new InvalidArgumentException('User is not a member of this organizer');
        }

        // Check if user is already inactive
        if (!$targetMembership->pivot->is_active) {
            throw new InvalidArgumentException('User is already inactive in this organizer');
        }

        // Check if remover is a member (unless removing themselves)
        if ($remover->id !== $userToRemove->id) {
            $removerMembership = $organizer->users()
                ->where('user_id', $remover->id)
                ->where('is_active', true)
                ->first();

            if (!$removerMembership) {
                throw new UnauthorizedOperationException('User performing removal is not a member of this organizer');
            }
        }
    }

    /**
     * Validate removal permissions based on roles.
     */
    private function validateRemovalPermissions(Organizer $organizer, User $userToRemove, User $remover): void
    {
        // Users can always remove themselves (unless they're the last owner)
        if ($remover->id === $userToRemove->id) {
            return;
        }

        $removerMembership = $organizer->users()->where('user_id', $remover->id)->first();
        $targetMembership = $organizer->users()->where('user_id', $userToRemove->id)->first();

        $removerRole = $removerMembership->pivot->role_in_organizer;
        $targetRole = $targetMembership->pivot->role_in_organizer;

        // Only owners and managers can remove other users
        if (!in_array($removerRole, ['owner', 'manager'])) {
            throw new UnauthorizedOperationException('Insufficient permissions to remove users');
        }

        // Managers cannot remove owners or other managers
        if ($removerRole === 'manager' && in_array($targetRole, ['owner', 'manager'])) {
            throw new UnauthorizedOperationException('Insufficient permissions to remove this user');
        }
    }

    /**
     * Validate that we're not removing the last owner.
     */
    private function validateLastOwnerRestriction(Organizer $organizer, User $userToRemove): void
    {
        $targetMembership = $organizer->users()->where('user_id', $userToRemove->id)->first();

        if ($targetMembership->pivot->role_in_organizer === 'owner') {
            $activeOwnerCount = $organizer->users()
                ->where('role_in_organizer', 'owner')
                ->where('is_active', true)
                ->count();

            if ($activeOwnerCount <= 1) {
                throw new UnauthorizedOperationException('Cannot remove the last owner of the organizer');
            }
        }
    }

    /**
     * Perform the actual removal by updating the pivot record.
     */
    private function performRemoval(Organizer $organizer, User $userToRemove, User $remover): void
    {
        $organizer->users()->updateExistingPivot($userToRemove->id, [
            'is_active' => false,
            'updated_at' => now(),
        ]);
    }

    /**
     * Send removal notifications.
     */
    private function sendNotifications(Organizer $organizer, User $removedUser, User $remover, ?string $reason = null): void
    {
        $organizerName = is_array($organizer->name)
            ? ($organizer->name['en'] ?? $organizer->name[array_key_first($organizer->name)] ?? 'Unknown Organizer')
            : $organizer->name;

        $removedMembership = $organizer->users()->where('user_id', $removedUser->id)->first();
        $userRole = $removedMembership->pivot->role_in_organizer;

        // Notify the removed user
        $removedUser->notify(new UserRemovedFromOrganizerNotification(
            organizerName: $organizerName,
            removedByName: $remover->name,
            reason: $reason
        ));

        // Notify other owners and managers (but not the remover)
        $adminsToNotify = $organizer->users()
            ->whereIn('role_in_organizer', ['owner', 'manager'])
            ->where('is_active', true)
            ->where('user_id', '!=', $remover->id)
            ->get();

        foreach ($adminsToNotify as $admin) {
            $admin->notify(new TeamMemberRemovedNotification(
                organizerName: $organizerName,
                removedUserName: $removedUser->name,
                removedUserEmail: $removedUser->email,
                removedByName: $remover->name,
                userRole: $userRole,
                reason: $reason
            ));
        }
    }
}
