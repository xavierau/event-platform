<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\PromotionalModal\Models\PromotionalModal;
use App\Modules\PromotionalModal\Models\PromotionalModalImpression;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\PromotionalModal\Models\PromotionalModalImpression>
 */
class PromotionalModalImpressionFactory extends Factory
{
    protected $model = PromotionalModalImpression::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'promotional_modal_id' => PromotionalModal::factory(),
            'user_id' => $this->faker->optional(0.7)->randomElement(User::pluck('id')->toArray() ?: [User::factory()]),
            'session_id' => $this->faker->optional(0.8)->uuid(),
            'action' => $this->faker->randomElement(['impression', 'click', 'dismiss']),
            'page_url' => $this->faker->randomElement([
                'https://example.com/',
                'https://example.com/events',
                'https://example.com/events/123',
                'https://example.com/profile',
                'https://example.com/about',
            ]),
            'metadata' => $this->faker->optional(0.3)->randomElement([
                null,
                ['source' => 'homepage_banner'],
                ['source' => 'modal', 'position' => 'center'],
                ['referrer' => 'google.com'],
                ['utm_source' => 'email', 'utm_campaign' => 'promo2024'],
            ]),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'created_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
        ];
    }

    /**
     * Indicate that the impression is an impression action.
     */
    public function impression(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'impression',
        ]);
    }

    /**
     * Indicate that the impression is a click action.
     */
    public function click(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'click',
        ]);
    }

    /**
     * Indicate that the impression is a dismiss action.
     */
    public function dismiss(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'dismiss',
        ]);
    }

    /**
     * Indicate that the impression is for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'session_id' => null,
        ]);
    }

    /**
     * Indicate that the impression is for anonymous user.
     */
    public function anonymous(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'session_id' => $this->faker->uuid(),
        ]);
    }

    /**
     * Indicate that the impression happened recently.
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Indicate that the impression happened on a specific page.
     */
    public function onPage(string $page): static
    {
        return $this->state(fn (array $attributes) => [
            'page_url' => "https://example.com/{$page}",
        ]);
    }
}
