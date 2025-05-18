<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nameEn = $this->faker->sentence(3);
        $attributes = [
            'organizer_id' => User::factory(), // Assumes UserFactory exists
            'category_id' => Category::factory(), // Assumes CategoryFactory exists and is defined
            'name' => ['en' => $nameEn],
            'slug' => ['en' => $this->faker->slug(3)],
            'description' => ['en' => $this->faker->paragraph],
            'short_summary' => ['en' => $this->faker->sentence],
            'event_status' => 'draft',
            'visibility' => 'public',
            'is_featured' => $this->faker->boolean,
            'contact_email' => $this->faker->safeEmail,
            'contact_phone' => $this->faker->phoneNumber,
            'website_url' => $this->faker->url,
            'cancellation_policy' => ['en' => $this->faker->sentence],
            'published_at' => null,
            'meta_title' => ['en' => $nameEn],
            'meta_description' => ['en' => $this->faker->sentence],
            'meta_keywords' => ['en' => implode(', ', $this->faker->words(3))],
            // 'created_by' => User::factory(), // If you have these fields
            // 'updated_by' => User::factory(),
        ];

        unset($attributes['status']); // Explicitly remove 'status' if it exists

        return $attributes;
    }
}
