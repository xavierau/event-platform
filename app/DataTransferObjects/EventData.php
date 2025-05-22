<?php

namespace App\DataTransferObjects;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation;
use Spatie\LaravelData\Attributes\Validation\Each;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class EventData extends Data
{
    public function __construct(
        #[Validation\Rule(['required', 'integer', 'exists:users,id'])]
        public readonly int $organizer_id,

        #[Validation\Rule(['required', 'integer', 'exists:categories,id'])]
        public readonly int $category_id,

        #[Validation\Rule(['required', 'array'])]
        public readonly array $name, // Translatable: ['en' => '', 'zh-TW' => '']

        #[Validation\Rule(['required', 'array'])]
        public readonly array $slug, // Translatable

        #[Validation\Rule(['required', 'array'])]
        public readonly array $description, // Translatable

        #[Validation\Rule(['nullable', 'array'])]
        public readonly ?array $short_summary, // Translatable

        #[Validation\Rule(['sometimes', 'string', 'in:draft,pending_approval,published,cancelled,completed,past'])]
        public readonly ?string $event_status,

        #[Validation\Rule(['sometimes', 'string', 'in:public,private,unlisted'])]
        public readonly ?string $visibility,

        #[Validation\Rule(['sometimes', 'boolean'])]
        public readonly ?bool $is_featured,

        #[Validation\Rule(['nullable', 'email'])]
        public readonly ?string $contact_email,

        #[Validation\Rule(['nullable', 'string', 'max:255'])]
        public readonly ?string $contact_phone,

        #[Validation\Rule(['nullable', 'url', 'max:2048'])]
        public readonly ?string $website_url,

        #[Validation\Rule(['nullable', 'array'])]
        public readonly ?array $social_media_links, // e.g., ['facebook' => 'url', 'twitter' => 'url']

        #[Validation\Rule(['nullable', 'string', 'max:255'])]
        public readonly ?string $youtube_video_id,

        #[Validation\Rule(['nullable', 'array'])]
        public readonly ?array $cancellation_policy, // Translatable

        #[Validation\Rule(['nullable', 'array'])]
        public readonly ?array $meta_title, // Translatable

        #[Validation\Rule(['nullable', 'array'])]
        public readonly ?array $meta_description, // Translatable

        #[Validation\Rule(['nullable', 'array'])]
        public readonly ?array $meta_keywords, // Translatable

        #[Validation\Rule(['nullable', 'date'])]
        public readonly ?string $published_at, // Or Carbon instance

        // For tags
        #[Validation\Rule(['nullable', 'array'])]
        public readonly ?array $tag_ids, // Array of tag IDs

        // For creator and updater, these are usually set automatically
        public readonly ?int $created_by = null,
        public readonly ?int $updated_by = null,

        // For existing event ID during updates
        public readonly ?int $id = null,

        // Media Uploads
        #[Validation\Rule(['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'])] // Max 2MB example
        public readonly ?UploadedFile $uploaded_portrait_poster = null,

        #[Validation\Rule(['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'])]
        public readonly ?UploadedFile $uploaded_landscape_poster = null,

        #[Validation\Rule(['nullable', 'array'])] // Validates that it's an array if present
        // Individual file validation will be in rules() method
        public readonly ?array $uploaded_gallery = null, // Array of UploadedFile

        // For handling removal of existing gallery items during update
        #[Validation\Rule(['nullable', 'array'])]
        public readonly ?array $removed_gallery_ids = null // Array of media IDs to remove
    ) {}

    public static function rules(): array
    {
        $rules = [];
        $requiredLocale = config('app.locale', 'en');
        $otherLocales = array_diff(config('translatable.locales', ['en', 'zh-TW', 'zh-CN']), [$requiredLocale]);

        $rules["name.{$requiredLocale}"] = ['required', 'string', 'max:255'];
        $rules["slug.{$requiredLocale}"] = ['required', 'string', 'max:255'];
        $rules["description.{$requiredLocale}"] = ['required', 'string'];

        foreach ($otherLocales as $locale) {
            $rules["name.{$locale}"] = ['nullable', 'string', 'max:255'];
            $rules["slug.{$locale}"] = ['nullable', 'string', 'max:255'];
            $rules["description.{$locale}"] = ['nullable', 'string'];
            $rules["short_summary.{$locale}"] = ['nullable', 'string'];
            $rules["cancellation_policy.{$locale}"] = ['nullable', 'string'];
            $rules["meta_title.{$locale}"] = ['nullable', 'string', 'max:255'];
            $rules["meta_description.{$locale}"] = ['nullable', 'string', 'max:1000'];
            $rules["meta_keywords.{$locale}"] = ['nullable', 'string', 'max:255'];
        }

        // Validation for tag_ids array elements
        $rules['tag_ids'] = ['nullable', 'array'];
        $rules['tag_ids.*'] = ['integer', 'exists:tags,id'];

        // Validation for gallery images (if present)
        $rules['uploaded_gallery.*'] = ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048']; // Max 2MB example per image

        // Validation for removed_gallery_ids (if present)
        $rules['removed_gallery_ids'] = ['nullable', 'array'];
        $rules['removed_gallery_ids.*'] = ['integer', 'numeric']; // Should be existing media IDs

        return $rules;
    }
}
