<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    /**
     * Define the model's default state.
     * Uses new Organizer entity by default.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nameEn = fake()->words(3, true);
        $attributes = [
            'organizer_id' => function () {
                // Use existing organizer or create one if none exist
                return Organizer::inRandomOrder()->first()?->id ?? Organizer::factory()->create()->id;
            },
            'category_id' => Category::factory(),
            'name' => ['en' => $nameEn],
            'slug' => ['en' => fake()->slug(3)],
            'description' => ['en' => fake()->paragraph()],
            'short_summary' => ['en' => fake()->sentence()],
            'event_status' => 'draft',
            'visibility' => 'public',
            'is_featured' => fake()->boolean(),
            'contact_email' => fake()->safeEmail(),
            'contact_phone' => fake()->phoneNumber(),
            'website_url' => fake()->url(),
            'cancellation_policy' => ['en' => fake()->sentence()],
            'published_at' => null,
            'meta_title' => ['en' => $nameEn],
            'meta_description' => ['en' => fake()->sentence()],
            'meta_keywords' => ['en' => implode(', ', fake()->words(3))],
            'visible_to_membership_levels' => null, // Public by default
            'action_type' => 'purchase_ticket', // Default action type
            // 'created_by' => User::factory(), // Commented out to avoid conflicts
            // 'updated_by' => User::factory(),
        ];

        return $attributes;
    }

    /**
     * Create an event with a specific organizer entity.
     */
    public function forOrganizer(Organizer $organizer): static
    {
        return $this->state(fn (array $attributes) => [
            'organizer_id' => $organizer->id,
        ]);
    }

    /**
     * Create an event with a new organizer entity.
     * This is the default behavior but provided for explicit usage.
     */
    public function withOrganizerEntity(): static
    {
        return $this->state(fn (array $attributes) => [
            'organizer_id' => function () {
                // Use existing organizer or create one if none exist
                return Organizer::inRandomOrder()->first()?->id ?? Organizer::factory()->create()->id;
            },
        ]);
    }

    /**
     * Create an event for testing with backward compatibility.
     * Creates a default organizer entity that can be used consistently in tests.
     *
     * @deprecated Use withOrganizerEntity() or forOrganizer() instead
     */
    public function forTesting(): static
    {
        return $this->state(fn (array $attributes) => [
            'organizer_id' => function () {
                // Use existing organizer or create one if none exist
                return Organizer::inRandomOrder()->first()?->id ?? Organizer::factory()->create()->id;
            },
        ]);
    }

    /**
     * Create a published event (for public display).
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_status' => 'published',
            'published_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    /**
     * Create a featured event.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    /**
     * Create a draft event.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_status' => 'draft',
            'published_at' => null,
        ]);
    }

    /**
     * Create an event with specific category.
     */
    public function inCategory(Category $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category->id,
        ]);
    }

    /**
     * Create an event with translatable content for multiple locales.
     */
    public function withMultiLanguageContent(): static
    {
        $nameEn = fake()->words(3, true);
        $nameZhTW = $this->faker->sentence(3);
        $nameZhCN = $this->faker->sentence(3);

        return $this->state(fn (array $attributes) => [
            'name' => [
                'en' => $nameEn,
                'zh-TW' => $nameZhTW,
                'zh-CN' => $nameZhCN,
            ],
            'slug' => [
                'en' => fake()->slug(3),
                'zh-TW' => fake()->slug(3),
                'zh-CN' => fake()->slug(3),
            ],
            'description' => [
                'en' => fake()->paragraph(),
                'zh-TW' => fake()->paragraph(),
                'zh-CN' => fake()->paragraph(),
            ],
            'short_summary' => [
                'en' => fake()->sentence(),
                'zh-TW' => fake()->sentence(),
                'zh-CN' => fake()->sentence(),
            ],
            'meta_title' => [
                'en' => $nameEn,
                'zh-TW' => $nameZhTW,
                'zh-CN' => $nameZhCN,
            ],
            'meta_description' => [
                'en' => fake()->sentence(),
                'zh-TW' => fake()->sentence(),
                'zh-CN' => fake()->sentence(),
            ],
        ]);
    }

    /**
     * Create an event restricted to specific membership levels.
     */
    public function restrictedToMembershipLevels(array $membershipLevelIds): static
    {
        return $this->state(fn (array $attributes) => [
            'visible_to_membership_levels' => $membershipLevelIds,
        ]);
    }

    /**
     * Create an event with member QR action type.
     */
    public function withMemberQrAction(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => 'show_member_qr',
        ]);
    }

    /**
     * Create an event with purchase ticket action type (default).
     */
    public function withPurchaseTicketAction(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => 'purchase_ticket',
        ]);
    }
}
