<?php

namespace App\Actions\Organizer;

use App\Enums\OrganizerPermissionEnum;
use App\Exceptions\UnauthorizedOperationException;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GrantOrganizerPermissionAction
{
    /**
     * Grant a single permission to a user.
     */
    public function execute(int $organizerId, int $userId, string $permission, int $grantedBy): bool
    {
        return $this->executeMultiple($organizerId, $userId, [$permission], $grantedBy);
    }

    /**
     * Grant multiple permissions to a user.
     */
    public function executeMultiple(int $organizerId, int $userId, array $permissions, int $grantedBy): bool
    {
        return DB::transaction(function () use ($organizerId, $userId, $permissions, $grantedBy) {
            $organizer = $this->validateOrganizer($organizerId);
            $user = $this->validateUser($userId, 'User not found');
            $grantingUser = $this->validateUser($grantedBy, 'User performing grant not found');

            $this->validatePermissions($permissions);
            $this->validateMemberships($organizer, $user, $grantingUser);
            $this->validateGrantPermissions($organizer, $grantingUser);

            $this->performPermissionGrant($organizer, $user, $permissions);

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
    private function validateMemberships(Organizer $organizer, User $user, User $grantingUser): void
    {
        // Check if target user is a member and active
        $userMembership = $organizer->users()->where('user_id', $user->id)->first();
        if (!$userMembership) {
            throw new InvalidArgumentException('User is not a member of this organizer');
        }

        if (!$userMembership->pivot->is_active) {
            throw new InvalidArgumentException('Cannot grant permissions to inactive user');
        }

        // Check if granting user is a member and active
        $grantingUserMembership = $organizer->users()
            ->where('user_id', $grantingUser->id)
            ->where('is_active', true)
            ->first();

        if (!$grantingUserMembership) {
            throw new UnauthorizedOperationException('User performing grant is not a member of this organizer');
        }
    }

    /**
     * Validate that the granting user has permissions to grant permissions.
     */
    private function validateGrantPermissions(Organizer $organizer, User $grantingUser): void
    {
        $grantingUserMembership = $organizer->users()->where('user_id', $grantingUser->id)->first();
        $grantingUserRole = $grantingUserMembership->pivot->role_in_organizer;

        // Only owners and managers can grant permissions
        if (!in_array($grantingUserRole, ['owner', 'manager'])) {
            throw new UnauthorizedOperationException('Insufficient permissions to grant permissions');
        }
    }

    /**
     * Perform the actual permission grant operation.
     */
    private function performPermissionGrant(Organizer $organizer, User $user, array $newPermissions): void
    {
        $currentMembership = $organizer->users()->where('user_id', $user->id)->first();
        $currentPermissions = $currentMembership->pivot->permissions
            ? json_decode($currentMembership->pivot->permissions, true)
            : [];

        // Merge new permissions with existing ones (avoiding duplicates)
        $updatedPermissions = array_unique(array_merge($currentPermissions, $newPermissions));

        // Update the pivot record
        $organizer->users()->updateExistingPivot($user->id, [
            'permissions' => json_encode($updatedPermissions),
            'updated_at' => now(),
        ]);
    }
}
