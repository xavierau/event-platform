<?php

namespace Database\Factories;

use App\Models\State;
use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\State>
 */
class StateFactory extends Factory
{
    protected $model = State::class;

    public function definition(): array
    {
        return [
            'country_id' => function () {
                // Use existing country or create one if none exist
                return Country::inRandomOrder()->first()?->id ?? Country::factory()->create()->id;
            },
            'name' => ['en' => $this->faker->state],
            'code' => $this->faker->stateAbbr,
            'is_active' => true,
        ];
    }
}
