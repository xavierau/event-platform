<?php

namespace App\Modules\Membership\Actions;

use App\Models\User;

class CancelMembershipAction
{
    public function execute(User $user): void
    {
        $membership = $user->membership;

        if ($membership) {
            $membership->update(['status' => 'cancelled']);
        }
    }
}
