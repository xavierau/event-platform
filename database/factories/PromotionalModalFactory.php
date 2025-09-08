<?php

namespace Database\Factories;

use App\Modules\PromotionalModal\Models\PromotionalModal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\PromotionalModal\Models\PromotionalModal>
 */
class PromotionalModalFactory extends Factory
{
    protected $model = PromotionalModal::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => [
                'en' => $this->faker->sentence(4),
                'zh-TW' => '促銷活動標題',
                'zh-CN' => '促销活动标题',
            ],
            'content' => [
                'en' => $this->faker->paragraph(3),
                'zh-TW' => '這是促銷活動的詳細內容描述，包含所有必要的資訊。',
                'zh-CN' => '这是促销活动的详细内容描述，包含所有必要的信息。',
            ],
            'type' => $this->faker->randomElement(['modal', 'banner']),
            'pages' => $this->faker->randomElement([
                null,
                ['home'],
                ['home', 'events'],
                ['events', 'event-detail'],
                ['all'],
            ]),
            'membership_levels' => $this->faker->randomElement([
                null,
                [1, 2],
                [3],
                [1, 2, 3],
            ]),
            'user_segments' => null,
            'start_at' => $this->faker->optional(0.7)->dateTimeBetween('-1 week', '+1 week'),
            'end_at' => $this->faker->optional(0.8)->dateTimeBetween('+1 week', '+1 month'),
            'display_frequency' => $this->faker->randomElement(['once', 'daily', 'weekly', 'always']),
            'cooldown_hours' => $this->faker->randomElement([1, 6, 12, 24, 48, 72]),
            'impressions_count' => $this->faker->numberBetween(0, 10000),
            'clicks_count' => function (array $attributes) {
                return $this->faker->numberBetween(0, (int)($attributes['impressions_count'] * 0.1));
            },
            'conversion_rate' => function (array $attributes) {
                if ($attributes['impressions_count'] > 0) {
                    return round(($attributes['clicks_count'] / $attributes['impressions_count']) * 100, 2);
                }
                return 0.00;
            },
            'is_active' => $this->faker->boolean(80),
            'priority' => $this->faker->numberBetween(0, 100),
            'sort_order' => $this->faker->numberBetween(0, 999),
            'button_text' => $this->faker->optional(0.8)->randomElement([
                'Learn More',
                'Get Started',
                'Sign Up Now',
                'View Details',
                'Join Now',
                '了解更多',
                '立即註冊',
                '查看詳情',
            ]),
            'button_url' => $this->faker->optional(0.8)->url(),
            'is_dismissible' => $this->faker->boolean(90),
            'display_conditions' => $this->faker->optional(0.3)->randomElement([
                null,
                ['device' => 'desktop'],
                ['device' => 'mobile'],
                ['time' => ['start' => '09:00', 'end' => '17:00']],
                ['location' => ['countries' => ['US', 'CA', 'TW']]],
            ]),
            'created_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'updated_at' => function (array $attributes) {
                return $this->faker->dateTimeBetween($attributes['created_at'], 'now');
            },
        ];
    }

    /**
     * Indicate that the modal should be active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'start_at' => $this->faker->dateTimeBetween('-1 week', '-1 hour'),
            'end_at' => $this->faker->dateTimeBetween('+1 hour', '+1 month'),
            'membership_levels' => null,
            'pages' => null,
        ]);
    }

    /**
     * Indicate that the modal should be inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the modal is a banner type.
     */
    public function banner(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'banner',
        ]);
    }

    /**
     * Indicate that the modal is a modal type.
     */
    public function modal(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'modal',
        ]);
    }

    /**
     * Indicate that the modal should display once only.
     */
    public function displayOnce(): static
    {
        return $this->state(fn (array $attributes) => [
            'display_frequency' => 'once',
            'cooldown_hours' => 24,
        ]);
    }

    /**
     * Indicate that the modal should display always.
     */
    public function displayAlways(): static
    {
        return $this->state(fn (array $attributes) => [
            'display_frequency' => 'always',
            'cooldown_hours' => 0,
        ]);
    }

    /**
     * Indicate that the modal has high priority.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => $this->faker->numberBetween(80, 100),
        ]);
    }

    /**
     * Indicate that the modal has low priority.
     */
    public function lowPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => $this->faker->numberBetween(0, 20),
        ]);
    }

    /**
     * Indicate that the modal has many impressions.
     */
    public function withManyImpressions(): static
    {
        return $this->state(fn (array $attributes) => [
            'impressions_count' => $this->faker->numberBetween(1000, 50000),
            'clicks_count' => function (array $attributes) {
                return $this->faker->numberBetween(50, (int)($attributes['impressions_count'] * 0.15));
            },
            'conversion_rate' => function (array $attributes) {
                return round(($attributes['clicks_count'] / $attributes['impressions_count']) * 100, 2);
            },
        ]);
    }

    /**
     * Indicate that the modal is for home page only.
     */
    public function forHomePage(): static
    {
        return $this->state(fn (array $attributes) => [
            'pages' => ['home'],
        ]);
    }

    /**
     * Indicate that the modal is for all pages.
     */
    public function forAllPages(): static
    {
        return $this->state(fn (array $attributes) => [
            'pages' => null,
        ]);
    }
}
