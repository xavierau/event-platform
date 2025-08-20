<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Comment>
 */
class CommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'commentable_type' => Event::class,
            'commentable_id' => Event::factory(),
            'content' => $this->faker->paragraph,
            'content_type' => 'plain',
            'status' => $this->faker->randomElement(['pending', 'approved', 'rejected']),
            'parent_id' => null,
            'votes_enabled' => true,
            'votes_up_count' => 0,
            'votes_down_count' => 0,
        ];
    }

    /**
     * Set a specific model as the commentable.
     */
    public function for($model, $relationship = null): static
    {
        if ($relationship === null && is_object($model)) {
            // If it's a direct model instance, set polymorphic fields
            return $this->state([
                'commentable_type' => get_class($model),
                'commentable_id' => $model->getKey(),
            ]);
        }
        
        // Use parent implementation for other cases
        return parent::for($model, $relationship);
    }
}
