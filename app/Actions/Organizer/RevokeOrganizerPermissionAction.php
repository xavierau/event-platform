<?php

namespace App\Actions\Organizer;

use App\Enums\OrganizerPermissionEnum;
use App\Exceptions\UnauthorizedOperationException;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RevokeOrganizerPermissionAction
{
    /**
     * Revoke a single permission from a user.
     */
    public function execute(int $organizerId, int $userId, string $permission, int $revokedBy): bool
    {
        return $this->executeMultiple($organizerId, $userId, [$permission], $revokedBy);
    }

    /**
     * Revoke multiple permissions from a user.
     */
    public function executeMultiple(int $organizerId, int $userId, array $permissions, int $revokedBy): bool
    {
        return DB::transaction(function () use ($organizerId, $userId, $permissions, $revokedBy) {
            $organizer = $this->validateOrganizer($organizerId);
            $user = $this->validateUser($userId, 'User not found');
            $revokingUser = $this->validateUser($revokedBy, 'User performing revoke not found');

            $this->validatePermissions($permissions);
            $this->validateMemberships($organizer, $user, $revokingUser);
            $this->validateRevokePermissions($organizer, $revokingUser);

            $this->performPermissionRevoke($organizer, $user, $permissions);

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
     * Validate that all permissions are valid.
     */
    private function validatePermissions(array $permissions): void
    {
        $validPermissions = OrganizerPermissionEnum::all();

        foreach ($permissions as $permission) {
            if (!in_array($permission, $validPermissions)) {
                throw new InvalidArgumentException('Invalid permission provided: ' . $permission);
            }
        }
    }

    /**
     * Validate that both users are members of the organizer.
     */
    private function validateMemberships(Organizer $organizer, User $user, User $revokingUser): void
    {
        // Check if target user is a member and active
        $userMembership = $organizer->users()->where('user_id', $user->id)->first();
        if (!$userMembership) {
            throw new InvalidArgumentException('User is not a member of this organizer');
        }

        if (!$userMembership->pivot->is_active) {
            throw new InvalidArgumentException('Cannot revoke permissions from inactive user');
        }

        // Check if revoking user is a member and active
        $revokingUserMembership = $organizer->users()
            ->where('user_id', $revokingUser->id)
            ->where('is_active', true)
            ->first();

        if (!$revokingUserMembership) {
            throw new UnauthorizedOperationException('User performing revoke is not a member of this organizer');
        }
    }

    /**
     * Validate that the revoking user has permissions to revoke permissions.
     */
    private function validateRevokePermissions(Organizer $organizer, User $revokingUser): void
    {
        $revokingUserMembership = $organizer->users()->where('user_id', $revokingUser->id)->first();
        $revokingUserRole = $revokingUserMembership->pivot->role_in_organizer;

        // Only owners and managers can revoke permissions
        if (!in_array($revokingUserRole, ['owner', 'manager'])) {
            throw new UnauthorizedOperationException('Insufficient permissions to revoke permissions');
        }
    }

    /**
     * Perform the actual permission revoke operation.
     */
    private function performPermissionRevoke(Organizer $organizer, User $user, array $permissionsToRevoke): void
    {
        $currentMembership = $organizer->users()->where('user_id', $user->id)->first();
        $currentPermissions = $currentMembership->pivot->permissions
            ? json_decode($currentMembership->pivot->permissions, true)
            : [];

        // Remove the specified permissions from the current permissions
        $updatedPermissions = array_diff($currentPermissions, $permissionsToRevoke);

        // Update the pivot record
        $organizer->users()->updateExistingPivot($user->id, [
            'permissions' => json_encode(array_values($updatedPermissions)), // Re-index array
            'updated_at' => now(),
        ]);
    }
}
