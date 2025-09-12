<?php

namespace App\Actions\Admin;

use App\Models\AdminAuditLog;
use App\Models\User;
use App\Modules\Membership\Actions\AssignMembershipLevelAction;
use App\Modules\Membership\Models\MembershipLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateUserByAdminAction
{
    public function __construct(
        private AssignMembershipLevelAction $assignMembershipLevelAction
    ) {
    }

    /**
     * Create a new user by admin with optional membership assignment.
     */
    public function execute(array $userData, ?int $membershipLevelId = null, ?int $customDurationMonths = null, ?string $reason = null): User
    {
        return DB::transaction(function () use ($userData, $membershipLevelId, $customDurationMonths, $reason) {
            // Create the user
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'mobile_number' => $userData['mobile_number'] ?? null,
                'password' => Hash::make($userData['password']),
                'email_verified_at' => now(), // Admin-created users are auto-verified
                'is_commenting_blocked' => false,
            ]);

            // Assign membership if specified
            $membershipAssigned = false;
            if ($membershipLevelId) {
                $membershipLevel = MembershipLevel::findOrFail($membershipLevelId);
                $this->assignMembershipLevelAction->execute($user, $membershipLevel, $customDurationMonths);
                $membershipAssigned = true;
            }

            // Create audit log
            $this->createAuditLog($user, $membershipLevelId, $customDurationMonths, $membershipAssigned, $reason);

            return $user->fresh();
        });
    }

    private function createAuditLog(
        User $user, 
        ?int $membershipLevelId, 
        ?int $customDurationMonths, 
        bool $membershipAssigned,
        ?string $reason
    ): void {
        $actionDetails = [
            'user_email' => $user->email,
            'user_name' => $user->name,
            'mobile_number' => $user->mobile_number,
            'membership_assigned' => $membershipAssigned,
        ];

        if ($membershipAssigned && $membershipLevelId) {
            $membershipLevel = MembershipLevel::find($membershipLevelId);
            $actionDetails['membership_level'] = [
                'id' => $membershipLevelId,
                'name' => $membershipLevel?->name,
                'custom_duration_months' => $customDurationMonths,
            ];
        }

        AdminAuditLog::create([
            'admin_user_id' => auth()->id(),
            'target_user_id' => $user->id,
            'action_type' => 'create_user',
            'action_details' => $actionDetails,
            'reason' => $reason ?? 'User created by admin',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}