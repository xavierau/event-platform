<?php

namespace App\Enums;

enum RoleNameEnum: string
{
    case ADMIN = 'admin';
    case USER = 'user';

    /**
     * Get all role names as an array.
     *
     * @return array<string>
     */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }
}
