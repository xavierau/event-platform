<?php

namespace App\Modules\SocialShare\Data;

use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;

class SocialPlatformData extends Data
{
    public function __construct(
        #[Required, Rule(['string', 'max:50'])]
        public string $name,

        #[Required, Rule(['string', 'url', 'max:2048'])]
        public string $url,

        #[Required, Rule(['string', 'max:200'])]
        public string $title,

        #[Rule(['nullable', 'string', 'max:500'])]
        public ?string $description = null,

        #[Rule(['array'])]
        public array $hashtags = [],

        #[Rule(['nullable', 'string', 'max:100'])]
        public ?string $via = null,

        #[Rule(['nullable', 'string', 'url', 'max:2048'])]
        public ?string $image_url = null,

        #[Rule(['array'])]
        public array $metadata = [],
    ) {}

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'url' => ['required', 'string', 'url', 'max:2048'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:500'],
            'hashtags' => ['array'],
            'hashtags.*' => ['string', 'max:50'],
            'via' => ['nullable', 'string', 'max:100'],
            'image_url' => ['nullable', 'string', 'url', 'max:2048'],
            'metadata' => ['array'],
        ];
    }

    /**
     * Format hashtags with # prefix for display
     */
    public function getFormattedHashtags(): string
    {
        if (empty($this->hashtags)) {
            return '';
        }

        return implode(' ', array_map(fn ($tag) => '#'.$tag, $this->hashtags));
    }

    /**
     * Get truncated title for platforms with character limits
     */
    public function getTruncatedTitle(int $maxLength): string
    {
        if (strlen($this->title) <= $maxLength) {
            return $this->title;
        }

        return substr($this->title, 0, $maxLength - 3).'...';
    }

    /**
     * Get truncated description for platforms with character limits
     */
    public function getTruncatedDescription(int $maxLength): ?string
    {
        if (! $this->description) {
            return null;
        }

        if (strlen($this->description) <= $maxLength) {
            return $this->description;
        }

        return substr($this->description, 0, $maxLength - 3).'...';
    }

    /**
     * Get metadata value by key
     *
     * @param  mixed  $default
     * @return mixed
     */
    public function getMetadata(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Check if platform supports specific features
     */
    public function supportsFeatures(array $features): bool
    {
        $supportedFeatures = $this->getMetadata('supported_features', []);

        return empty(array_diff($features, $supportedFeatures));
    }
}
