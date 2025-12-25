<?php

namespace App\Modules\TicketHold\Enums;

enum HoldStatusEnum: string
{
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case RELEASED = 'released';
    case EXHAUSTED = 'exhausted';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::EXPIRED => 'Expired',
            self::RELEASED => 'Released',
            self::EXHAUSTED => 'Exhausted',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'green',
            self::EXPIRED => 'gray',
            self::RELEASED => 'blue',
            self::EXHAUSTED => 'orange',
        };
    }

    public function isUsable(): bool
    {
        return $this === self::ACTIVE;
    }
}
