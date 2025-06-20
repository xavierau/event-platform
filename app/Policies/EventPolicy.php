<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Event;
use App\Enums\RoleNameEnum;
use App\Enums\OrganizerRoleEnum;
use Illuminate\Auth\Access\Response;

class EventPolicy
{
    /**
     * Determine whether the user can view any events.
     *
     * Platform admins can view all events.
     * Users with organizer membership can view events based on their organizer access.
     */
    public function viewAny(User $user): bool
    {
        // Platform admins can view all events
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Users with organizer membership can view events
        if ($user->hasOrganizerMembership()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view the event.
     *
     * Rules:
     * - Platform admins can view any event
     * - Published events can be viewed by any authenticated user
     * - Draft/unpublished events can only be viewed by their organizer's members
     * - Users must be members of the event's organizer to view non-public events
     */
    public function view(User $user, Event $event): bool
    {
        // Platform admins can view any event
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Published events can be viewed by any authenticated user
        if ($event->event_status === 'published') {
            return true;
        }

        // For non-published events, user must be a member of the event's organizer
        if ($event->organizer && $user->hasOrganizerMembership()) {
            return $event->organizer->hasMember($user);
        }

        return false;
    }

    /**
     * Determine whether the user can create events.
     *
     * Rules:
     * - Platform admins can always create events
     * - Users must have organizer membership with event management permissions
     */
    public function create(User $user): bool
    {
        // Platform admins can always create events
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // User must have at least one organizer membership where they can manage events
        return $user->getEventManageableOrganizers()->exists();
    }

    /**
     * Determine whether the user can update the event.
     *
     * Rules:
     * - Platform admins can update any event
     * - Users must be members of the event's organizer with event management permissions
     */
    public function update(User $user, Event $event): bool
    {
        // Platform admins can update any event
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // User must be able to manage events for this event's organizer
        if ($event->organizer) {
            return $event->organizer->userCanManageEvents($user);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the event.
     *
     * Rules:
     * - Platform admins can delete any event
     * - Users with DELETE_EVENTS permission can delete their organizer's events
     * - Fallback: Organizer owners and managers can delete their organizer's events
     */
    public function delete(User $user, Event $event): bool
    {
        // Platform admins can delete any event
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Check if user has specific delete events permission
        if ($event->organizer) {
            if ($user->hasOrganizerPermission($event->organizer, \App\Enums\OrganizerPermissionEnum::DELETE_EVENTS)) {
                return true;
            }

            // Fallback to role-based check for compatibility
            $role = $event->organizer->getUserRole($user);
            return $role && in_array($role, [OrganizerRoleEnum::OWNER, OrganizerRoleEnum::MANAGER]);
        }

        return false;
    }

    /**
     * Determine whether the user can restore the event.
     *
     * Rules:
     * - Platform admins can restore any event
     * - Users with DELETE_EVENTS permission can restore their organizer's events (same as delete)
     * - Fallback: Organizer owners and managers can restore their organizer's events
     */
    public function restore(User $user, Event $event): bool
    {
        // Platform admins can restore any event
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Check if user has specific delete events permission (restore follows same rules as delete)
        if ($event->organizer) {
            if ($user->hasOrganizerPermission($event->organizer, \App\Enums\OrganizerPermissionEnum::DELETE_EVENTS)) {
                return true;
            }

            // Fallback to role-based check for compatibility
            $role = $event->organizer->getUserRole($user);
            return $role && in_array($role, [OrganizerRoleEnum::OWNER, OrganizerRoleEnum::MANAGER]);
        }

        return false;
    }

    /**
     * Determine whether the user can permanently delete the event.
     *
     * Only platform admins can permanently delete events.
     */
    public function forceDelete(User $user, Event $event): bool
    {
        return $user->hasRole(RoleNameEnum::ADMIN);
    }

    /**
     * Determine whether the user can publish/unpublish the event.
     *
     * Rules:
     * - Platform admins can change publication status of any event
     * - Users with PUBLISH_EVENTS permission can change publication status of their events
     * - Fallback: Organizer owners and managers can change publication status of their events
     */
    public function publish(User $user, Event $event): bool
    {
        // Platform admins can change publication status of any event
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Check if user has specific publish events permission
        if ($event->organizer) {
            if ($user->hasOrganizerPermission($event->organizer, \App\Enums\OrganizerPermissionEnum::PUBLISH_EVENTS)) {
                return true;
            }

            // Fallback to role-based check for compatibility
            $role = $event->organizer->getUserRole($user);
            return $role && in_array($role, [OrganizerRoleEnum::OWNER, OrganizerRoleEnum::MANAGER]);
        }

        return false;
    }

    /**
     * Determine whether the user can manage event occurrences.
     *
     * Rules:
     * - Platform admins can manage occurrences for any event
     * - Users must be able to manage events for the event's organizer
     */
    public function manageOccurrences(User $user, Event $event): bool
    {
        // Platform admins can manage occurrences for any event
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // User must be able to manage events for this event's organizer
        if ($event->organizer) {
            return $event->organizer->userCanManageEvents($user);
        }

        return false;
    }

    /**
     * Determine whether the user can manage event bookings.
     *
     * Rules:
     * - Platform admins can manage bookings for any event
     * - Users with VIEW_BOOKINGS permission can manage bookings for their events
     * - Fallback: Any member of the event's organizer can view/manage bookings
     */
    public function manageBookings(User $user, Event $event): bool
    {
        // Platform admins can manage bookings for any event
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // Check if user has specific bookings permission
        if ($event->organizer) {
            if ($user->hasOrganizerPermission($event->organizer, \App\Enums\OrganizerPermissionEnum::VIEW_BOOKINGS)) {
                return true;
            }

            // Fallback: Any member of the event's organizer can view/manage bookings
            return $event->organizer->hasMember($user);
        }

        return false;
    }

    /**
     * Determine whether the user can manage event media.
     *
     * Rules:
     * - Platform admins can manage media for any event
     * - Users must be able to manage events for the event's organizer
     */
    public function manageMedia(User $user, Event $event): bool
    {
        // Platform admins can manage media for any event
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // User must be able to manage events for this event's organizer
        if ($event->organizer) {
            return $event->organizer->userCanManageEvents($user);
        }

        return false;
    }

    /**
     * Determine whether the user can duplicate the event.
     *
     * Rules:
     * - Platform admins can duplicate any event
     * - Users can duplicate events if they can create events for the same organizer
     */
    public function duplicate(User $user, Event $event): bool
    {
        // Platform admins can duplicate any event
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return true;
        }

        // User must be able to manage events for this event's organizer
        if ($event->organizer) {
            return $event->organizer->userCanManageEvents($user);
        }

        return false;
    }

    /**
     * Determine whether the user can change the event's organizer.
     *
     * Only platform admins can change event organizer assignments.
     */
    public function changeOrganizer(User $user, Event $event): bool
    {
        return $user->hasRole(RoleNameEnum::ADMIN);
    }
}
