<?php

namespace App\Modules\Membership\Actions;

use App\Models\User;
use Carbon\Carbon;

class RenewMembershipAction
{
    public function execute(User $user): void
    {
        $membership = $user->membership;

        if ($membership) {
            $membership->update([
                'expires_at' => Carbon::parse($membership->expires_at)->addMonths($membership->level->duration_months),
            ]);
        }
    }
}
