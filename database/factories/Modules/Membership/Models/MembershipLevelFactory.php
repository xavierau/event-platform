<?php

namespace Database\Factories\Modules\Membership\Models;

use App\Modules\Membership\Models\MembershipLevel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MembershipLevelFactory extends Factory
{
    protected $model = MembershipLevel::class;

    public function definition(): array
    {
        $name = ['en' => $this->faker->words(2, true)];

        return [
            'name' => $name,
            'slug' => Str::slug($name['en']),
            'description' => ['en' => $this->faker->sentence],
            'price' => $this->faker->numberBetween(1000, 5000),
            'duration_months' => $this->faker->randomElement([1, 6, 12]),
            'is_active' => true,
        ];
    }
}
