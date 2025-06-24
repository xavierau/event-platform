<?php

namespace Database\Factories\Modules\Coupon;

use App\Models\User;
use App\Modules\Coupon\Enums\UserCouponStatusEnum;
use App\Modules\Coupon\Models\Coupon;
use App\Modules\Coupon\Models\UserCoupon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Coupon\Models\UserCoupon>
 */
class UserCouponFactory extends Factory
{
    protected $model = UserCoupon::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'coupon_id' => Coupon::factory(),
            'unique_code' => Str::random(12),
            'status' => $this->faker->randomElement(UserCouponStatusEnum::cases())->value,
            'times_can_be_used' => 1,
            'times_used' => 0,
            'expires_at' => now()->addMonths(1),
            'issued_at' => now(),
        ];
    }
}
