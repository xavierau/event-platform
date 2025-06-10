<?php

namespace Database\Factories\Modules\Membership\Models;

use App\Models\User;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\Membership\Models\UserMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserMembershipFactory extends Factory
{
    protected $model = UserMembership::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'membership_level_id' => MembershipLevel::factory(),
            'started_at' => now(),
            'expires_at' => now()->addMonth(),
            'status' => 'active',
            'payment_method' => 'stripe',
            'auto_renew' => false,
        ];
    }
}
