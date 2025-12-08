<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\TemporaryRegistration\Models\TemporaryRegistrationPage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TemporaryRegistrationPage>
 */
class TemporaryRegistrationPageFactory extends Factory
{
    protected $model = TemporaryRegistrationPage::class;

    public function definition(): array
    {
        return [
            'title' => [
                'en' => fake()->sentence(3),
                'zh-TW' => fake()->sentence(3),
                'zh-CN' => fake()->sentence(3),
            ],
            'description' => [
                'en' => fake()->paragraph(),
                'zh-TW' => fake()->paragraph(),
                'zh-CN' => fake()->paragraph(),
            ],
            'slug' => null,
            'token' => Str::random(32),
            'membership_level_id' => MembershipLevel::factory(),
            'expires_at' => null,
            'max_registrations' => null,
            'registrations_count' => 0,
            'is_active' => true,
            'use_slug' => false,
            'created_by' => User::factory(),
            'metadata' => null,
        ];
    }

    public function withSlug(string $slug = null): static
    {
        return $this->state(fn (array $attributes) => [
            'slug' => $slug ?? fake()->slug(),
            'use_slug' => true,
        ]);
    }

    public function available(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'expires_at' => now()->addDays(30),
            'max_registrations' => 100,
            'registrations_count' => 0,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);
    }

    public function full(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'max_registrations' => 10,
            'registrations_count' => 10,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withMaxRegistrations(int $max, int $current = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'max_registrations' => $max,
            'registrations_count' => $current,
        ]);
    }

    public function expiresAt(\DateTimeInterface $date): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => $date,
        ]);
    }
}
