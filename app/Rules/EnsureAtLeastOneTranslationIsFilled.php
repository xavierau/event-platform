<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
// use Illuminate\Contracts\Validation\ImplicitRule; // This line should be removed or commented if present
use Illuminate\Support\Arr;

class EnsureAtLeastOneTranslationIsFilled implements ValidationRule // Ensure ImplicitRule is NOT listed here
{
    protected string $fieldName;

    public function __construct(string $fieldName)
    {
        $this->fieldName = $fieldName;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_array($value)) {
            // This should ideally be caught by 'array' rule first
            $fail("The {$this->fieldName} must be an array.")->translate();
            return;
        }

        $hasAtLeastOneFilled = false;
        foreach ($value as $localeValue) {
            if (!empty(trim((string) $localeValue))) {
                $hasAtLeastOneFilled = true;
                break;
            }
        }

        if (!$hasAtLeastOneFilled) {
            $fail("At least one translation for the {$this->fieldName} field must be provided.")->translate();
        }
    }
}
