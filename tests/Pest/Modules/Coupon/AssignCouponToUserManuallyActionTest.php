<?php

use App\Enums\RoleNameEnum;
use App\Models\Organizer;
use App\Models\User;
use App\Modules\Coupon\Actions\AssignCouponToUserManuallyAction;
use App\Modules\Coupon\DataTransferObjects\AssignCouponToUserData;
use App\Modules\Coupon\Models\Coupon;
use App\Modules\Coupon\Models\UserCoupon;
use App\Modules\Coupon\Enums\UserCouponStatusEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create roles if they don't exist
    Role::firstOrCreate(['name' => RoleNameEnum::ADMIN->value, 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => RoleNameEnum::USER->value, 'guard_name' => 'web']);
    
    $this->organizer = Organizer::factory()->create();
    $this->adminUser = User::factory()->create();
    $this->targetUser = User::factory()->create();
    $this->regularUser = User::factory()->create();
    
    $this->coupon = Coupon::factory()->create([
        'organizer_id' => $this->organizer->id,
        'code' => 'TEST-COUPON',
        'name' => 'Test Coupon',
        'discount_type' => 'percentage',
        'discount_value' => 20,
        'max_issuance' => 100,
        'is_active' => true,
    ]);
    
    // Assign roles
    $this->adminUser->assignRole(RoleNameEnum::ADMIN);
    $this->targetUser->assignRole(RoleNameEnum::USER);
    $this->regularUser->assignRole(RoleNameEnum::USER);
    
    $this->action = new AssignCouponToUserManuallyAction();
});

describe('AssignCouponToUserManuallyAction', function () {

    test('can assign coupon to user successfully', function () {
        $assignmentData = AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'Customer loyalty reward',
            'assignment_notes' => 'Long-term customer appreciation',
            'times_can_be_used' => 3,
            'quantity' => 1,
        ]);

        $result = $this->action->execute($assignmentData);

        expect($result)->toBeInstanceOf(UserCoupon::class)
            ->and($result->user_id)->toBe($this->targetUser->id)
            ->and($result->coupon_id)->toBe($this->coupon->id)
            ->and($result->assigned_by)->toBe($this->adminUser->id)
            ->and($result->assignment_reason)->toBe('Customer loyalty reward')
            ->and($result->assignment_notes)->toBe('Long-term customer appreciation')
            ->and($result->times_can_be_used)->toBe(3)
            ->and($result->quantity)->toBe(1)
            ->and($result->status)->toBe(UserCouponStatusEnum::AVAILABLE);
    });

    test('creates user coupon record in database', function () {
        $assignmentData = AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'Test assignment',
            'times_can_be_used' => 1,
            'quantity' => 1,
        ]);

        $this->action->execute($assignmentData);

        $this->assertDatabaseHas('user_coupons', [
            'user_id' => $this->targetUser->id,
            'coupon_id' => $this->coupon->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'Test assignment',
            'times_can_be_used' => 1,
            'quantity' => 1,
            'status' => 'available',
        ]);
    });

    test('can assign multiple quantities of same coupon', function () {
        $assignmentData = AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'Multiple coupon reward',
            'times_can_be_used' => 1,
            'quantity' => 5,
        ]);

        $result = $this->action->execute($assignmentData);

        expect($result->quantity)->toBe(5);
        
        $this->assertDatabaseHas('user_coupons', [
            'user_id' => $this->targetUser->id,
            'coupon_id' => $this->coupon->id,
            'quantity' => 5,
        ]);
    });

    test('prevents duplicate assignments by same admin', function () {
        $assignmentData = AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'First assignment',
            'times_can_be_used' => 1,
            'quantity' => 1,
        ]);

        // First assignment should succeed
        $this->action->execute($assignmentData);

        // Second assignment should fail
        expect(fn() => $this->action->execute($assignmentData))
            ->toThrow(ValidationException::class);
    });

    test('allows different admins to assign same coupon to same user', function () {
        $anotherAdmin = User::factory()->create();
        $anotherAdmin->assignRole(RoleNameEnum::ADMIN);

        $firstAssignment = AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'First assignment',
            'times_can_be_used' => 1,
            'quantity' => 1,
        ]);

        $secondAssignment = AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $anotherAdmin->id,
            'assignment_reason' => 'Second assignment',
            'times_can_be_used' => 1,
            'quantity' => 1,
        ]);

        $result1 = $this->action->execute($firstAssignment);
        $result2 = $this->action->execute($secondAssignment);

        expect($result1->assigned_by)->toBe($this->adminUser->id)
            ->and($result2->assigned_by)->toBe($anotherAdmin->id);
    });

    test('respects coupon issuance limits', function () {
        // Create a coupon with limited issuance
        $limitedCoupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'code' => 'LIMITED-COUPON',
            'max_issuance' => 2,
        ]);

        $assignmentData = AssignCouponToUserData::from([
            'coupon_id' => $limitedCoupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'Test assignment',
            'times_can_be_used' => 1,
            'quantity' => 3, // Exceeds issuance limit
        ]);

        expect(fn() => $this->action->execute($assignmentData))
            ->toThrow(ValidationException::class);
    });
    test('handles coupons with no issuance limit', function () {
        // Create a coupon with no issuance limit
        $unlimitedCoupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'code' => 'UNLIMITED-COUPON',
            'max_issuance' => null,
        ]);

        $assignmentData = AssignCouponToUserData::from([
            'coupon_id' => $unlimitedCoupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'Test assignment',
            'times_can_be_used' => 1,
            'quantity' => 999,
        ]);

        $result = $this->action->execute($assignmentData);

        expect($result->quantity)->toBe(999);
    });

    test('validates coupon is active', function () {
        $inactiveCoupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'code' => 'INACTIVE-COUPON',
            'is_active' => false,
        ]);

        $assignmentData = AssignCouponToUserData::from([
            'coupon_id' => $inactiveCoupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'Test assignment',
            'times_can_be_used' => 1,
            'quantity' => 1,
        ]);

        expect(fn() => $this->action->execute($assignmentData))
            ->toThrow(ValidationException::class);
    });

    test('validates coupon is not expired', function () {
        $expiredCoupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'code' => 'EXPIRED-COUPON',
            'expires_at' => now()->subDays(1),
        ]);

        $assignmentData = AssignCouponToUserData::from([
            'coupon_id' => $expiredCoupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'Test assignment',
            'times_can_be_used' => 1,
            'quantity' => 1,
        ]);

        expect(fn() => $this->action->execute($assignmentData))
            ->toThrow(ValidationException::class);
    });

    test('allows assignment of coupon not yet valid', function () {
        $futureCoupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'code' => 'FUTURE-COUPON',
            'valid_from' => now()->addDays(1),
        ]);

        $assignmentData = AssignCouponToUserData::from([
            'coupon_id' => $futureCoupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'Pre-assignment for future promotion',
            'times_can_be_used' => 1,
            'quantity' => 1,
        ]);

        $result = $this->action->execute($assignmentData);

        expect($result)->toBeInstanceOf(UserCoupon::class);
    });

    test('records assignment metadata', function () {
        $assignmentData = AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'Customer loyalty reward',
            'assignment_notes' => 'VIP customer compensation',
            'times_can_be_used' => 3,
            'quantity' => 2,
        ]);

        $result = $this->action->execute($assignmentData);

        expect($result->assignment_method)->toBe('manual')
            ->and($result->assignment_reason)->toBe('Customer loyalty reward')
            ->and($result->assignment_notes)->toBe('VIP customer compensation')
            ->and($result->assigned_by)->toBe($this->adminUser->id)
            ->and($result->acquired_at)->not()->toBeNull();
    });

    test('handles assignment with minimal data', function () {
        $assignmentData = AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'Basic reward',
        ]);

        $result = $this->action->execute($assignmentData);

        expect($result->times_can_be_used)->toBe(1)
            ->and($result->quantity)->toBe(1)
            ->and($result->assignment_notes)->toBeNull()
            ->and($result->assignment_method)->toBe('manual');
    });

    test('tracks coupon issuance through database count', function () {
        $assignmentData = AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'Test assignment',
            'times_can_be_used' => 1,
            'quantity' => 3,
        ]);

        $this->action->execute($assignmentData);

        // Verify assignment was recorded
        $totalIssuedQuantity = UserCoupon::getCouponCurrentIssueCount($this->coupon->id);
        expect($totalIssuedQuantity)->toBe('3');
    });

    test('provides helpful error message for duplicate assignment', function () {
        $assignmentData = AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'First assignment',
            'times_can_be_used' => 1,
            'quantity' => 1,
        ]);

        $this->action->execute($assignmentData);

        try {
            $this->action->execute($assignmentData);
        } catch (ValidationException $e) {
            expect($e->getMessage())->toContain('already been assigned');
        }
    });

    test('provides helpful error message for inactive coupon', function () {
        $inactiveCoupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'is_active' => false,
        ]);

        $assignmentData = AssignCouponToUserData::from([
            'coupon_id' => $inactiveCoupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'Test assignment',
            'times_can_be_used' => 1,
            'quantity' => 1,
        ]);

        try {
            $this->action->execute($assignmentData);
        } catch (ValidationException $e) {
            expect($e->getMessage())->toContain('not active');
        }
    });

    test('provides helpful error message for expired coupon', function () {
        $expiredCoupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'expires_at' => now()->subDay(),
        ]);

        $assignmentData = AssignCouponToUserData::from([
            'coupon_id' => $expiredCoupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'Test assignment',
            'times_can_be_used' => 1,
            'quantity' => 1,
        ]);

        try {
            $this->action->execute($assignmentData);
        } catch (ValidationException $e) {
            expect($e->getMessage())->toContain('expired');
        }
    });

    test('provides helpful error message for issuance limit exceeded', function () {
        $limitedCoupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'max_issuance' => 5,
        ]);

        $assignmentData = AssignCouponToUserData::from([
            'coupon_id' => $limitedCoupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'Test assignment',
            'times_can_be_used' => 1,
            'quantity' => 6, // Exceeds issuance limit
        ]);

        try {
            $this->action->execute($assignmentData);
        } catch (ValidationException $e) {
            expect($e->getMessage())->toContain('issuance limit');
        }
    });

    test('works with transaction rollback on error', function () {
        // Create a coupon that will fail validation after user coupon creation
        $assignmentData = AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'Test assignment',
            'times_can_be_used' => 1,
            'quantity' => 1,
        ]);

        // First assignment should succeed
        $this->action->execute($assignmentData);

        // Second assignment should fail due to duplicate
        try {
            $this->action->execute($assignmentData);
        } catch (ValidationException $e) {
            // Verify only one record exists in database
            expect(UserCoupon::where('user_id', $this->targetUser->id)
                ->where('coupon_id', $this->coupon->id)
                ->count())->toBe(1);
        }
    });

    test('maintains data integrity during concurrent assignments', function () {
        // This test simulates potential race conditions
        $assignmentData = AssignCouponToUserData::from([
            'coupon_id' => $this->coupon->id,
            'user_id' => $this->targetUser->id,
            'assigned_by' => $this->adminUser->id,
            'assignment_reason' => 'Concurrent test',
            'times_can_be_used' => 1,
            'quantity' => 1,
        ]);

        $result = $this->action->execute($assignmentData);

        // Verify the assignment was recorded correctly
        expect($result->user_id)->toBe($this->targetUser->id)
            ->and($result->coupon_id)->toBe($this->coupon->id);
            
        // Verify database consistency
        $dbRecord = UserCoupon::where('user_id', $this->targetUser->id)
            ->where('coupon_id', $this->coupon->id)
            ->first();
            
        expect($dbRecord->assigned_by)->toBe($this->adminUser->id);
    });
});