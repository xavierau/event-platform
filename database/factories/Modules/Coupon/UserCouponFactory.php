<?php

namespace Database\Factories\Modules\Coupon;

use App\Models\User;
use App\Modules\Coupon\Enums\UserCouponStatusEnum;
use App\Modules\Coupon\Models\Coupon;
use App\Modules\Coupon\Models\UserCoupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Coupon\Models\UserCoupon>
 */
class UserCouponFactory extends Factory
{
    protected $model = UserCoupon::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'coupon_id' => Coupon::factory(),
            'unique_code' => strtoupper($this->faker->unique()->bothify('########????')),
            'status' => UserCouponStatusEnum::ACTIVE,
            'times_can_be_used' => $this->faker->numberBetween(1, 10),
            'times_used' => 0,
            'expires_at' => $this->faker->optional()->dateTimeBetween('+1 week', '+1 month'),
            'issued_at' => now(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => UserCouponStatusEnum::ACTIVE,
            'times_used' => 0,
        ]);
    }

    public function fullyUsed(): static
    {
        return $this->state(function (array $attributes) {
            $timesCanBeUsed = $attributes['times_can_be_used'] ?? 1;
            return [
                'status' => UserCouponStatusEnum::FULLY_USED,
                'times_used' => $timesCanBeUsed,
            ];
        });
    }

    public function expired(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => UserCouponStatusEnum::EXPIRED,
            'expires_at' => now()->subDay(),
        ]);
    }

    public function withCode(string $code): static
    {
        return $this->state(fn(array $attributes) => [
            'unique_code' => $code,
        ]);
    }
}
