<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Organizer;
use App\Enums\RoleNameEnum;
use App\Enums\OrganizerRoleEnum;
use App\Enums\OrganizerPermissionEnum;

class TeamManagementPolicy
{
    /**
     * Determine whether the user can invite users to the organizer.
     */
    public function inviteUser(User $user, Organizer $organizer): bool
    {
        // Platform admins can invite users to any organizer
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Check if user has specific invite permission or manage team permission
        if ($user->canInviteToOrganizer($organizer) || $user->canManageOrganizerTeam($organizer)) {
            return true;
        }

        // Check role-based permissions (owners and managers can invite)
        $membership = $this->getActiveMembership($user, $organizer);
        if (!$membership) {
            return false;
        }

        $role = OrganizerRoleEnum::tryFrom($membership->role_in_organizer);
        return $role && in_array($role, [OrganizerRoleEnum::OWNER, OrganizerRoleEnum::MANAGER]);
    }

    /**
     * Determine whether the user can remove users from the organizer.
     */
    public function removeUser(User $user, Organizer $organizer, User $targetUser): bool
    {
        // Platform admins can remove users from any organizer
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            // Prevent removing the last owner even for admins
            if ($this->isLastOwner($organizer, $targetUser)) {
                return false;
            }
            return true;
        }

        // Check if user has specific remove permission or manage team permission
        if ($user->canRemoveFromOrganizer($organizer) || $user->canManageOrganizerTeam($organizer)) {
            // Still need to check hierarchy and last owner rules
            return $this->canManageTargetUser($user, $organizer, $targetUser);
        }

        // Check role-based permissions
        $membership = $this->getActiveMembership($user, $organizer);
        if (!$membership) {
            return false;
        }

        $userRole = OrganizerRoleEnum::tryFrom($membership->role_in_organizer);
        if (!$userRole) {
            return false;
        }

        // Only owners and managers can remove users
        if (!in_array($userRole, [OrganizerRoleEnum::OWNER, OrganizerRoleEnum::MANAGER])) {
            return false;
        }

        return $this->canManageTargetUser($user, $organizer, $targetUser);
    }

    /**
     * Determine whether the user can update user roles in the organizer.
     */
    public function updateUserRole(User $user, Organizer $organizer, User $targetUser): bool
    {
        // Platform admins can update user roles in any organizer
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            // Prevent changing the last owner role even for admins
            if ($this->isLastOwner($organizer, $targetUser)) {
                return false;
            }
            return true;
        }

        // Prevent changing the last owner role
        if ($this->isLastOwner($organizer, $targetUser)) {
            return false;
        }

        // Check if user has specific edit team roles permission or manage team permission
        if ($user->canEditOrganizerTeamRoles($organizer) || $user->canManageOrganizerTeam($organizer)) {
            // Users with explicit permissions can manage based on permission-based hierarchy
            return $this->canManageTargetUserWithPermissions($user, $organizer, $targetUser);
        }

        // Check role-based permissions
        $membership = $this->getActiveMembership($user, $organizer);
        if (!$membership) {
            return false;
        }

        $userRole = OrganizerRoleEnum::tryFrom($membership->role_in_organizer);
        if (!$userRole) {
            return false;
        }

        // Only owners and managers can update user roles
        if (!in_array($userRole, [OrganizerRoleEnum::OWNER, OrganizerRoleEnum::MANAGER])) {
            return false;
        }

        return $this->canManageTargetUser($user, $organizer, $targetUser);
    }

    /**
     * Determine whether the user can view team members of the organizer.
     */
    public function viewTeamMembers(User $user, Organizer $organizer): bool
    {
        // Platform admins can view team members of any organizer
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Check if user has specific view team members permission
        if ($user->canViewOrganizerTeamMembers($organizer)) {
            return true;
        }

        // Any active member can view team members
        return $this->getActiveMembership($user, $organizer) !== null;
    }

    /**
     * Determine whether the user can manage permissions for organizer members.
     */
    public function managePermissions(User $user, Organizer $organizer, User $targetUser): bool
    {
        // Platform admins can manage permissions for any organizer
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Check if user has specific edit team roles permission or manage team permission
        if ($user->canEditOrganizerTeamRoles($organizer) || $user->canManageOrganizerTeam($organizer)) {
            // Users with explicit permissions can manage based on permission-based hierarchy
            return $this->canManageTargetUserWithPermissions($user, $organizer, $targetUser);
        }

        // Check role-based permissions
        $membership = $this->getActiveMembership($user, $organizer);
        if (!$membership) {
            return false;
        }

        $userRole = OrganizerRoleEnum::tryFrom($membership->role_in_organizer);
        if (!$userRole) {
            return false;
        }

        // Only owners and managers can manage permissions
        if (!in_array($userRole, [OrganizerRoleEnum::OWNER, OrganizerRoleEnum::MANAGER])) {
            return false;
        }

        return $this->canManageTargetUser($user, $organizer, $targetUser, false);
    }

    /**
     * Get active membership for a user in an organizer.
     */
    private function getActiveMembership(User $user, Organizer $organizer)
    {
        $userWithPivot = $organizer->users()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        return $userWithPivot?->pivot;
    }

    /**
     * Check if user can manage the target user based on role hierarchy.
     */
    private function canManageTargetUser(User $user, Organizer $organizer, User $targetUser, bool $checkLastOwner = true): bool
    {
        // Prevent removing/changing the last owner
        if ($checkLastOwner && $this->isLastOwner($organizer, $targetUser)) {
            return false;
        }

        $userMembership = $this->getActiveMembership($user, $organizer);
        $targetMembership = $this->getActiveMembership($targetUser, $organizer);

        if (!$userMembership || !$targetMembership) {
            return false;
        }

        $userRole = OrganizerRoleEnum::tryFrom($userMembership->role_in_organizer);
        $targetRole = OrganizerRoleEnum::tryFrom($targetMembership->role_in_organizer);

        if (!$userRole || !$targetRole) {
            return false;
        }

        // Owners can manage anyone
        if ($userRole === OrganizerRoleEnum::OWNER) {
            return true;
        }

        // Managers can manage staff and viewers, but not owners or other managers
        if ($userRole === OrganizerRoleEnum::MANAGER) {
            return in_array($targetRole, [OrganizerRoleEnum::STAFF, OrganizerRoleEnum::VIEWER]);
        }

        // Staff and viewers cannot manage other users
        return false;
    }

    /**
     * Check if user can manage the target user based on permissions and relaxed hierarchy.
     * This is used when the user has explicit permissions like EDIT_TEAM_ROLES.
     */
    private function canManageTargetUserWithPermissions(User $user, Organizer $organizer, User $targetUser): bool
    {
        $userMembership = $this->getActiveMembership($user, $organizer);
        $targetMembership = $this->getActiveMembership($targetUser, $organizer);

        if (!$userMembership || !$targetMembership) {
            return false;
        }

        $userRole = OrganizerRoleEnum::tryFrom($userMembership->role_in_organizer);
        $targetRole = OrganizerRoleEnum::tryFrom($targetMembership->role_in_organizer);

        if (!$userRole || !$targetRole) {
            return false;
        }

        // Owners can manage anyone (even with permissions)
        if ($userRole === OrganizerRoleEnum::OWNER) {
            return true;
        }

        // Users with explicit permissions cannot manage owners
        if ($targetRole === OrganizerRoleEnum::OWNER) {
            return false;
        }

        // Managers with explicit permissions cannot manage other managers (strict hierarchy maintained)
        if ($userRole === OrganizerRoleEnum::MANAGER && $targetRole === OrganizerRoleEnum::MANAGER) {
            return false;
        }

        // Users with explicit permissions can manage staff and viewers
        if (in_array($targetRole, [OrganizerRoleEnum::STAFF, OrganizerRoleEnum::VIEWER])) {
            return true;
        }

        // For all other cases, fall back to strict hierarchy
        return false;
    }

    /**
     * Check if the target user is the last owner of the organizer.
     */
    private function isLastOwner(Organizer $organizer, User $targetUser): bool
    {
        $targetMembership = $this->getActiveMembership($targetUser, $organizer);

        if (!$targetMembership || $targetMembership->role_in_organizer !== OrganizerRoleEnum::OWNER->value) {
            return false;
        }

        $ownerCount = $organizer->users()
            ->where('role_in_organizer', OrganizerRoleEnum::OWNER->value)
            ->where('is_active', true)
            ->count();

        return $ownerCount === 1;
    }
}
