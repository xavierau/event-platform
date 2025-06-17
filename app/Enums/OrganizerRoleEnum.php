<?php

namespace App\Enums;

enum OrganizerRoleEnum: string
{
    case OWNER = 'owner';
    case MANAGER = 'manager';
    case STAFF = 'staff';
    case VIEWER = 'viewer';

    /**
     * Get all role values as an array.
     *
     * @return array<string>
     */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get roles with management capabilities.
     *
     * @return array<string>
     */
    public static function managementRoles(): array
    {
        return [
            self::OWNER->value,
            self::MANAGER->value,
        ];
    }

    /**
     * Check if the role can manage other users.
     */
    public function canManageUsers(): bool
    {
        return in_array($this->value, [self::OWNER->value, self::MANAGER->value]);
    }

    /**
     * Check if the role can manage organizer settings.
     */
    public function canManageOrganizer(): bool
    {
        return $this->value === self::OWNER->value;
    }

    /**
     * Check if the role can create/edit events.
     */
    public function canManageEvents(): bool
    {
        return in_array($this->value, [
            self::OWNER->value,
            self::MANAGER->value,
            self::STAFF->value,
        ]);
    }

    /**
     * Check if the role can only view.
     */
    public function isViewOnly(): bool
    {
        return $this->value === self::VIEWER->value;
    }
}
