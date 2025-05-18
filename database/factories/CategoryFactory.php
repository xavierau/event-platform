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
        return [
            'name' => ['en' => $this->faker->words(2, true)], // Default to English for testing
            // Add other locales if needed for specific tests, e.g., 'zh-TW' => '測試分類'
            'slug' => $this->faker->slug(2),
            'parent_id' => null, // Default to no parent
            // Add any other required fields for Category model
        ];
    }
}
