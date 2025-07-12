<?php

namespace App\Actions\Organizer;

use App\DataTransferObjects\Organizer\OrganizerUserData;
use App\Exceptions\UnauthorizedOperationException;
use App\Models\Organizer;
use App\Models\User;
use App\Notifications\RoleUpdatedNotification;
use App\Notifications\TeamMemberRoleChangedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateOrganizerUserRoleAction
{
    private array $validRoles = ['owner', 'manager', 'staff', 'viewer'];

    /**
     * Execute the role update process.
     */
    public function execute(int $organizerId, int $userId, string $newRole, int $updatedBy): bool
    {
        return $this->executeWithPermissions($organizerId, $userId, $newRole, null, $updatedBy);
    }

    /**
     * Execute role update with custom permissions.
     */
    public function executeWithPermissions(
        int $organizerId,
        int $userId,
        string $newRole,
        ?array $customPermissions,
        int $updatedBy
    ): bool {
        return DB::transaction(function () use ($organizerId, $userId, $newRole, $customPermissions, $updatedBy) {
            $organizer = $this->validateOrganizer($organizerId);
            $user = $this->validateUser($userId, 'User not found');
            $updater = $this->validateUser($updatedBy, 'User performing update not found');

            $this->validateRole($newRole);
            $this->validateMemberships($organizer, $user, $updater);

            $currentMembership = $organizer->users()->where('user_id', $userId)->first();
            $oldRole = $currentMembership->pivot->role_in_organizer;

            $this->validateUpdatePermissions($organizer, $user, $updater, $oldRole, $newRole);
            $this->validateLastOwnerRestriction($organizer, $user, $oldRole, $newRole);

            $this->performRoleUpdate($organizer, $user, $newRole, $customPermissions);
            $this->sendNotifications($organizer, $user, $updater, $oldRole, $newRole, $customPermissions);

            return true;
        });
    }

    /**
     * Execute with OrganizerUserData.
     */
    public function executeWithData(OrganizerUserData $organizerUserData): bool
    {
        return $this->executeWithPermissions(
            organizerId: $organizerUserData->organizer_id,
            userId: $organizerUserData->user_id,
            newRole: $organizerUserData->role_in_organizer,
            customPermissions: $organizerUserData->permissions,
            updatedBy: $organizerUserData->invited_by // Using invited_by as updater
        );
    }

    /**
     * Execute and return the updated OrganizerUserData.
     */
    public function executeAndReturnData(int $organizerId, int $userId, string $newRole, int $updatedBy): OrganizerUserData
    {
        $this->execute($organizerId, $userId, $newRole, $updatedBy);

        $organizer = Organizer::findOrFail($organizerId);
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
     * Validate that the role is valid.
     */
    private function validateRole(string $role): void
    {
        if (!in_array($role, $this->validRoles)) {
            throw new InvalidArgumentException('Invalid role provided');
        }
    }

    /**
     * Validate that both users are members of the organizer.
     */
    private function validateMemberships(Organizer $organizer, User $user, User $updater): void
    {
        // Check if user is a member
        $userMembership = $organizer->users()->where('user_id', $user->id)->first();
        if (!$userMembership) {
            throw new InvalidArgumentException('User is not a member of this organizer');
        }

        // Check if user is active
        if (!$userMembership->pivot->is_active) {
            throw new InvalidArgumentException('Cannot update role of inactive user');
        }

        // Check if updater is a member
        $updaterMembership = $organizer->users()
            ->where('user_id', $updater->id)
            ->where('is_active', true)
            ->first();

        if (!$updaterMembership) {
            throw new UnauthorizedOperationException('User performing update is not a member of this organizer');
        }
    }

    /**
     * Validate update permissions based on roles.
     */
    private function validateUpdatePermissions(
        Organizer $organizer,
        User $user,
        User $updater,
        string $oldRole,
        string $newRole
    ): void {
        $updaterMembership = $organizer->users()->where('user_id', $updater->id)->first();
        $updaterRole = $updaterMembership->pivot->role_in_organizer;

        // Only owners and managers can update roles
        if (!in_array($updaterRole, ['owner', 'manager'])) {
            throw new UnauthorizedOperationException('Insufficient permissions to update user roles');
        }

        // Managers have restrictions
        if ($updaterRole === 'manager') {
            // Cannot modify owners or other managers
            if (in_array($oldRole, ['owner', 'manager'])) {
                throw new UnauthorizedOperationException('Insufficient permissions to modify this user\'s role');
            }

            // Cannot promote to owner or manager
            if (in_array($newRole, ['owner', 'manager'])) {
                throw new UnauthorizedOperationException("Insufficient permissions to assign {$newRole} role");
            }
        }
    }

    /**
     * Validate that we're not demoting the last owner.
     */
    private function validateLastOwnerRestriction(Organizer $organizer, User $user, string $oldRole, string $newRole): void
    {
        if ($oldRole === 'owner' && $newRole !== 'owner') {
            $activeOwnerCount = $organizer->users()
                ->where('role_in_organizer', 'owner')
                ->where('is_active', true)
                ->count();

            if ($activeOwnerCount <= 1) {
                throw new UnauthorizedOperationException('Cannot demote the last owner of the organizer');
            }
        }
    }

    /**
     * Perform the actual role update.
     */
    private function performRoleUpdate(Organizer $organizer, User $user, string $newRole, ?array $customPermissions): void
    {
        $updateData = [
            'role_in_organizer' => $newRole,
            'permissions' => $customPermissions ? json_encode($customPermissions) : null,
            'updated_at' => now(),
        ];

        $organizer->users()->updateExistingPivot($user->id, $updateData);
    }

    /**
     * Send role update notifications.
     */
    private function sendNotifications(
        Organizer $organizer,
        User $user,
        User $updater,
        string $oldRole,
        string $newRole,
        ?array $customPermissions
    ): void {
        $organizerName = is_array($organizer->name)
            ? ($organizer->name['en'] ?? $organizer->name[array_key_first($organizer->name)] ?? 'Unknown Organizer')
            : $organizer->name;

        // Notify the user whose role was updated
        $user->notify(new RoleUpdatedNotification(
            organizerName: $organizerName,
            oldRole: $oldRole,
            newRole: $newRole,
            updatedByName: $updater->name,
            customPermissions: $customPermissions
        ));

        // Notify other owners and managers (but not the updater)
        $adminsToNotify = $organizer->users()
            ->whereIn('role_in_organizer', ['owner', 'manager'])
            ->where('is_active', true)
            ->where('user_id', '!=', $updater->id)
            ->get();

        foreach ($adminsToNotify as $admin) {
            $admin->notify(new TeamMemberRoleChangedNotification(
                organizerName: $organizerName,
                userName: $user->name,
                userEmail: $user->email,
                oldRole: $oldRole,
                newRole: $newRole,
                updatedByName: $updater->name,
                customPermissions: $customPermissions
            ));
        }
    }
}
