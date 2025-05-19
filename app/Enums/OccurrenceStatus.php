<?php

namespace App\Enums;

enum OccurrenceStatus: string
{
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Cancelled = 'cancelled';
    case Postponed = 'postponed';
    case Completed = 'completed'; // Added if applicable

    public static function labels(): array
    {
        return [
            self::Scheduled->value => 'Scheduled',
            self::Active->value => 'Active',
            self::Cancelled->value => 'Cancelled',
            self::Postponed->value => 'Postponed',
            self::Completed->value => 'Completed',
        ];
    }

    public function label(): string
    {
        return static::labels()[$this->value];
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
