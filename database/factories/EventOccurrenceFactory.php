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
        $startDate = $this->faker->dateTimeBetween('+1 day', '+1 month');
        $endDate = $this->faker->dateTimeBetween($startDate, '+2 months');

        return [
            'event_id' => Event::factory(),
            'venue_id' => Venue::factory(), // Or null if it can be an online event without a venue initially
            'name' => ['en' => $this->faker->optional()->sentence(4)], // Optional, as per task EVT-002
            'description' => ['en' => $this->faker->optional()->paragraph],
            'start_at' => $startDate->format('Y-m-d H:i:s'),
            'end_at' => $endDate->format('Y-m-d H:i:s'),
            // Remove default values for start_at_utc and end_at_utc to allow proper override
            'start_at_utc' => $startDate,
            'end_at_utc' => $endDate,
            'timezone' => $this->faker->timezone,
            'is_online' => $this->faker->boolean,
            'online_meeting_link' => $this->faker->optional()->url,
            'capacity' => $this->faker->optional()->numberBetween(10, 200),
            'max_tickets_per_user' => $this->faker->optional()->numberBetween(1, 10),
            'parent_occurrence_id' => null,
            'status' => 'scheduled', // Add default status
        ];
    }
}
