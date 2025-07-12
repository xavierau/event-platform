<?php

namespace App\Services;

use App\Models\User;

class UserService
{
    /**
     * Block a user from commenting.
     *
     * @param User $user
     * @return User
     */
    public function blockUserCommenting(User $user): User
    {
        $user->update(['is_commenting_blocked' => true]);
        return $user->fresh();
    }

    /**
     * Unblock a user from commenting.
     *
     * @param User $user
     * @return User
     */
    public function unblockUserCommenting(User $user): User
    {
        $user->update(['is_commenting_blocked' => false]);
        return $user->fresh();
    }
}
