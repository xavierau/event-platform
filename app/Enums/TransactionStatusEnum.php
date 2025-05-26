<?php

namespace App\Enums;

// use App\Traits\EnumEnhancements; // Assuming this trait might not exist yet

enum TransactionStatusEnum: string
{
    // use EnumEnhancements; // Assuming this trait might not exist yet

    case PENDING_PAYMENT = 'pending_payment'; // Initial status when payment is required but not yet made
    case CONFIRMED = 'confirmed';             // Payment successful, or free booking confirmed
    case CANCELLED = 'cancelled';             // Booking cancelled by user or system before payment/completion
    case FAILED_PAYMENT = 'failed_payment';     // Payment attempted but failed
    case REFUNDED = 'refunded';               // Booking was confirmed but then refunded
    case PENDING_CONFIRMATION = 'pending_confirmation'; // For free tickets or manual approval before 'confirmed'

    // You can add more statuses as needed, e.g., 'AWAITING_APPROVAL', 'EXPIRED'

    /**
     * Get the label for the enum case.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING_PAYMENT => 'Pending Payment',
            self::CONFIRMED => 'Confirmed',
            self::CANCELLED => 'Cancelled',
            self::FAILED_PAYMENT => 'Payment Failed',
            self::REFUNDED => 'Refunded',
            self::PENDING_CONFIRMATION => 'Pending Confirmation',
            default => str($this->value)->replace('_', ' ')->title(),
        };
    }
}
