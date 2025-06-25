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

<<<<<<< HEAD
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
=======
>>>>>>> feature/coupon-module
    public function definition(): array
    {
        return [
            'organizer_id' => Organizer::factory(),
            'name' => $this->faker->words(3, true),
<<<<<<< HEAD
            'description' => $this->faker->sentence,
            'code' => $this->faker->unique()->word,
            'type' => $this->faker->randomElement(CouponTypeEnum::cases())->value,
            'discount_value' => $this->faker->numberBetween(10, 50),
            'discount_type' => 'fixed',
            'max_issuance' => $this->faker->optional()->numberBetween(100, 1000),
            'valid_from' => now(),
            'expires_at' => now()->addMonths(3),
            'redemption_methods' => ['qr'], // Default to QR only
            'merchant_pin' => null,
        ];
    }

    /**
     * Create a coupon with PIN redemption enabled
     */
    public function withPin(string $pin = '123456'): static
    {
        return $this->state(fn(array $attributes) => [
            'redemption_methods' => ['pin'],
            'merchant_pin' => $pin,
        ]);
    }

    /**
     * Create a coupon with both QR and PIN redemption
     */
    public function withBothMethods(string $pin = '123456'): static
    {
        return $this->state(fn(array $attributes) => [
            'redemption_methods' => ['qr', 'pin'],
            'merchant_pin' => $pin,
=======
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
>>>>>>> feature/coupon-module
        ]);
    }
}
