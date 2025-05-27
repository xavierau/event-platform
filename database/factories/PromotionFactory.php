<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Promotion>
 */
class PromotionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => [
                'en' => fake()->sentence(3),
                'zh-TW' => fake()->sentence(3),
            ],
            'subtitle' => [
                'en' => fake()->sentence(6),
                'zh-TW' => fake()->sentence(6),
            ],
            'url' => fake()->url(),
            'is_active' => fake()->boolean(80), // 80% chance of being active
            'starts_at' => fake()->boolean(70) ? fake()->dateTimeBetween('-1 month', '+1 month') : null,
            'ends_at' => fake()->boolean(70) ? fake()->dateTimeBetween('+1 month', '+3 months') : null,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * Indicate that the promotion is active.
     */
    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);
    }

    /**
     * Indicate that the promotion is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the promotion is expired.
     */
    public function expired(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => true,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
        ]);
    }
}
