<?php

namespace App\DTOs;

use App\Models\EventSeo;
use Spatie\LaravelData\Attributes\Validation\Boolean;
use Spatie\LaravelData\Attributes\Validation\Integer;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;

class EventSeoData extends Data
{
    public function __construct(
        #[Integer, Required]
        public int $event_id,

        #[Rule(['array', 'nullable'])]
        public ?array $meta_title = null,

        #[Rule(['array', 'nullable'])]
        public ?array $meta_description = null,

        #[Rule(['array', 'nullable'])]
        public ?array $keywords = null,

        #[Rule(['array', 'nullable'])]
        public ?array $og_title = null,

        #[Rule(['array', 'nullable'])]
        public ?array $og_description = null,

        #[Rule(['nullable', 'string', 'url', 'max:255'])]
        public ?string $og_image_url = null,

        #[Boolean]
        public bool $is_active = true,
    ) {}

    public static function rules(): array
    {
        return [
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'meta_title' => ['nullable', 'array'],
            'meta_title.*' => ['nullable', 'string', 'max:60'],
            'meta_description' => ['nullable', 'array'],
            'meta_description.*' => ['nullable', 'string', 'max:160'],
            'keywords' => ['nullable', 'array'],
            'keywords.*' => ['nullable', 'string', 'max:255'],
            'og_title' => ['nullable', 'array'],
            'og_title.*' => ['nullable', 'string', 'max:60'],
            'og_description' => ['nullable', 'array'],
            'og_description.*' => ['nullable', 'string', 'max:160'],
            'og_image_url' => ['nullable', 'string', 'url', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    public static function fromModel(EventSeo $eventSeo): self
    {
        return new self(
            event_id: $eventSeo->event_id,
            meta_title: $eventSeo->getTranslations('meta_title') ?: null,
            meta_description: $eventSeo->getTranslations('meta_description') ?: null,
            keywords: $eventSeo->getTranslations('keywords') ?: null,
            og_title: $eventSeo->getTranslations('og_title') ?: null,
            og_description: $eventSeo->getTranslations('og_description') ?: null,
            og_image_url: $eventSeo->og_image_url,
            is_active: $eventSeo->is_active,
        );
    }

    public function getMetaTitleForLocale(string $locale): ?string
    {
        return $this->meta_title[$locale] ?? null;
    }

    public function getMetaDescriptionForLocale(string $locale): ?string
    {
        return $this->meta_description[$locale] ?? null;
    }

    public function getKeywordsForLocale(string $locale): ?string
    {
        return $this->keywords[$locale] ?? null;
    }

    public function getOgTitleForLocale(string $locale): ?string
    {
        return $this->og_title[$locale] ?? null;
    }

    public function getOgDescriptionForLocale(string $locale): ?string
    {
        return $this->og_description[$locale] ?? null;
    }
}
