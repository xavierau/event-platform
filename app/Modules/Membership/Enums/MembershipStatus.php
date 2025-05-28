<?php

namespace App\Modules\Membership\Enums;

enum MembershipStatus: string
{
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
    case PENDING = 'pending';
    case SUSPENDED = 'suspended';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::EXPIRED => 'Expired',
            self::CANCELLED => 'Cancelled',
            self::PENDING => 'Pending',
            self::SUSPENDED => 'Suspended',
        };
    }

    /**
     * Get the color class for the status (for UI display).
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::ACTIVE => 'text-green-600',
            self::EXPIRED => 'text-red-600',
            self::CANCELLED => 'text-gray-600',
            self::PENDING => 'text-yellow-600',
            self::SUSPENDED => 'text-orange-600',
        };
    }

    /**
     * Check if the status is considered active.
     */
    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * Check if the status is considered inactive.
     */
    public function isInactive(): bool
    {
        return !$this->isActive();
    }
}
