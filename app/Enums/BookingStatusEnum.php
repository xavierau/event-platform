<?php

namespace App\Enums;

enum BookingStatusEnum: string
{
    case PENDING_CONFIRMATION = 'pending_confirmation'; // Booking initiated, awaiting finalization (e.g., payment)
    case CONFIRMED = 'confirmed';             // Booking is active and valid (payment complete or free booking)
    case CANCELLED = 'cancelled';             // Booking has been voided
    case USED = 'used';                       // Ticket/booking has been utilized (e.g., event entry)
    case EXPIRED = 'expired';                 // Booking was valid but passed its validity period without use

    /**
     * Get the label for the enum case.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING_CONFIRMATION => 'Pending Confirmation',
            self::CONFIRMED => 'Confirmed',
            self::CANCELLED => 'Cancelled',
            self::USED => 'Used',
            self::EXPIRED => 'Expired',
            // default => str($this->value)->replace('_', ' ')->title(), // Not needed if all cases are covered
        };
    }

    // You could add methods here for color-coding, transitions, etc.
    // public function color(): string
    // {
    //     return match ($this) {
    //         self::PENDING_CONFIRMATION => 'blue',
    //         self::CONFIRMED => 'green',
    //         self::CANCELLED => 'red',
    //         self::USED => 'purple',
    //         self::EXPIRED => 'gray',
    //     };
    // }
}
