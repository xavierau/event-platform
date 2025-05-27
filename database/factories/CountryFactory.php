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
        return [
            'name' => ['en' => $this->faker->country],
            'iso_code_2' => $this->faker->unique()->countryCode,
            'iso_code_3' => $this->faker->unique()->countryISOAlpha3,
            'phone_code' => $this->faker->numerify('+###'),
            'is_active' => true,
        ];
    }
}
