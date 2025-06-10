<?php

namespace App\Modules\Membership\Actions;

use App\Modules\Membership\DataTransferObjects\MembershipPurchaseData;
use App\Models\User;
use App\Modules\Membership\Enums\MembershipStatus;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\Membership\Models\UserMembership;
use Carbon\Carbon;

class PurchaseMembershipAction
{
    public function execute(User $user, MembershipPurchaseData $data): UserMembership
    {
        $membershipLevel = MembershipLevel::findOrFail($data->membership_level_id);

        return UserMembership::create([
            'user_id' => $user->id,
            'membership_level_id' => $membershipLevel->id,
            'started_at' => now(),
            'expires_at' => now()->addMonths($membershipLevel->duration_months),
            'status' => MembershipStatus::ACTIVE,
            'payment_method' => $data->payment_method,
            'transaction_reference' => $data->transaction_reference,
            'auto_renew' => $data->auto_renew,
        ]);
    }
}
