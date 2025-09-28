<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventSeo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventSeo>
 */
class EventSeoFactory extends Factory
{
    protected $model = EventSeo::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventTitle = fake()->words(3, true);
        $shortDescription = fake()->sentence(12);
        $keywords = implode(', ', fake()->words(5));

        return [
            'event_id' => Event::factory(),
            'meta_title' => [
                'en' => $eventTitle,
                'zh-TW' => fake()->words(3, true),
            ],
            'meta_description' => [
                'en' => $shortDescription,
                'zh-TW' => fake()->sentence(12),
            ],
            'keywords' => [
                'en' => $keywords,
                'zh-TW' => implode(', ', fake()->words(5)),
            ],
            'og_title' => [
                'en' => $eventTitle.' - Join Us!',
                'zh-TW' => fake()->words(3, true).' - 加入我們！',
            ],
            'og_description' => [
                'en' => $shortDescription,
                'zh-TW' => fake()->sentence(12),
            ],
            'og_image_url' => fake()->imageUrl(1200, 630, 'events', true),
            'is_active' => true,
        ];
    }

    /**
     * Create SEO settings for a specific event
     */
    public function forEvent(Event $event): static
    {
        return $this->state(fn (array $attributes) => [
            'event_id' => $event->id,
        ]);
    }

    /**
     * Create SEO settings with minimal English-only content
     */
    public function englishOnly(): static
    {
        $eventTitle = fake()->words(3, true);
        $shortDescription = fake()->sentence(12);

        return $this->state(fn (array $attributes) => [
            'meta_title' => ['en' => $eventTitle],
            'meta_description' => ['en' => $shortDescription],
            'keywords' => ['en' => implode(', ', fake()->words(5))],
            'og_title' => ['en' => $eventTitle],
            'og_description' => ['en' => $shortDescription],
        ]);
    }

    /**
     * Create SEO settings with content for multiple locales
     */
    public function multiLocale(): static
    {
        $eventTitleEn = fake()->words(3, true);
        $eventTitleZh = fake()->words(3, true);
        $descEn = fake()->sentence(12);
        $descZh = fake()->sentence(12);

        return $this->state(fn (array $attributes) => [
            'meta_title' => [
                'en' => $eventTitleEn,
                'zh-TW' => $eventTitleZh,
                'zh-CN' => fake()->words(3, true),
            ],
            'meta_description' => [
                'en' => $descEn,
                'zh-TW' => $descZh,
                'zh-CN' => fake()->sentence(12),
            ],
            'keywords' => [
                'en' => implode(', ', fake()->words(5)),
                'zh-TW' => implode(', ', fake()->words(5)),
                'zh-CN' => implode(', ', fake()->words(5)),
            ],
            'og_title' => [
                'en' => $eventTitleEn,
                'zh-TW' => $eventTitleZh,
                'zh-CN' => fake()->words(3, true),
            ],
            'og_description' => [
                'en' => $descEn,
                'zh-TW' => $descZh,
                'zh-CN' => fake()->sentence(12),
            ],
        ]);
    }

    /**
     * Create SEO settings with character limit violations for testing
     */
    public function withLongContent(): static
    {
        return $this->state(fn (array $attributes) => [
            'meta_title' => [
                'en' => str_repeat('Very Long Event Title ', 10), // Over 60 chars
            ],
            'meta_description' => [
                'en' => str_repeat('This is a very long description that exceeds the recommended character limit for meta descriptions. ', 3), // Over 160 chars
            ],
            'keywords' => [
                'en' => str_repeat('keyword, very-long-keyword-name, ', 20), // Over 255 chars
            ],
            'og_title' => [
                'en' => str_repeat('Very Long Open Graph Title ', 8), // Over 60 chars
            ],
            'og_description' => [
                'en' => str_repeat('This is a very long Open Graph description that exceeds limits. ', 5), // Over 160 chars
            ],
        ]);
    }

    /**
     * Create inactive SEO settings
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create SEO settings with only basic fields filled
     */
    public function minimal(): static
    {
        return $this->state(fn (array $attributes) => [
            'meta_title' => ['en' => fake()->words(3, true)],
            'meta_description' => null,
            'keywords' => null,
            'og_title' => null,
            'og_description' => null,
            'og_image_url' => null,
        ]);
    }

    /**
     * Create SEO settings optimized for search engines
     */
    public function optimized(): static
    {
        $title = fake()->words(3, true);
        $description = fake()->sentence(15); // Around 150 chars

        return $this->state(fn (array $attributes) => [
            'meta_title' => [
                'en' => substr($title, 0, 55), // Within 60 char limit
                'zh-TW' => substr(fake()->words(3, true), 0, 55),
            ],
            'meta_description' => [
                'en' => substr($description, 0, 155), // Within 160 char limit
                'zh-TW' => substr(fake()->sentence(15), 0, 155),
            ],
            'keywords' => [
                'en' => implode(', ', array_slice(fake()->words(8), 0, 8)), // Reasonable keyword count
                'zh-TW' => implode(', ', array_slice(fake()->words(8), 0, 8)),
            ],
            'og_title' => [
                'en' => substr($title, 0, 55),
                'zh-TW' => substr(fake()->words(3, true), 0, 55),
            ],
            'og_description' => [
                'en' => substr($description, 0, 155),
                'zh-TW' => substr(fake()->sentence(15), 0, 155),
            ],
            'og_image_url' => fake()->imageUrl(1200, 630, 'events', true),
            'is_active' => true,
        ]);
    }

    /**
     * Create SEO settings with no Open Graph image
     */
    public function withoutOgImage(): static
    {
        return $this->state(fn (array $attributes) => [
            'og_image_url' => null,
        ]);
    }
}
