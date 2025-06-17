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
        ];

        $name = $this->faker->randomElement($companyNames);
        $slug = Str::slug($name) . '-' . $this->faker->randomNumber(4);

        return [
            'name' => [
                'en' => $name,
                'zh-TW' => $this->faker->company(),
                'zh-CN' => $this->faker->company(),
            ],
            'slug' => $slug,
            'description' => [
                'en' => $this->faker->paragraph(3),
                'zh-TW' => $this->faker->realText(200),
                'zh-CN' => $this->faker->realText(200),
            ],
            'logo_path' => null, // Will be handled by media library
            'website_url' => $this->faker->optional()->url(),
            'contact_email' => $this->faker->companyEmail(),
            'contact_phone' => $this->faker->phoneNumber(),
            'social_media_links' => [
                'facebook' => $this->faker->optional()->url(),
                'twitter' => $this->faker->optional()->url(),
                'instagram' => $this->faker->optional()->url(),
                'linkedin' => $this->faker->optional()->url(),
            ],
            'address_line_1' => $this->faker->streetAddress(),
            'address_line_2' => $this->faker->optional()->secondaryAddress(),
            'city' => $this->faker->city(),
            'postal_code' => $this->faker->postcode(),
            'country_id' => Country::factory(),
            'state_id' => State::factory(),
            'is_active' => $this->faker->boolean(90), // 90% active
            'contract_details' => [
                'contract_type' => $this->faker->randomElement(['standard', 'premium', 'enterprise']),
                'commission_rate' => $this->faker->randomFloat(2, 5, 15),
                'payment_terms' => $this->faker->randomElement(['net30', 'net15', 'immediate']),
                'special_conditions' => $this->faker->optional()->sentence(),
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
                'commission_rate' => $this->faker->randomFloat(2, 3, 8),
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
