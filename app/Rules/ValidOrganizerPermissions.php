<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidOrganizerPermissions implements ValidationRule
{
    /**
     * The list of valid organizer permissions
     */
    private const VALID_PERMISSIONS = [
        'create_events',
        'edit_events',
        'delete_events',
        'view_events',
        'manage_team',
        'invite_users',
        'remove_users',
        'edit_team_roles',
        'view_analytics',
        'manage_finances',
        'edit_organizer_profile',
        'manage_settings',
        'view_bookings',
        'export_data',
    ];

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Allow null/empty values (other validation rules can handle required)
        if (is_null($value) || (is_array($value) && empty($value))) {
            return;
        }

        // Ensure the value is an array
        if (!is_array($value)) {
            $fail("The {$attribute} must be an array of permissions.");
            return;
        }

        // Check each permission against the valid list
        $invalidPermissions = array_diff($value, self::VALID_PERMISSIONS);

        if (!empty($invalidPermissions)) {
            $invalidList = implode(', ', $invalidPermissions);
            $fail("The {$attribute} contains invalid permissions: {$invalidList}. Valid permissions are: " . implode(', ', self::VALID_PERMISSIONS));
        }
    }

    /**
     * Get the list of valid permissions
     */
    public static function getValidPermissions(): array
    {
        return self::VALID_PERMISSIONS;
    }
}
