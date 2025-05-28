<?php

namespace App\Modules\Wallet\Enums;

enum WalletTransactionType: string
{
    case EARN_POINTS = 'earn_points';
    case SPEND_POINTS = 'spend_points';
    case EARN_KILL_POINTS = 'earn_kill_points';
    case SPEND_KILL_POINTS = 'spend_kill_points';
    case MEMBERSHIP_PURCHASE = 'membership_purchase';
    case TRANSFER_IN = 'transfer_in';
    case TRANSFER_OUT = 'transfer_out';
    case REFUND = 'refund';
    case BONUS = 'bonus';
    case PENALTY = 'penalty';

    /**
     * Get all earning transaction types.
     */
    public static function earningTypes(): array
    {
        return [
            self::EARN_POINTS,
            self::EARN_KILL_POINTS,
            self::TRANSFER_IN,
            self::REFUND,
            self::BONUS,
        ];
    }

    /**
     * Get all spending transaction types.
     */
    public static function spendingTypes(): array
    {
        return [
            self::SPEND_POINTS,
            self::SPEND_KILL_POINTS,
            self::MEMBERSHIP_PURCHASE,
            self::TRANSFER_OUT,
            self::PENALTY,
        ];
    }

    /**
     * Check if this is an earning transaction type.
     */
    public function isEarning(): bool
    {
        return in_array($this, self::earningTypes());
    }

    /**
     * Check if this is a spending transaction type.
     */
    public function isSpending(): bool
    {
        return in_array($this, self::spendingTypes());
    }

    /**
     * Get the display label for the transaction type.
     */
    public function label(): string
    {
        return match ($this) {
            self::EARN_POINTS => 'Earn Points',
            self::SPEND_POINTS => 'Spend Points',
            self::EARN_KILL_POINTS => 'Earn Kill Points',
            self::SPEND_KILL_POINTS => 'Spend Kill Points',
            self::MEMBERSHIP_PURCHASE => 'Membership Purchase',
            self::TRANSFER_IN => 'Transfer In',
            self::TRANSFER_OUT => 'Transfer Out',
            self::REFUND => 'Refund',
            self::BONUS => 'Bonus',
            self::PENALTY => 'Penalty',
        };
    }
}
