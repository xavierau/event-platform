<?php

namespace App\Modules\TicketHold\Enums;

enum LinkStatusEnum: string
{
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case REVOKED = 'revoked';
    case EXHAUSTED = 'exhausted';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::EXPIRED => 'Expired',
            self::REVOKED => 'Revoked',
            self::EXHAUSTED => 'Fully Used',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'green',
            self::EXPIRED => 'gray',
            self::REVOKED => 'red',
            self::EXHAUSTED => 'blue',
        };
    }

    public function isUsable(): bool
    {
        return $this === self::ACTIVE;
    }
}
