<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;
use App\Enums\OrganizerPermissionEnum;

class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, Booking $booking): bool
    {
        // Platform admins can view any booking
        if ($user->hasRole('admin')) {
            return true;
        }

        // Check if user has organizer permissions for this event
        return $user->hasOrganizerPermission(
            $booking->event->organizer,
            OrganizerPermissionEnum::VIEW_BOOKINGS
        );
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Booking $booking): bool
    {
        // Platform admins can update any booking
        if ($user->hasRole('admin')) {
            return true;
        }

        // Check if user has organizer permissions for this event
        return $user->hasOrganizerPermission(
            $booking->event->organizer,
            OrganizerPermissionEnum::MANAGE_BOOKINGS
        );
    }

    public function delete(User $user, Booking $booking): bool
    {
        // Platform admins can delete any booking
        if ($user->hasRole('admin')) {
            return true;
        }

        // Check if user has organizer permissions for this event
        return $user->hasOrganizerPermission(
            $booking->event->organizer,
            OrganizerPermissionEnum::MANAGE_BOOKINGS
        );
    }

    public function assignSeat(User $user, Booking $booking): bool
    {
        // Platform admins can assign seats to any booking
        if ($user->hasRole('admin')) {
            return true;
        }

        // Check if user has organizer permissions for this event
        return $user->hasOrganizerPermission(
            $booking->eventOccurrence->event->organizer,
            OrganizerPermissionEnum::MANAGE_BOOKINGS
        );
    }
}