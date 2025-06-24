<?php

namespace Database\Factories\Modules\Coupon;

use App\Models\User;
use App\Modules\Coupon\Models\CouponUsageLog;
use App\Modules\Coupon\Models\UserCoupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Coupon\Models\CouponUsageLog>
 */
class CouponUsageLogFactory extends Factory
{
    protected $model = CouponUsageLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_coupon_id' => UserCoupon::factory(),
            'redeemed_by_user_id' => User::factory(),
            'redeemed_at' => now(),
            'context' => ['ip' => $this->faker->ipv4, 'user_agent' => $this->faker->userAgent],
        ];
    }
}
