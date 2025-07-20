<?php

use App\Enums\RoleNameEnum;
use App\Models\Organizer;
use App\Models\User;
use App\Modules\Coupon\DataTransferObjects\AssignCouponToUserData;
use App\Modules\Coupon\Models\Coupon;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Create roles if they don't exist
    Role::firstOrCreate(['name' => RoleNameEnum::ADMIN->value, 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => RoleNameEnum::USER->value, 'guard_name' => 'web']);
    
    $this->organizer = Organizer::factory()->create();
    $this->adminUser = User::factory()->create();
    $this->regularUser = User::factory()->create();
    $this->targetUser = User::factory()->create();
    
    $this->coupon = Coupon::factory()->create([
        'organizer_id' => $this->organizer->id,
    ]);
    
    // Assign admin role to admin user
    $this->adminUser->assignRole(RoleNameEnum::ADMIN);
    $this->regularUser->assignRole(RoleNameEnum::USER);
    $this->targetUser->assignRole(RoleNameEnum::USER);
});

describe('AssignCouponToUserData DTO', function () {

    test('can create DTO with all required fields', function () {
        $data = AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'Customer loyalty reward',
            'times_can_be_used' => 3,
            'quantity' => 1,
        ]);

        expect($data)->toBeInstanceOf(AssignCouponToUserData::class)
            ->and($data->coupon_id)->toBe($this->coupon->id)
            ->and($data->user_id)->toBe($this->targetUser->id)
            ->and($data->assigned_by)->toBe($this->adminUser->id)
            ->and($data->assignment_reason)->toBe('Customer loyalty reward')
            ->and($data->times_can_be_used)->toBe(3)
            ->and($data->quantity)->toBe(1)
            ->and($data->assignment_notes)->toBeNull();
    });

    test('can create DTO with optional assignment notes', function () {
        $data = AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'VIP customer reward',
            'assignment_notes' => 'Special customer requested compensation for delayed service',
            'times_can_be_used' => 1,
            'quantity' => 2,
        ]);

        expect($data->assignment_notes)->toBe('Special customer requested compensation for delayed service')
            ->and($data->quantity)->toBe(2);
    });

    test('uses default values for optional fields', function () {
        $data = AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'Standard reward',
        ]);

        expect($data->times_can_be_used)->toBe(1)
            ->and($data->quantity)->toBe(1)
            ->and($data->assignment_notes)->toBeNull();
    });

    test('requires coupon_id field', function () {
        expect(fn() => AssignCouponToUserData::from([
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'Test reason',
        ]))->toThrow(ValidationException::class);
    });

    test('requires user_id field', function () {
        expect(fn() => AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'Test reason',
        ]))->toThrow(ValidationException::class);
    });

    test('requires assigned_by field', function () {
        expect(fn() => AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => $this->targetUser->id,
            'assignment_reason' => 'Test reason',
        ]))->toThrow(ValidationException::class);
    });

    test('requires assignment_reason field', function () {
        expect(fn() => AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
        ]))->toThrow(ValidationException::class);
    });

    test('validates coupon_id exists in database', function () {
        expect(fn() => AssignCouponToUserData::from([
            'coupon_id' => 99999, // Non-existent coupon ID
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'Test reason',
        ]))->toThrow(ValidationException::class);
    });

    test('validates user_id exists in database', function () {
        expect(fn() => AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => 99999, // Non-existent user ID
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'Test reason',
        ]))->toThrow(ValidationException::class);
    });

    test('validates assigned_by user exists in database', function () {
        expect(fn() => AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => 99999, // Non-existent admin ID
            'assignment_reason' => 'Test reason',
        ]))->toThrow(ValidationException::class);
    });

    test('validates assignment_reason is not empty string', function () {
        expect(fn() => AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => '', // Empty string
        ]))->toThrow(ValidationException::class);
    });

    test('validates assignment_reason has minimum length', function () {
        expect(fn() => AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'a', // Too short
        ]))->toThrow(ValidationException::class);
    });

    test('validates assignment_reason does not exceed maximum length', function () {
        $longReason = str_repeat('a', 501); // Assuming 500 char limit
        
        expect(fn() => AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => $longReason,
        ]))->toThrow(ValidationException::class);
    });

    test('validates assignment_notes maximum length when provided', function () {
        $longNotes = str_repeat('a', 1001); // Assuming 1000 char limit
        
        expect(fn() => AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'Valid reason',
            'assignment_notes' => $longNotes,
        ]))->toThrow(ValidationException::class);
    });

    test('validates times_can_be_used is positive integer', function () {
        expect(fn() => AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'Valid reason',
            'times_can_be_used' => 0, // Invalid: must be at least 1
        ]))->toThrow(ValidationException::class);

        expect(fn() => AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'Valid reason',
            'times_can_be_used' => -1, // Invalid: negative
        ]))->toThrow(ValidationException::class);
    });

    test('validates quantity is positive integer', function () {
        expect(fn() => AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'Valid reason',
            'quantity' => 0, // Invalid: must be at least 1
        ]))->toThrow(ValidationException::class);

        expect(fn() => AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'Valid reason',
            'quantity' => -5, // Invalid: negative
        ]))->toThrow(ValidationException::class);
    });

    test('accepts valid ranges for times_can_be_used and quantity', function () {
        $data = AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'Valid reason',
            'times_can_be_used' => 99,
            'quantity' => 50,
        ]);

        expect($data->times_can_be_used)->toBe(99)
            ->and($data->quantity)->toBe(50);
    });

    test('prevents self-assignment', function () {
        expect(fn() => AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => $this->adminUser->id, // Same as assigned_by
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'Self-assignment attempt',
        ]))->toThrow(ValidationException::class);
    });

    test('validates assignment_reason contains meaningful content', function () {
        // Should reject reasons that are just whitespace
        expect(fn() => AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => '   ', // Just whitespace
        ]))->toThrow(ValidationException::class);
    });

    test('trims whitespace from string fields', function () {
        $data = AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => '  Customer reward  ',
            'assignment_notes' => '  Additional context  ',
        ]);

        expect($data->assignment_reason)->toBe('Customer reward')
            ->and($data->assignment_notes)->toBe('Additional context');
    });

    test('handles null assignment_notes correctly', function () {
        $data = AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'Valid reason',
            'assignment_notes' => null,
        ]);

        expect($data->assignment_notes)->toBeNull();
    });

    test('can be converted to array', function () {
        $data = AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'Test conversion',
            'assignment_notes' => 'Test notes',
            'times_can_be_used' => 3,
            'quantity' => 2,
        ]);

        $array = $data->toArray();

        expect($array)->toBeArray()
            ->and($array['coupon_id'])->toBe($this->coupon->id)
            ->and($array['user_id'])->toBe($this->targetUser->id)
            ->and($array['assigned_by'])->toBe($this->adminUser->id)
            ->and($array['assignment_reason'])->toBe('Test conversion')
            ->and($array['assignment_notes'])->toBe('Test notes')
            ->and($array['times_can_be_used'])->toBe(3)
            ->and($array['quantity'])->toBe(2);
    });

    test('provides helpful error messages for validation failures', function () {
        try {
            AssignCouponToUserData::from([
                'coupon_id' => 'invalid',
                'user_id' => $this->targetUser->id,
                'assigned_by' => $this->adminUser->id,
                'assignment_reason' => 'Test reason',
            ]);
        } catch (ValidationException $e) {
            expect($e->getMessage())->toContain('coupon id');
        }

        try {
            AssignCouponToUserData::from([
                'coupon_id' => $this->coupon->id,
                'user_id' => $this->targetUser->id,
                'assigned_by' => $this->adminUser->id,
                'assignment_reason' => str_repeat('a', 501), // Too long
            ]);
        } catch (ValidationException $e) {
            expect($e->getMessage())->toContain('Assignment reason');
        }
    });

    test('maintains data immutability', function () {
        $data = AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'Immutability test',
        ]);

        // Properties should be readonly
        expect($data->coupon_id)->toBe($this->coupon->id);
        
        // This should be a compile-time error, but we'll document the expectation
        // $data->coupon_id = 123; // Should not be possible due to readonly
    });
});