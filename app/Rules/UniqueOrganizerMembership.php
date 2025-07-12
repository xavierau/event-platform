<?php

namespace App\Rules;

use App\Models\Organizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueOrganizerMembership implements ValidationRule
{
    public function __construct(
        private int $organizerId,
        private bool $ignoreInactive = true
    ) {}

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Skip validation if no user_id provided
        if (is_null($value)) {
            return;
        }

        $organizer = Organizer::find($this->organizerId);

        if (!$organizer) {
            $fail("The specified organizer does not exist.");
            return;
        }

        // Check if user is already a member
        $query = $organizer->users()->where('user_id', $value);

        // If we should ignore inactive members, only check for active ones
        if ($this->ignoreInactive) {
            $query->where('is_active', true);
        }

        if ($query->exists()) {
            $fail("This user is already a member of the organizer.");
        }
    }

    /**
     * Create instance that also considers inactive members as conflicts
     */
    public static function includeInactive(int $organizerId): self
    {
        return new self($organizerId, false);
    }
}
