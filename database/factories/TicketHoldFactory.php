<?php

namespace Database\Factories;

use App\Models\EventOccurrence;
use App\Models\Organizer;
use App\Models\User;
use App\Modules\TicketHold\Enums\HoldStatusEnum;
use App\Modules\TicketHold\Models\TicketHold;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\TicketHold\Models\TicketHold>
 */
class TicketHoldFactory extends Factory
{
    protected $model = TicketHold::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid()->toString(),
            'event_occurrence_id' => EventOccurrence::factory(),
            'organizer_id' => Organizer::factory(),
            'created_by' => User::factory(),
            'name' => $this->faker->words(3, true).' Hold',
            'description' => $this->faker->optional(0.5)->paragraph(),
            'internal_notes' => $this->faker->optional(0.3)->sentence(),
            'status' => HoldStatusEnum::ACTIVE,
            'expires_at' => $this->faker->optional(0.7)->dateTimeBetween('+1 day', '+1 month'),
            'released_at' => null,
            'released_by' => null,
        ];
    }

    /**
     * Indicate that the hold is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => HoldStatusEnum::ACTIVE,
            'expires_at' => $this->faker->dateTimeBetween('+1 day', '+1 month'),
            'released_at' => null,
            'released_by' => null,
        ]);
    }

    /**
     * Indicate that the hold is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => HoldStatusEnum::EXPIRED,
            'expires_at' => $this->faker->dateTimeBetween('-1 month', '-1 day'),
            'released_at' => null,
            'released_by' => null,
        ]);
    }

    /**
     * Indicate that the hold has been released.
     */
    public function released(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => HoldStatusEnum::RELEASED,
            'released_at' => now(),
            'released_by' => User::factory(),
        ]);
    }

    /**
     * Indicate that the hold is exhausted (all tickets purchased).
     */
    public function exhausted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => HoldStatusEnum::EXHAUSTED,
            'released_at' => null,
            'released_by' => null,
        ]);
    }

    /**
     * Create a hold without an expiration date (never expires).
     */
    public function neverExpires(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => null,
        ]);
    }

    /**
     * Create a hold for a specific event occurrence.
     */
    public function forOccurrence(EventOccurrence $occurrence): static
    {
        return $this->state(fn (array $attributes) => [
            'event_occurrence_id' => $occurrence->id,
        ]);
    }

    /**
     * Create a hold for a specific organizer.
     */
    public function forOrganizer(Organizer $organizer): static
    {
        return $this->state(fn (array $attributes) => [
            'organizer_id' => $organizer->id,
        ]);
    }

    /**
     * Create a hold created by a specific user.
     */
    public function createdBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $user->id,
        ]);
    }
}
