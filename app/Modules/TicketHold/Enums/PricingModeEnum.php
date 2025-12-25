<?php

namespace App\Modules\TicketHold\Enums;

enum PricingModeEnum: string
{
    case ORIGINAL = 'original';
    case FIXED = 'fixed';
    case PERCENTAGE_DISCOUNT = 'percentage_discount';
    case FREE = 'free';

    public function label(): string
    {
        return match ($this) {
            self::ORIGINAL => 'Original Price',
            self::FIXED => 'Custom Fixed Price',
            self::PERCENTAGE_DISCOUNT => 'Percentage Discount',
            self::FREE => 'Free (Complimentary)',
        };
    }

    public function requiresValue(): bool
    {
        return match ($this) {
            self::ORIGINAL, self::FREE => false,
            self::FIXED, self::PERCENTAGE_DISCOUNT => true,
        };
    }
}
