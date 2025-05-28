<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Log;

class RequiredTranslation implements ValidationRule
{
    public function __construct(private string $fieldName = 'field') {}

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Debug: Log that validation is being called
        Log::info("RequiredTranslation validation called for {$attribute}", ['value' => $value]);

        if (!is_array($value)) {
            $fail("The {$this->fieldName} must be an array of translations.");
            return;
        }

        $hasValue = false;
        foreach ($value as $locale => $localeValue) {
            if (!empty($localeValue) && is_string($localeValue) && trim($localeValue) !== '') {
                $hasValue = true;
                break;
            }
        }

        if (!$hasValue) {
            Log::info("RequiredTranslation validation failed for {$attribute}");
            $fail("At least one {$this->fieldName} translation must be provided.");
        } else {
            Log::info("RequiredTranslation validation passed for {$attribute}");
        }
    }
}
