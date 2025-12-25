<?php

namespace App\Modules\TicketHold\Enums;

enum QuantityModeEnum: string
{
    case FIXED = 'fixed';
    case MAXIMUM = 'maximum';
    case UNLIMITED = 'unlimited';

    public function label(): string
    {
        return match ($this) {
            self::FIXED => 'Exact Quantity',
            self::MAXIMUM => 'Up to Maximum',
            self::UNLIMITED => 'Unlimited (from pool)',
        };
    }

    public function requiresLimit(): bool
    {
        return $this !== self::UNLIMITED;
    }
}
