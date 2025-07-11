<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Organizer;
use App\Models\State;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Organizer>
 */
class OrganizerFactory extends Factory
{
    protected $model = Organizer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $companyNames = [
            'TechEvents Corp',
            'Music Fest Productions',
            'Conference Masters',
            'Stellar Events',
            'Harmony Productions',
            'Elite Event Management',
            'Creative Gatherings Inc',
            'Metropolitan Events',
            'Global Conference Group',
            'Prestige Event Solutions',
            'EventSolutions',
        ];

        $name = fake()->randomElement($companyNames);
        $slug = Str::slug($name) . '-' . uniqid();

        return [
            'name' => [
                'en' => $name,
                'zh-TW' => fake()->company(),
                'zh-CN' => fake()->company(),
            ],
            'slug' => $slug,
            'description' => [
                'en' => fake()->paragraph(3),
                'zh-TW' => fake()->sentence(10),
                'zh-CN' => fake()->sentence(10),
            ],
            'logo_path' => null, // Will be handled by media library
            'website_url' => fake()->optional()->url(),
            'contact_email' => fake()->companyEmail(),
            'contact_phone' => fake()->phoneNumber(),
            'social_media_links' => [
                'facebook' => fake()->optional()->url(),
                'twitter' => fake()->optional()->url(),
                'instagram' => fake()->optional()->url(),
                'linkedin' => fake()->optional()->url(),
            ],
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => fake()->optional()->secondaryAddress(),
            'city' => fake()->city(),
            'postal_code' => fake()->postcode(),
            'country_id' => function () {
                // Use existing country or create one if none exist
                return Country::inRandomOrder()->first()?->id ?? Country::factory()->create()->id;
            },
            'state_id' => function () {
                // Use existing state or create one if none exist
                return State::inRandomOrder()->first()?->id ?? State::factory()->create()->id;
            },
            'is_active' => fake()->boolean(90), // 90% active
            'contract_details' => [
                'contract_type' => fake()->randomElement(['standard', 'premium', 'enterprise']),
                'commission_rate' => fake()->randomFloat(2, 5, 15),
                'payment_terms' => fake()->randomElement(['net30', 'net15', 'immediate']),
                'special_conditions' => fake()->optional()->sentence(),
            ],
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the organizer is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create an organizer with minimal contact information.
     */
    public function minimal(): static
    {
        return $this->state(fn(array $attributes) => [
            'description' => null,
            'website_url' => null,
            'contact_phone' => null,
            'social_media_links' => null,
            'address_line_2' => null,
            'contract_details' => null,
        ]);
    }

    /**
     * Create an organizer with premium features.
     */
    public function premium(): static
    {
        return $this->state(fn(array $attributes) => [
            'contract_details' => [
                'contract_type' => 'premium',
                'commission_rate' => fake()->randomFloat(2, 3, 8),
                'payment_terms' => 'net15',
                'special_conditions' => 'Premium support and priority listing',
                'features' => ['priority_support', 'custom_branding', 'analytics_access'],
            ],
        ]);
    }

    /**
     * Create an organizer for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn(array $attributes) => [
            'created_by' => $user->id,
        ]);
    }
}
