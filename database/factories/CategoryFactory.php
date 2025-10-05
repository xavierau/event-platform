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
        $categoryName = 'Category '.mt_rand(1000, 9999);

        return [
            'name' => [
                'en' => $categoryName,
                'zh-TW' => '類別 '.mt_rand(1000, 9999),
            ],
            'slug' => 'category-'.mt_rand(1000, 9999).'-'.time(),
            'parent_id' => null,
        ];
    }
}
