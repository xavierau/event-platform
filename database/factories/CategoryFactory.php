<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Category::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categoryName = $this->faker->words(2, true);

        return [
            'name' => [
                'en' => ucwords($categoryName),
                'zh-TW' => $this->faker->word(),
            ],
            'slug' => $this->faker->unique()->slug(2),
            'parent_id' => null,
        ];
    }
}
