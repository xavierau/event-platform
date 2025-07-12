<?php

namespace App\Traits;

use App\Models\Organizer;
use App\Enums\OrganizerPermissionEnum;
use App\Enums\OrganizerRoleEnum;
use Illuminate\Database\Eloquent\Collection;

trait HasOrganizerPermissions
{
    /**
     * Check if the user has a specific permission for an organizer.
     *
     * @param Organizer $organizer
     * @param string|OrganizerPermissionEnum $permission
     * @return bool
     */
    public function hasOrganizerPermission(Organizer $organizer, string|OrganizerPermissionEnum $permission): bool
    {
        $permissionValue = $permission instanceof OrganizerPermissionEnum ? $permission->value : $permission;

        // Get user's membership in this organizer
        $membership = $this->organizerMembership($organizer);

        if (!$membership) {
            return false;
        }

        // Check if user has custom permission
        if ($this->hasCustomOrganizerPermission($organizer, $permissionValue)) {
            return true;
        }

        // Check if user's role has this permission by default
        return $this->hasRoleBasedOrganizerPermission($organizer, $permissionValue);
    }

    /**
     * Check if the user has any of the specified permissions for an organizer.
     *
     * @param Organizer $organizer
     * @param array $permissions
     * @return bool
     */
    public function hasAnyOrganizerPermission(Organizer $organizer, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasOrganizerPermission($organizer, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the user has all of the specified permissions for an organizer.
     *
     * @param Organizer $organizer
     * @param array $permissions
     * @return bool
     */
    public function hasAllOrganizerPermissions(Organizer $organizer, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasOrganizerPermission($organizer, $permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the user has a custom permission for an organizer.
     *
     * @param Organizer $organizer
     * @param string $permission
     * @return bool
     */
    public function hasCustomOrganizerPermission(Organizer $organizer, string $permission): bool
    {
        $membership = $this->organizerMembership($organizer);

        if (!$membership || !$membership->pivot->permissions) {
            return false;
        }

        $customPermissions = is_string($membership->pivot->permissions)
            ? json_decode($membership->pivot->permissions, true)
            : $membership->pivot->permissions;

        return is_array($customPermissions) && in_array($permission, $customPermissions);
    }

    /**
     * Check if the user's role has a permission by default for an organizer.
     *
     * @param Organizer $organizer
     * @param string $permission
     * @return bool
     */
    public function hasRoleBasedOrganizerPermission(Organizer $organizer, string $permission): bool
    {
        $role = $this->getOrganizerRole($organizer);

        if (!$role) {
            return false;
        }

        $defaultPermissions = OrganizerPermissionEnum::getDefaultPermissionsForRole($role);

        return in_array($permission, $defaultPermissions);
    }

    /**
     * Get all permissions the user has for an organizer (role-based + custom).
     *
     * @param Organizer $organizer
     * @return array
     */
    public function getOrganizerPermissions(Organizer $organizer): array
    {
        $membership = $this->organizerMembership($organizer);

        if (!$membership) {
            return [];
        }

        // Get role-based permissions
        $role = OrganizerRoleEnum::tryFrom($membership->pivot->role_in_organizer);
        $rolePermissions = $role ? OrganizerPermissionEnum::getDefaultPermissionsForRole($role) : [];

        // Get custom permissions
        $customPermissions = [];
        if ($membership->pivot->permissions) {
            $customPermissions = is_string($membership->pivot->permissions)
                ? json_decode($membership->pivot->permissions, true)
                : $membership->pivot->permissions;
        }

        // Merge and deduplicate
        return array_unique(array_merge($rolePermissions, $customPermissions ?: []));
    }

    /**
     * Get permissions grouped by category for an organizer.
     *
     * @param Organizer $organizer
     * @return array
     */
    public function getOrganizerPermissionsByCategory(Organizer $organizer): array
    {
        $userPermissions = $this->getOrganizerPermissions($organizer);
        $categorizedPermissions = [];

        foreach (OrganizerPermissionEnum::byCategory() as $category => $categoryPermissions) {
            $categorizedPermissions[$category] = array_intersect($userPermissions, $categoryPermissions);
        }

        return $categorizedPermissions;
    }

    /**
     * Check if user can manage settings for an organizer.
     *
     * @param Organizer $organizer
     * @return bool
     */
    public function canManageOrganizerSettings(Organizer $organizer): bool
    {
        return $this->hasAnyOrganizerPermission($organizer, [
            OrganizerPermissionEnum::MANAGE_ORGANIZER_SETTINGS->value,
            OrganizerPermissionEnum::EDIT_ORGANIZER_PROFILE->value,
            OrganizerPermissionEnum::MANAGE_SETTINGS->value,
        ]);
    }

    /**
     * Check if user can manage team for an organizer.
     *
     * @param Organizer $organizer
     * @return bool
     */
    public function canManageOrganizerTeam(Organizer $organizer): bool
    {
        return $this->hasAnyOrganizerPermission($organizer, [
            OrganizerPermissionEnum::MANAGE_TEAM->value,
            OrganizerPermissionEnum::INVITE_USERS->value,
            OrganizerPermissionEnum::REMOVE_USERS->value,
            OrganizerPermissionEnum::EDIT_TEAM_ROLES->value,
        ]);
    }

    /**
     * Check if user can invite users to an organizer.
     *
     * @param Organizer $organizer
     * @return bool
     */
    public function canInviteToOrganizer(Organizer $organizer): bool
    {
        return $this->hasAnyOrganizerPermission($organizer, [
            OrganizerPermissionEnum::INVITE_USERS->value,
            OrganizerPermissionEnum::MANAGE_TEAM->value,
        ]);
    }

    /**
     * Check if user can remove users from an organizer.
     *
     * @param Organizer $organizer
     * @return bool
     */
    public function canRemoveFromOrganizer(Organizer $organizer): bool
    {
        return $this->hasAnyOrganizerPermission($organizer, [
            OrganizerPermissionEnum::REMOVE_USERS->value,
            OrganizerPermissionEnum::MANAGE_TEAM->value,
        ]);
    }

    /**
     * Check if user can edit team roles for an organizer.
     *
     * @param Organizer $organizer
     * @return bool
     */
    public function canEditOrganizerTeamRoles(Organizer $organizer): bool
    {
        return $this->hasAnyOrganizerPermission($organizer, [
            OrganizerPermissionEnum::EDIT_TEAM_ROLES->value,
            OrganizerPermissionEnum::MANAGE_TEAM->value,
        ]);
    }

    /**
     * Check if user can view team members for an organizer.
     *
     * @param Organizer $organizer
     * @return bool
     */
    public function canViewOrganizerTeamMembers(Organizer $organizer): bool
    {
        return $this->hasAnyOrganizerPermission($organizer, [
            OrganizerPermissionEnum::VIEW_TEAM_MEMBERS->value,
            OrganizerPermissionEnum::MANAGE_TEAM->value,
        ]);
    }

    /**
     * Check if user can manage events for an organizer.
     *
     * @param Organizer $organizer
     * @return bool
     */
    public function canManageOrganizerEvents(Organizer $organizer): bool
    {
        return $this->hasAnyOrganizerPermission($organizer, [
            OrganizerPermissionEnum::CREATE_EVENTS->value,
            OrganizerPermissionEnum::EDIT_EVENTS->value,
            OrganizerPermissionEnum::DELETE_EVENTS->value,
            OrganizerPermissionEnum::PUBLISH_EVENTS->value,
            OrganizerPermissionEnum::MANAGE_EVENT_OCCURRENCES->value,
        ]);
    }

    /**
     * Check if user can manage venues for an organizer.
     *
     * @param Organizer $organizer
     * @return bool
     */
    public function canManageOrganizerVenues(Organizer $organizer): bool
    {
        return $this->hasAnyOrganizerPermission($organizer, [
            OrganizerPermissionEnum::MANAGE_VENUES->value,
            OrganizerPermissionEnum::CREATE_VENUES->value,
            OrganizerPermissionEnum::EDIT_VENUES->value,
            OrganizerPermissionEnum::DELETE_VENUES->value,
        ]);
    }

    /**
     * Check if user can view venues for an organizer.
     *
     * @param Organizer $organizer
     * @return bool
     */
    public function canViewOrganizerVenues(Organizer $organizer): bool
    {
        return $this->hasAnyOrganizerPermission($organizer, [
            OrganizerPermissionEnum::VIEW_VENUES->value,
            OrganizerPermissionEnum::MANAGE_VENUES->value,
            OrganizerPermissionEnum::EDIT_VENUES->value,
        ]);
    }

    /**
     * Check if user can edit venues for an organizer.
     *
     * @param Organizer $organizer
     * @return bool
     */
    public function canEditOrganizerVenues(Organizer $organizer): bool
    {
        return $this->hasAnyOrganizerPermission($organizer, [
            OrganizerPermissionEnum::EDIT_VENUES->value,
            OrganizerPermissionEnum::MANAGE_VENUES->value,
        ]);
    }

    /**
     * Check if user can view analytics for an organizer.
     *
     * @param Organizer $organizer
     * @return bool
     */
    public function canViewOrganizerAnalytics(Organizer $organizer): bool
    {
        return $this->hasAnyOrganizerPermission($organizer, [
            OrganizerPermissionEnum::VIEW_ANALYTICS->value,
            OrganizerPermissionEnum::VIEW_REPORTS->value,
            OrganizerPermissionEnum::VIEW_FINANCIAL_REPORTS->value,
        ]);
    }

    /**
     * Check if user can manage bookings for an organizer.
     *
     * @param Organizer $organizer
     * @return bool
     */
    public function canManageOrganizerBookings(Organizer $organizer): bool
    {
        return $this->hasAnyOrganizerPermission($organizer, [
            OrganizerPermissionEnum::VIEW_BOOKINGS->value,
            OrganizerPermissionEnum::MANAGE_BOOKINGS->value,
            OrganizerPermissionEnum::MANAGE_ATTENDEES->value,
            OrganizerPermissionEnum::PROCESS_REFUNDS->value,
        ]);
    }

    /**
     * Check if user has administrative permissions for an organizer.
     *
     * @param Organizer $organizer
     * @return bool
     */
    public function hasOrganizerAdministrativePermissions(Organizer $organizer): bool
    {
        $userPermissions = $this->getOrganizerPermissions($organizer);

        foreach ($userPermissions as $permission) {
            $permissionEnum = OrganizerPermissionEnum::tryFrom($permission);
            if ($permissionEnum && $permissionEnum->isAdministrative()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user only has view permissions for an organizer.
     *
     * @param Organizer $organizer
     * @return bool
     */
    public function hasOnlyViewOrganizerPermissions(Organizer $organizer): bool
    {
        $userPermissions = $this->getOrganizerPermissions($organizer);

        foreach ($userPermissions as $permission) {
            $permissionEnum = OrganizerPermissionEnum::tryFrom($permission);
            if ($permissionEnum && !$permissionEnum->isViewOnly()) {
                return false;
            }
        }

        return !empty($userPermissions);
    }

    /**
     * Get organizers where user has a specific permission.
     *
     * @param string|OrganizerPermissionEnum $permission
     * @return Collection
     */
    public function getOrganizersWithPermission(string|OrganizerPermissionEnum $permission): Collection
    {
        $permissionValue = $permission instanceof OrganizerPermissionEnum ? $permission->value : $permission;

        return $this->organizers->filter(function ($organizer) use ($permissionValue) {
            return $this->hasOrganizerPermission($organizer, $permissionValue);
        });
    }

    /**
     * Get organizers where user has any of the specified permissions.
     *
     * @param array $permissions
     * @return Collection
     */
    public function getOrganizersWithAnyPermission(array $permissions): Collection
    {
        return $this->organizers->filter(function ($organizer) use ($permissions) {
            return $this->hasAnyOrganizerPermission($organizer, $permissions);
        });
    }

    /**
     * Get organizers where user can manage events.
     *
     * @return Collection
     */
    public function getOrganizersWhereCanManageEvents(): Collection
    {
        return $this->organizers->filter(function ($organizer) {
            return $this->canManageOrganizerEvents($organizer);
        });
    }

    /**
     * Get organizers where user can manage team.
     *
     * @return Collection
     */
    public function getOrganizersWhereCanManageTeam(): Collection
    {
        return $this->organizers->filter(function ($organizer) {
            return $this->canManageOrganizerTeam($organizer);
        });
    }

    /**
     * Get organizers where user has administrative permissions.
     *
     * @return Collection
     */
    public function getOrganizersWithAdministrativeAccess(): Collection
    {
        return $this->organizers->filter(function ($organizer) {
            return $this->hasOrganizerAdministrativePermissions($organizer);
        });
    }

    /**
     * Helper method to get organizer membership pivot record.
     *
     * @param Organizer $organizer
     * @return \App\Models\Organizer|null
     */
    protected function organizerMembership(Organizer $organizer): ?Organizer
    {
        return $this->organizers()->where('organizer_id', $organizer->id)->first();
    }

    /**
     * Get user's role in an organizer.
     *
     * @param Organizer $organizer
     * @return OrganizerRoleEnum|null
     */
    protected function getOrganizerRole(Organizer $organizer): ?OrganizerRoleEnum
    {
        $membership = $this->organizerMembership($organizer);

        if (!$membership) {
            return null;
        }

        return OrganizerRoleEnum::tryFrom($membership->pivot->role_in_organizer);
    }
}
