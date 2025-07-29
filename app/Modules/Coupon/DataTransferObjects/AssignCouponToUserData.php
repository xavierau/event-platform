<?php

namespace App\Modules\Coupon\DataTransferObjects;

use Spatie\LaravelData\Data;

class AssignCouponToUserData extends Data
{
    public function __construct(
        public readonly mixed $coupon_id,
        public readonly mixed $user_id,
        public readonly mixed $assigned_by,
        public readonly string $assignment_reason,
        public readonly ?string $assignment_notes = null,
        public readonly mixed $times_can_be_used = 1,
        public readonly mixed $quantity = 1,
    ) {}

    public static function rules(): array
    {
        return [
            'coupon_id' => ['required', 'integer', 'exists:coupons,id'],
            'user_id' => ['required', 'integer', 'exists:users,id', 'different:assigned_by'],
            'assigned_by' => ['required', 'integer', 'exists:users,id'],
            'assignment_reason' => ['required', 'string', 'min:3', 'max:500', 'regex:/\S/'],
            'assignment_notes' => ['nullable', 'string', 'max:1000'],
            'times_can_be_used' => ['integer', 'min:1'],
            'quantity' => ['integer', 'min:1'],
        ];
    }

    public static function messages(): array
    {
        return [
            'coupon_id.required' => 'Coupon ID is required.',
            'coupon_id.exists' => 'The selected coupon does not exist.',
            'user_id.required' => 'User ID is required.',
            'user_id.exists' => 'The selected user does not exist.',
            'user_id.different' => 'Cannot assign coupon to yourself.',
            'assigned_by.required' => 'Assigning admin ID is required.',
            'assigned_by.exists' => 'The assigning admin does not exist.',
            'assignment_reason.required' => 'Assignment reason is required.',
            'assignment_reason.min' => 'Assignment reason must be at least 3 characters.',
            'assignment_reason.max' => 'Assignment reason must not exceed 500 characters.',
            'assignment_reason.regex' => 'Assignment reason cannot be empty or just whitespace.',
            'assignment_notes.max' => 'Assignment notes must not exceed 1000 characters.',
            'times_can_be_used.min' => 'Times can be used must be at least 1.',
            'quantity.min' => 'Quantity must be at least 1.',
        ];
    }

    /**
     * Override from() to ensure validation happens
     */
    public static function from(mixed ...$payloads): static
    {
        $payload = $payloads[0] ?? [];
        
        // Validate the data first
        static::validate($payload);
        
        // Then create the object
        return parent::from($payload);
    }

    /**
     * Transform the data before validation
     */
    public static function prepareForPipeline(array $properties): array
    {
        // Trim whitespace from string fields
        if (isset($properties['assignment_reason'])) {
            $properties['assignment_reason'] = trim($properties['assignment_reason']);
        }
        
        if (isset($properties['assignment_notes']) && is_string($properties['assignment_notes'])) {
            $properties['assignment_notes'] = trim($properties['assignment_notes']);
            // Convert empty string to null
            if ($properties['assignment_notes'] === '') {
                $properties['assignment_notes'] = null;
            }
        }

        return $properties;
    }
}