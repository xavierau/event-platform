<?php

namespace Database\Factories;

use App\Models\EventOccurrence;
use App\Models\Event;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventOccurrence>
 */
class EventOccurrenceFactory extends Factory
{
    protected $model = EventOccurrence::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'venue_id' => Venue::factory(), // Or null if it can be an online event without a venue initially
            'name' => ['en' => $this->faker->optional()->sentence(4)], // Optional, as per task EVT-002
            'description' => ['en' => $this->faker->optional()->paragraph],
            'start_at' => $this->faker->dateTimeBetween('+1 day', '+1 month')->format('Y-m-d H:i:s'),
            'end_at' => $this->faker->dateTimeBetween('+1 month', '+2 months')->format('Y-m-d H:i:s'),
            // The test uses start_at_utc and end_at_utc directly, so ensure these are consistent or use accessors/mutators in model
            'start_at_utc' => $this->faker->dateTimeBetween('+1 day', '+1 month'),
            'end_at_utc' => $this->faker->dateTimeBetween('+1 month', '+2 months'),
            'timezone' => $this->faker->timezone,
            'is_online' => $this->faker->boolean,
            'online_meeting_link' => $this->faker->optional()->url,
            'capacity' => $this->faker->optional()->numberBetween(10, 200),
            'max_tickets_per_user' => $this->faker->optional()->numberBetween(1, 10),
            'parent_occurrence_id' => null,
        ];
    }
}
