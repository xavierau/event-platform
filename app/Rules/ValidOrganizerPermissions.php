<?php

namespace App\Rules;

use App\Enums\OrganizerPermissionEnum;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidOrganizerPermissions implements ValidationRule
{
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
        $validPermissions = OrganizerPermissionEnum::all();
        $invalidPermissions = array_diff($value, $validPermissions);

        if (!empty($invalidPermissions)) {
            $invalidList = implode(', ', $invalidPermissions);
            $fail("The {$attribute} contains invalid permissions: {$invalidList}. Valid permissions are: " . implode(', ', $validPermissions));
        }
    }

    /**
     * Get the list of valid permissions
     */
    public static function getValidPermissions(): array
    {
        return OrganizerPermissionEnum::all();
    }
}
