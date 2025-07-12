<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Organizer;
use App\Enums\RoleNameEnum;
use App\Enums\OrganizerRoleEnum;

class OrganizerPolicy
{
    /**
     * Determine whether the user can view any organizers.
     *
     * Platform admins can view all organizers.
     * Users with any organizer membership can view organizers.
     */
    public function viewAny(User $user): bool
    {
        // Platform admins can view all organizers
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Users with organizer membership can view organizers
        return $user->hasOrganizerMembership();
    }

    /**
     * Determine whether the user can view the organizer.
     *
     * Rules:
     * - Platform admins can view any organizer
     * - Users must be active members of the organizer to view it
     */
    public function view(User $user, Organizer $organizer): bool
    {
        // Platform admins can view any organizer
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Check if user is an active member of this organizer
        return $organizer->hasMember($user) && $this->isActiveMember($user, $organizer);
    }

    /**
     * Determine whether the user can create organizers.
     *
     * Rules:
     * - Platform admins can always create organizers
     * - Users with owner or manager roles in any organizer can create new organizers
     */
    public function create(User $user): bool
    {
        // Platform admins can always create organizers
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Users with owner or manager roles can create organizers
        return $user->hasOrganizerRole(OrganizerRoleEnum::OWNER) ||
            $user->hasOrganizerRole(OrganizerRoleEnum::MANAGER);
    }

    /**
     * Determine whether the user can update the organizer.
     *
     * Rules:
     * - Platform admins can update any organizer
     * - Organizer owners and managers can update their organizer
     */
    public function update(User $user, Organizer $organizer): bool
    {
        // Platform admins can update any organizer
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Check if user is an active member with proper role
        if (!$this->isActiveMember($user, $organizer)) {
            return false;
        }

        $role = $organizer->getUserRole($user);
        return $role && in_array($role, [OrganizerRoleEnum::OWNER, OrganizerRoleEnum::MANAGER]);
    }

    /**
     * Determine whether the user can delete the organizer.
     *
     * Rules:
     * - Platform admins can delete any organizer
     * - Only organizer owners can delete their organizer
     */
    public function delete(User $user, Organizer $organizer): bool
    {
        // Platform admins can delete any organizer
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Check if user is an active member with owner role
        if (!$this->isActiveMember($user, $organizer)) {
            return false;
        }

        $role = $organizer->getUserRole($user);
        return $role === OrganizerRoleEnum::OWNER;
    }

    /**
     * Determine whether the user can restore the organizer.
     *
     * Rules:
     * - Platform admins can restore any organizer
     * - Only organizer owners can restore their organizer
     */
    public function restore(User $user, Organizer $organizer): bool
    {
        // Platform admins can restore any organizer
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Check if user is an active member with owner role
        if (!$this->isActiveMember($user, $organizer)) {
            return false;
        }

        $role = $organizer->getUserRole($user);
        return $role === OrganizerRoleEnum::OWNER;
    }

    /**
     * Determine whether the user can permanently delete the organizer.
     *
     * Only platform admins can permanently delete organizers.
     */
    public function forceDelete(User $user, Organizer $organizer): bool
    {
        return $user->hasRole(RoleNameEnum::ADMIN);
    }

    /**
     * Determine whether the user can manage team members for the organizer.
     *
     * Rules:
     * - Platform admins can manage team for any organizer
     * - Organizer owners and managers can manage team
     * - Users with custom team management permissions can manage team
     */
    public function manageTeam(User $user, Organizer $organizer): bool
    {
        // Platform admins can manage team for any organizer
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Check if user is an active member
        if (!$this->isActiveMember($user, $organizer)) {
            return false;
        }

        // Check if user can manage organizer team through permissions or role
        return $user->canManageOrganizerTeam($organizer);
    }

    /**
     * Determine whether the user can manage settings for the organizer.
     *
     * Rules:
     * - Platform admins can manage settings for any organizer
     * - Organizer owners and managers can manage settings
     * - Users with custom settings management permissions can manage settings
     */
    public function manageSettings(User $user, Organizer $organizer): bool
    {
        // Platform admins can manage settings for any organizer
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Check if user is an active member
        if (!$this->isActiveMember($user, $organizer)) {
            return false;
        }

        // Check if user can manage organizer settings through permissions or role
        return $user->canManageOrganizerSettings($organizer);
    }

    /**
     * Check if the user is an active member of the organizer.
     */
    private function isActiveMember(User $user, Organizer $organizer): bool
    {
        return $organizer->users()
            ->where('user_id', $user->id)
            ->wherePivot('is_active', true)
            ->exists();
    }
}
