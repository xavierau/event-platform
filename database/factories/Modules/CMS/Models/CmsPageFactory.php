<?php

namespace Database\Factories\Modules\CMS\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\CMS\Models\CmsPage>
 */
class CmsPageFactory extends Factory
{
    protected $model = \App\Modules\CMS\Models\CmsPage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->sentence(4);

        return [
            'title' => [
                'en' => $title,
                'zh-TW' => '頁面標題 ' . $this->faker->words(2, true),
                'zh-CN' => '页面标题 ' . $this->faker->words(2, true),
            ],
            'slug' => $this->faker->unique()->slug(3),
            'content' => [
                'en' => $this->faker->paragraphs(3, true),
                'zh-TW' => '這是一個示例內容段落。' . $this->faker->text(200),
                'zh-CN' => '这是一个示例内容段落。' . $this->faker->text(200),
            ],
            'meta_description' => [
                'en' => $this->faker->sentence(10),
                'zh-TW' => '頁面描述 ' . $this->faker->sentence(8),
                'zh-CN' => '页面描述 ' . $this->faker->sentence(8),
            ],
            'meta_keywords' => [
                'en' => implode(', ', $this->faker->words(5)),
                'zh-TW' => implode(', ', ['關鍵字', '頁面', '內容']),
                'zh-CN' => implode(', ', ['关键字', '页面', '内容']),
            ],
            'is_published' => $this->faker->boolean(70), // 70% chance of being published
            'published_at' => $this->faker->optional(0.7)->dateTimeBetween('-1 month', 'now'),
            'author_id' => User::factory(),
            'sort_order' => $this->faker->numberBetween(0, 100),
        ];
    }

    /**
     * Indicate that the page is published.
     */
    public function published(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_published' => true,
            'published_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    /**
     * Indicate that the page is unpublished.
     */
    public function unpublished(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_published' => false,
            'published_at' => null,
        ]);
    }

    /**
     * Indicate that the page is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_published' => false,
            'published_at' => null,
        ]);
    }

    /**
     * Create a page with minimal English content only.
     */
    public function englishOnly(): static
    {
        return $this->state(fn(array $attributes) => [
            'title' => [
                'en' => $this->faker->sentence(4),
            ],
            'content' => [
                'en' => $this->faker->paragraphs(2, true),
            ],
            'meta_description' => [
                'en' => $this->faker->sentence(8),
            ],
            'meta_keywords' => [
                'en' => implode(', ', $this->faker->words(3)),
            ],
        ]);
    }
}
