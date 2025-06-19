<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Venue;
use App\Enums\RoleNameEnum;
use App\Enums\OrganizerRoleEnum;
use Illuminate\Auth\Access\Response;

class VenuePolicy
{
    /**
     * Determine whether the user can view any venues.
     *
     * Platform admins can view all venues.
     * Users with organizer membership can view public venues and their own organizer's venues.
     */
    public function viewAny(User $user): bool
    {
        // Platform admins can view all venues
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Users with organizer membership can view venues
        if ($user->hasOrganizerMembership()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view the venue.
     *
     * Rules:
     * - Platform admins can view any venue
     * - Public venues can be viewed by any user with organizer membership
     * - Organizer-specific venues can only be viewed by their owner organizer's members
     */
    public function view(User $user, Venue $venue): bool
    {
        // Platform admins can view any venue
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Public venues can be viewed by any user with organizer membership
        if ($venue->isPublic() && $user->hasOrganizerMembership()) {
            return true;
        }

        // Organizer-specific venues can only be viewed by their organizer's members
        if ($venue->isOrganizerSpecific() && $venue->organizer) {
            return $user->canManageOrganizer($venue->organizer) ||
                $user->organizersByRole(OrganizerRoleEnum::STAFF)->contains($venue->organizer);
        }

        return false;
    }

    /**
     * Determine whether the user can create venues.
     *
     * Only platform admins can create venues.
     * Organizers cannot create venues directly - they are assigned by admins.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(RoleNameEnum::ADMIN);
    }

    /**
     * Determine whether the user can update the venue.
     *
     * Rules:
     * - Platform admins can update any venue
     * - Organizer owners/managers can update their organizer-specific venues
     * - Public venues can only be updated by platform admins
     */
    public function update(User $user, Venue $venue): bool
    {
        // Platform admins can update any venue
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Public venues can only be updated by platform admins
        if ($venue->isPublic()) {
            return false;
        }

        // Organizer-specific venues can be updated by their organizer's owners/managers
        if ($venue->isOrganizerSpecific() && $venue->organizer) {
            return $user->canManageOrganizer($venue->organizer);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the venue.
     *
     * Only platform admins can delete venues.
     */
    public function delete(User $user, Venue $venue): bool
    {
        return $user->hasRole(RoleNameEnum::ADMIN);
    }

    /**
     * Determine whether the user can restore the venue.
     *
     * Only platform admins can restore venues.
     */
    public function restore(User $user, Venue $venue): bool
    {
        return $user->hasRole(RoleNameEnum::ADMIN);
    }

    /**
     * Determine whether the user can permanently delete the venue.
     *
     * Only platform admins can permanently delete venues.
     */
    public function forceDelete(User $user, Venue $venue): bool
    {
        return $user->hasRole(RoleNameEnum::ADMIN);
    }

    /**
     * Determine whether the user can use the venue for events.
     *
     * Rules:
     * - Platform admins can use any venue
     * - Public venues can be used by any organizer member
     * - Organizer-specific venues can only be used by their owner organizer's members
     */
    public function use(User $user, Venue $venue): bool
    {
        // Platform admins can use any venue
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Public venues can be used by any user with organizer membership
        if ($venue->isPublic() && $user->hasOrganizerMembership()) {
            return true;
        }

        // Organizer-specific venues can only be used by their organizer's members
        if ($venue->isOrganizerSpecific() && $venue->organizer) {
            return $user->organizers()->contains($venue->organizer);
        }

        return false;
    }

    /**
     * Determine whether the user can assign the venue to an organizer.
     *
     * Only platform admins can assign venues to organizers.
     */
    public function assign(User $user, Venue $venue): bool
    {
        return $user->hasRole(RoleNameEnum::ADMIN);
    }

    /**
     * Determine whether the user can unassign the venue from an organizer.
     *
     * Only platform admins can unassign venues from organizers.
     */
    public function unassign(User $user, Venue $venue): bool
    {
        return $user->hasRole(RoleNameEnum::ADMIN);
    }

    /**
     * Determine whether the user can manage venue assignments.
     *
     * Only platform admins can manage venue assignments.
     */
    public function manageAssignments(User $user): bool
    {
        return $user->hasRole(RoleNameEnum::ADMIN);
    }
}
