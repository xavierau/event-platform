<?php

namespace Database\Factories\Modules\Coupon;

use App\Models\Organizer;
use App\Modules\Coupon\Enums\CouponTypeEnum;
use App\Modules\Coupon\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Coupon\Models\Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'organizer_id' => Organizer::factory(),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'code' => strtoupper($this->faker->unique()->bothify('???###')),
            'type' => $this->faker->randomElement(CouponTypeEnum::cases()),
            'discount_value' => $this->faker->numberBetween(5, 50),
            'discount_type' => $this->faker->randomElement(['fixed', 'percentage']),
            'max_issuance' => $this->faker->optional()->numberBetween(10, 1000),
            'valid_from' => $this->faker->optional(0.7)->dateTimeBetween('-1 week', 'now'),
            'expires_at' => $this->faker->optional(0.7)->dateTimeBetween('+1 week', '+1 month'),
        ];
    }

    public function singleUse(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => CouponTypeEnum::SINGLE_USE,
        ]);
    }

    public function multiUse(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => CouponTypeEnum::MULTI_USE,
        ]);
    }

    public function withValidPeriod(): static
    {
        return $this->state(fn(array $attributes) => [
            'valid_from' => now()->subDay(),
            'expires_at' => now()->addMonth(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn(array $attributes) => [
            'valid_from' => now()->subMonth(),
            'expires_at' => now()->subDay(),
        ]);
    }

    public function notYetValid(): static
    {
        return $this->state(fn(array $attributes) => [
            'valid_from' => now()->addDay(),
            'expires_at' => now()->addMonth(),
        ]);
    }
}
