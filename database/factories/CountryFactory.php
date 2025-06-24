<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Country>
 */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Use firstOrCreate approach to avoid duplicates with seeded countries
        $countries = [
            ['name' => 'United States', 'iso2' => 'US', 'iso3' => 'USA', 'phone' => '+1'],
            ['name' => 'Canada', 'iso2' => 'CA', 'iso3' => 'CAN', 'phone' => '+1'],
            ['name' => 'United Kingdom', 'iso2' => 'GB', 'iso3' => 'GBR', 'phone' => '+44'],
            ['name' => 'Germany', 'iso2' => 'DE', 'iso3' => 'DEU', 'phone' => '+49'],
            ['name' => 'France', 'iso2' => 'FR', 'iso3' => 'FRA', 'phone' => '+33'],
            ['name' => 'Japan', 'iso2' => 'JP', 'iso3' => 'JPN', 'phone' => '+81'],
            ['name' => 'Australia', 'iso2' => 'AU', 'iso3' => 'AUS', 'phone' => '+61'],
            ['name' => 'Brazil', 'iso2' => 'BR', 'iso3' => 'BRA', 'phone' => '+55'],
            ['name' => 'India', 'iso2' => 'IN', 'iso3' => 'IND', 'phone' => '+91'],
            ['name' => 'China', 'iso2' => 'CN', 'iso3' => 'CHN', 'phone' => '+86'],
        ];

        $randomCountry = fake()->randomElement($countries);

        return [
            'name' => ['en' => $randomCountry['name']],
            'iso_code_2' => $randomCountry['iso2'],
            'iso_code_3' => $randomCountry['iso3'],
            'phone_code' => $randomCountry['phone'],
            'is_active' => true,
        ];
    }

    /**
     * Use existing country instead of creating duplicates.
     */
    public function existing(): static
    {
        return $this->state(function (array $attributes) {
            // Try to find an existing country first
            $existing = Country::inRandomOrder()->first();

            if ($existing) {
                return [
                    'name' => $existing->name,
                    'iso_code_2' => $existing->iso_code_2,
                    'iso_code_3' => $existing->iso_code_3,
                    'phone_code' => $existing->phone_code,
                    'is_active' => $existing->is_active,
                ];
            }

            // Fallback to default definition if no countries exist
            return [];
        });
    }
}
