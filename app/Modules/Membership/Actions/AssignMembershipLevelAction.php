<?php

namespace App\Modules\Membership\Actions;

use App\Models\User;
use App\Modules\Membership\Enums\MembershipStatus;
use App\Modules\Membership\Enums\PaymentMethod;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\Membership\Models\UserMembership;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AssignMembershipLevelAction
{
    /**
     * Assign a membership level to a user (admin grant).
     *
     * @param User $user
     * @param MembershipLevel $membershipLevel
     * @param int|null $durationMonths Optional custom duration, defaults to level's duration
     * @return UserMembership
     */
    public function execute(User $user, MembershipLevel $membershipLevel, ?int $durationMonths = null): UserMembership
    {
        return DB::transaction(function () use ($user, $membershipLevel, $durationMonths) {
            // Cancel any existing active membership
            $existingMembership = $user->currentMembership;
            if ($existingMembership) {
                $existingMembership->cancel();
            }

            // Use provided duration or default to membership level's duration
            $duration = $durationMonths ?? $membershipLevel->duration_months;
            
            // Create new membership
            return UserMembership::create([
                'user_id' => $user->id,
                'membership_level_id' => $membershipLevel->id,
                'started_at' => now(),
                'expires_at' => now()->addMonths($duration),
                'status' => MembershipStatus::ACTIVE,
                'payment_method' => PaymentMethod::ADMIN_GRANT,
                'transaction_reference' => null,
                'auto_renew' => false,
                'subscription_metadata' => [
                    'granted_by' => auth()->user()->id,
                    'granted_at' => now()->toIso8601String(),
                    'reason' => 'Admin grant from user management'
                ],
            ]);
        });
    }
}