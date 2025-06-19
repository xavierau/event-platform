<?php

namespace Database\Factories;

use App\Models\Venue;
use App\Models\Organizer; // For organizer_id
use App\Models\Country; // Assuming Country model & factory exist
use App\Models\State;   // Assuming State model & factory exist
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Venue>
 */
class VenueFactory extends Factory
{
    protected $model = Venue::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nameEn = $this->faker->company;
        return [
            'organizer_id' => null, // Public venue by default, can be overridden with Organizer::factory()
            'country_id' => Country::factory(), // Assuming CountryFactory exists
            'state_id' => State::factory(), // Assuming StateFactory exists and state is linked to country
            'name' => ['en' => $nameEn],
            'slug' => strtolower(str_replace(' ', '-', $nameEn)) . '-' . $this->faker->unique()->randomNumber(5), // Ensure uniqueness for tests
            'description' => ['en' => $this->faker->optional()->paragraph],
            'address_line_1' => ['en' => $this->faker->streetAddress],
            'address_line_2' => ['en' => $this->faker->optional()->secondaryAddress],
            'city' => ['en' => $this->faker->city],
            'postal_code' => $this->faker->postcode,
            'latitude' => $this->faker->latitude,
            'longitude' => $this->faker->longitude,
            'website_url' => $this->faker->optional()->url,
            'contact_phone' => $this->faker->optional()->phoneNumber,
            'contact_email' => $this->faker->optional()->safeEmail,
            'seating_capacity' => $this->faker->optional()->numberBetween(50, 1000),
            'is_active' => true,
            // 'created_by' => User::factory(), // If you have these fields
            // 'updated_by' => User::factory(),
        ];
    }

    /**
     * Create an organizer-specific venue.
     */
    public function forOrganizer($organizer = null): static
    {
        return $this->state(fn(array $attributes) => [
            'organizer_id' => $organizer ?? Organizer::factory(),
        ]);
    }

    /**
     * Create a public venue (explicitly set organizer_id to null).
     */
    public function public(): static
    {
        return $this->state(fn(array $attributes) => [
            'organizer_id' => null,
        ]);
    }
}
