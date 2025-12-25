<?php

namespace App\Modules\TicketHold\Policies;

use App\Enums\OrganizerPermissionEnum;
use App\Enums\RoleNameEnum;
use App\Models\User;
use App\Modules\TicketHold\Models\PurchaseLink;

class PurchaseLinkPolicy
{
    /**
     * Determine whether the user can view any purchase links.
     *
     * Platform admins can view all links.
     * Users with organizer membership can view links for their organizers.
     */
    public function viewAny(User $user): bool
    {
        // Platform admins can view all links
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Users with organizer membership can view links
        return $user->hasOrganizerMembership();
    }

    /**
     * Determine whether the user can view the purchase link.
     *
     * Platform admins can view any link.
     * Organizer members with VIEW_EVENTS permission can view their organizer's links.
     */
    public function view(User $user, PurchaseLink $link): bool
    {
        // Platform admins can view any link
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Get the organizer through the hold
        $organizer = $link->ticketHold?->organizer;

        if ($organizer) {
            return $user->hasOrganizerPermission(
                $organizer,
                OrganizerPermissionEnum::VIEW_EVENTS
            ) || $organizer->hasMember($user);
        }

        return false;
    }

    /**
     * Determine whether the user can create purchase links.
     *
     * Platform admins can create links.
     * Users need MANAGE_BOOKINGS permission for at least one organizer.
     */
    public function create(User $user): bool
    {
        // Platform admins can always create links
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // User must have at least one organizer where they can manage bookings
        return $user->getOrganizersWithPermission(OrganizerPermissionEnum::MANAGE_BOOKINGS)->isNotEmpty();
    }

    /**
     * Determine whether the user can update the purchase link.
     *
     * Platform admins can update any link.
     * Users need MANAGE_BOOKINGS permission for the link's organizer.
     */
    public function update(User $user, PurchaseLink $link): bool
    {
        // Platform admins can update any link
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Get the organizer through the hold
        $organizer = $link->ticketHold?->organizer;

        if ($organizer) {
            return $user->hasOrganizerPermission(
                $organizer,
                OrganizerPermissionEnum::MANAGE_BOOKINGS
            );
        }

        return false;
    }

    /**
     * Determine whether the user can delete the purchase link.
     *
     * Platform admins can delete any link.
     * Users need MANAGE_BOOKINGS permission for the link's organizer.
     */
    public function delete(User $user, PurchaseLink $link): bool
    {
        // Platform admins can delete any link
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Get the organizer through the hold
        $organizer = $link->ticketHold?->organizer;

        if ($organizer) {
            return $user->hasOrganizerPermission(
                $organizer,
                OrganizerPermissionEnum::MANAGE_BOOKINGS
            );
        }

        return false;
    }

    /**
     * Determine whether the user can restore the purchase link.
     */
    public function restore(User $user, PurchaseLink $link): bool
    {
        // Same as delete permission
        return $this->delete($user, $link);
    }

    /**
     * Determine whether the user can permanently delete the purchase link.
     *
     * Only platform admins can permanently delete links.
     */
    public function forceDelete(User $user, PurchaseLink $link): bool
    {
        return $user->hasRole(RoleNameEnum::ADMIN);
    }

    /**
     * Determine whether the user can revoke the purchase link.
     *
     * Platform admins can revoke any link.
     * Users need MANAGE_BOOKINGS permission for the link's organizer.
     */
    public function revoke(User $user, PurchaseLink $link): bool
    {
        // Platform admins can revoke any link
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Get the organizer through the hold
        $organizer = $link->ticketHold?->organizer;

        if ($organizer) {
            return $user->hasOrganizerPermission(
                $organizer,
                OrganizerPermissionEnum::MANAGE_BOOKINGS
            );
        }

        return false;
    }

    /**
     * Determine whether the user can view analytics for the link.
     *
     * Platform admins can view analytics for any link.
     * Users need VIEW_ANALYTICS permission for the link's organizer.
     */
    public function viewAnalytics(User $user, PurchaseLink $link): bool
    {
        // Platform admins can view analytics for any link
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Get the organizer through the hold
        $organizer = $link->ticketHold?->organizer;

        if ($organizer) {
            return $user->hasOrganizerPermission(
                $organizer,
                OrganizerPermissionEnum::VIEW_ANALYTICS
            );
        }

        return false;
    }

    /**
     * Determine whether a user can access the link for purchasing (public access).
     *
     * Anonymous links can be accessed by anyone.
     * User-tied links can only be accessed by the assigned user.
     */
    public function access(?User $user, PurchaseLink $link): bool
    {
        return $link->canBeUsedByUser($user);
    }
}
