<?php

namespace App\Actions\Organizer;

use App\Enums\OrganizerPermissionEnum;
use App\Exceptions\UnauthorizedOperationException;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateOrganizerPermissionsAction
{
    /**
     * Update (replace) all permissions for a user.
     */
    public function execute(int $organizerId, int $userId, array $permissions, int $updatedBy): bool
    {
        return DB::transaction(function () use ($organizerId, $userId, $permissions, $updatedBy) {
            $organizer = $this->validateOrganizer($organizerId);
            $user = $this->validateUser($userId, 'User not found');
            $updatingUser = $this->validateUser($updatedBy, 'User performing update not found');

            $this->validatePermissions($permissions);
            $this->validateMemberships($organizer, $user, $updatingUser);
            $this->validateUpdatePermissions($organizer, $updatingUser);

            $this->performPermissionUpdate($organizer, $user, $permissions);

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
    private function validateMemberships(Organizer $organizer, User $user, User $updatingUser): void
    {
        // Check if target user is a member and active
        $userMembership = $organizer->users()->where('user_id', $user->id)->first();
        if (!$userMembership) {
            throw new InvalidArgumentException('User is not a member of this organizer');
        }

        if (!$userMembership->pivot->is_active) {
            throw new InvalidArgumentException('Cannot update permissions for inactive user');
        }

        // Check if updating user is a member and active
        $updatingUserMembership = $organizer->users()
            ->where('user_id', $updatingUser->id)
            ->where('is_active', true)
            ->first();

        if (!$updatingUserMembership) {
            throw new UnauthorizedOperationException('User performing update is not a member of this organizer');
        }
    }

    /**
     * Validate that the updating user has permissions to update permissions.
     */
    private function validateUpdatePermissions(Organizer $organizer, User $updatingUser): void
    {
        $updatingUserMembership = $organizer->users()->where('user_id', $updatingUser->id)->first();
        $updatingUserRole = $updatingUserMembership->pivot->role_in_organizer;

        // Only owners and managers can update permissions
        if (!in_array($updatingUserRole, ['owner', 'manager'])) {
            throw new UnauthorizedOperationException('Insufficient permissions to update permissions');
        }
    }

    /**
     * Perform the actual permission update operation.
     */
    private function performPermissionUpdate(Organizer $organizer, User $user, array $newPermissions): void
    {
        // Remove duplicates and ensure clean array
        $uniquePermissions = array_unique($newPermissions);

        // Update the pivot record with the new permissions (completely replacing old ones)
        $organizer->users()->updateExistingPivot($user->id, [
            'permissions' => json_encode(array_values($uniquePermissions)), // Re-index array
            'updated_at' => now(),
        ]);
    }
}
