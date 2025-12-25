<?php

namespace App\Modules\TicketHold\Policies;

use App\Enums\OrganizerPermissionEnum;
use App\Enums\RoleNameEnum;
use App\Models\User;
use App\Modules\TicketHold\Models\TicketHold;

class TicketHoldPolicy
{
    /**
     * Determine whether the user can view any ticket holds.
     *
     * Platform admins can view all holds.
     * Users with organizer membership can view holds for their organizers.
     */
    public function viewAny(User $user): bool
    {
        // Platform admins can view all holds
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Users with organizer membership can view holds
        return $user->hasOrganizerMembership();
    }

    /**
     * Determine whether the user can view the ticket hold.
     *
     * Platform admins can view any hold.
     * Organizer members with VIEW_EVENTS permission can view their organizer's holds.
     */
    public function view(User $user, TicketHold $hold): bool
    {
        // Platform admins can view any hold
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Check if user has permission for this organizer
        if ($hold->organizer) {
            return $user->hasOrganizerPermission(
                $hold->organizer,
                OrganizerPermissionEnum::VIEW_EVENTS
            ) || $hold->organizer->hasMember($user);
        }

        return false;
    }

    /**
     * Determine whether the user can create ticket holds.
     *
     * Platform admins can create holds for any organizer.
     * Users need MANAGE_BOOKINGS permission to create holds.
     */
    public function create(User $user): bool
    {
        // Platform admins can always create holds
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // User must have at least one organizer where they can manage bookings
        return $user->getOrganizersWithPermission(OrganizerPermissionEnum::MANAGE_BOOKINGS)->isNotEmpty();
    }

    /**
     * Determine whether the user can update the ticket hold.
     *
     * Platform admins can update any hold.
     * Users need MANAGE_BOOKINGS permission for the hold's organizer.
     */
    public function update(User $user, TicketHold $hold): bool
    {
        // Platform admins can update any hold
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Check if user has permission for this organizer
        if ($hold->organizer) {
            return $user->hasOrganizerPermission(
                $hold->organizer,
                OrganizerPermissionEnum::MANAGE_BOOKINGS
            );
        }

        return false;
    }

    /**
     * Determine whether the user can delete the ticket hold.
     *
     * Platform admins can delete any hold.
     * Users need MANAGE_BOOKINGS permission for the hold's organizer.
     */
    public function delete(User $user, TicketHold $hold): bool
    {
        // Platform admins can delete any hold
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Check if user has permission for this organizer
        if ($hold->organizer) {
            return $user->hasOrganizerPermission(
                $hold->organizer,
                OrganizerPermissionEnum::MANAGE_BOOKINGS
            );
        }

        return false;
    }

    /**
     * Determine whether the user can restore the ticket hold.
     */
    public function restore(User $user, TicketHold $hold): bool
    {
        // Same as delete permission
        return $this->delete($user, $hold);
    }

    /**
     * Determine whether the user can permanently delete the ticket hold.
     *
     * Only platform admins can permanently delete holds.
     */
    public function forceDelete(User $user, TicketHold $hold): bool
    {
        return $user->hasRole(RoleNameEnum::ADMIN);
    }

    /**
     * Determine whether the user can release the ticket hold.
     *
     * Platform admins can release any hold.
     * Users need MANAGE_BOOKINGS permission for the hold's organizer.
     */
    public function release(User $user, TicketHold $hold): bool
    {
        // Platform admins can release any hold
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Check if user has permission for this organizer
        if ($hold->organizer) {
            return $user->hasOrganizerPermission(
                $hold->organizer,
                OrganizerPermissionEnum::MANAGE_BOOKINGS
            );
        }

        return false;
    }

    /**
     * Determine whether the user can create purchase links for the hold.
     *
     * Platform admins can create links for any hold.
     * Users need MANAGE_BOOKINGS permission for the hold's organizer.
     */
    public function createLink(User $user, TicketHold $hold): bool
    {
        // Platform admins can create links for any hold
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Check if user has permission for this organizer
        if ($hold->organizer) {
            return $user->hasOrganizerPermission(
                $hold->organizer,
                OrganizerPermissionEnum::MANAGE_BOOKINGS
            );
        }

        return false;
    }

    /**
     * Determine whether the user can view analytics for the hold.
     *
     * Platform admins can view analytics for any hold.
     * Users need VIEW_ANALYTICS permission for the hold's organizer.
     */
    public function viewAnalytics(User $user, TicketHold $hold): bool
    {
        // Platform admins can view analytics for any hold
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Check if user has permission for this organizer
        if ($hold->organizer) {
            return $user->hasOrganizerPermission(
                $hold->organizer,
                OrganizerPermissionEnum::VIEW_ANALYTICS
            );
        }

        return false;
    }
}
