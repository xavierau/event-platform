<?php

namespace App\Modules\Membership\Enums;

enum PaymentMethod: string
{
    case POINTS = 'points';
    case KILL_POINTS = 'kill_points';
    case STRIPE = 'stripe';
    case ADMIN_GRANT = 'admin_grant';
    case PROMOTIONAL = 'promotional';

    /**
     * Get the display label for the payment method.
     */
    public function label(): string
    {
        return match ($this) {
            self::POINTS => 'Points',
            self::KILL_POINTS => 'Kill Points',
            self::STRIPE => 'Credit Card',
            self::ADMIN_GRANT => 'Admin Grant',
            self::PROMOTIONAL => 'Promotional',
        };
    }

    /**
     * Check if this payment method uses wallet points.
     */
    public function usesWallet(): bool
    {
        return in_array($this, [self::POINTS, self::KILL_POINTS]);
    }

    /**
     * Check if this payment method requires external payment processing.
     */
    public function requiresExternalPayment(): bool
    {
        return $this === self::STRIPE;
    }

    /**
     * Check if this payment method is free (no payment required).
     */
    public function isFree(): bool
    {
        return in_array($this, [self::ADMIN_GRANT, self::PROMOTIONAL]);
    }
}
