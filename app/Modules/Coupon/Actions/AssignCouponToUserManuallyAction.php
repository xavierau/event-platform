<?php

namespace App\Modules\Coupon\Actions;

use App\Modules\Coupon\DataTransferObjects\AssignCouponToUserData;
use App\Modules\Coupon\Models\Coupon;
use App\Modules\Coupon\Models\UserCoupon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignCouponToUserManuallyAction
{
    /**
     * Execute the manual coupon assignment
     */
    public function execute(AssignCouponToUserData $assignmentData): UserCoupon
    {
        return DB::transaction(function () use ($assignmentData) {
            // Load the coupon with locking to prevent race conditions
            $coupon = Coupon::lockForUpdate()->findOrFail($assignmentData->coupon_id);

            // Validate coupon eligibility
            $this->validateCouponEligibility($coupon, $assignmentData);

            // Check for duplicate assignments by the same admin
            $this->checkForDuplicateAssignment($assignmentData);

            // Create the user coupon assignment
            $userCoupon = $this->createUserCouponAssignment($assignmentData);

            return $userCoupon;
        });
    }

    /**
     * Validate that the coupon is eligible for assignment
     */
    private function validateCouponEligibility(Coupon $coupon, AssignCouponToUserData $assignmentData): void
    {
        // Check if coupon is active
        if (!$coupon->is_active) {
            throw ValidationException::withMessages([
                'coupon' => "The coupon '{$coupon->code}' is not active and cannot be assigned.",
            ]);
        }

        // Check if coupon is expired
        if ($coupon->expires_at && $coupon->expires_at < now()) {
            throw ValidationException::withMessages([
                'coupon' => "The coupon '{$coupon->code}' has expired and cannot be assigned.",
            ]);
        }

        // Check issuance limits (how many copies can be distributed)
        if ($coupon->max_issuance !== null) {
            if (!$coupon->hasEnoughIssuance($assignmentData->quantity) ) {
                throw ValidationException::withMessages([
                    'quantity' => "Cannot assign {$assignmentData->quantity} coupon(s). Only {$coupon->getRemainingIssuance()} copies remaining before reaching the maximum issuance limit of {$coupon->max_issuance}.",
                ]);
            }
        }
    }

    /**
     * Check for duplicate assignments by the same admin
     */
    private function checkForDuplicateAssignment(AssignCouponToUserData $assignmentData): void
    {
        $existingAssignment = UserCoupon::where('user_id', $assignmentData->user_id)
            ->where('coupon_id', $assignmentData->coupon_id)
            ->where('assigned_by', $assignmentData->assigned_by)
            ->exists();

        if ($existingAssignment) {
            throw ValidationException::withMessages([
                'assignment' => 'This coupon has already been assigned to this user by the same admin.',
            ]);
        }
    }

    /**
     * Create the user coupon assignment record
     */
    private function createUserCouponAssignment(AssignCouponToUserData $assignmentData): UserCoupon
    {
        return UserCoupon::create([
            'user_id' => $assignmentData->user_id,
            'coupon_id' => $assignmentData->coupon_id,
            'unique_code' => $this->generateUniqueCode(),
            'assigned_by' => $assignmentData->assigned_by,
            'assignment_method' => 'manual',
            'assignment_reason' => $assignmentData->assignment_reason,
            'assignment_notes' => $assignmentData->assignment_notes,
            'times_can_be_used' => $assignmentData->times_can_be_used,
            'quantity' => $assignmentData->quantity,
            'status' => 'available',
            'acquired_at' => now(),
        ]);
    }

    /**
     * Generate a unique code for the user coupon
     */
    private function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(bin2hex(random_bytes(6))); // Generate 12 character code
        } while (UserCoupon::where('unique_code', $code)->exists());

        return $code;
    }

}
