<?php

namespace App\Modules\PromotionalModal\DataTransferObjects;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Data;

class PromotionalModalData extends Data
{
    public function __construct(
        public array $title,
        public array $content,
        public ?string $type = 'modal',
        #[Nullable]
        public readonly ?array $pages = null,
        #[Nullable]
        public readonly ?array $membership_levels = null,
        #[Nullable]
        public readonly ?array $user_segments = null,
        #[Nullable]
        public readonly ?string $start_at = null,
        #[Nullable]
        public readonly ?string $end_at = null,
        public ?string $display_frequency = 'once',
        public ?int $cooldown_hours = 24,
        public ?int $impressions_count = 0,
        public ?int $clicks_count = 0,
        public ?float $conversion_rate = 0.00,
        public ?bool $is_active = true,
        public ?int $priority = 0,
        public ?int $sort_order = 0,
        #[Nullable]
        public readonly ?string $button_text = null,
        #[Nullable]
        public readonly ?string $button_url = null,
        public ?bool $is_dismissible = true,
        #[Nullable]
        public readonly ?array $display_conditions = null,
        #[Nullable]
        public readonly ?UploadedFile $banner_image = null,
        #[Nullable]
        public readonly ?UploadedFile $background_image = null,
    ) {}

    public static function rules(): array
    {
        $rules = [];
        $locales = config('app.available_locales', ['en' => 'English']);
        $primaryLocale = array_key_first($locales);

        // Translatable fields validation
        foreach (array_keys($locales) as $locale) {
            $titleRequired = $locale === $primaryLocale ? 'required' : 'nullable';
            $rules["title.{$locale}"] = [$titleRequired, 'string', 'max:255'];

            $contentRequired = $locale === $primaryLocale ? 'required' : 'nullable';
            $rules["content.{$locale}"] = [$contentRequired, 'string'];
        }

        // Basic fields
        $rules['type'] = ['required', 'string', Rule::in(['modal', 'banner'])];
        $rules['pages'] = ['nullable', 'array'];
        $rules['pages.*'] = ['string', 'max:255'];
        $rules['membership_levels'] = ['nullable', 'array'];
        $rules['membership_levels.*'] = ['integer', 'exists:membership_levels,id'];
        $rules['user_segments'] = ['nullable', 'array'];

        // Timing
        $rules['start_at'] = ['nullable', 'date'];
        $rules['end_at'] = ['nullable', 'date', 'after_or_equal:start_at'];
        $rules['display_frequency'] = ['required', 'string', Rule::in(['once', 'daily', 'weekly', 'always'])];
        $rules['cooldown_hours'] = ['required', 'integer', 'min:0', 'max:8760']; // max 1 year

        // Analytics
        $rules['impressions_count'] = ['nullable', 'integer', 'min:0'];
        $rules['clicks_count'] = ['nullable', 'integer', 'min:0'];
        $rules['conversion_rate'] = ['nullable', 'numeric', 'min:0', 'max:100'];

        // Status
        $rules['is_active'] = ['nullable', 'boolean'];
        $rules['priority'] = ['nullable', 'integer', 'min:0', 'max:999'];
        $rules['sort_order'] = ['nullable', 'integer', 'min:0'];

        // Additional
        $rules['button_text'] = ['nullable', 'string', 'max:100'];
        $rules['button_url'] = ['nullable', 'string', 'max:500', 'url'];
        $rules['is_dismissible'] = ['nullable', 'boolean'];
        $rules['display_conditions'] = ['nullable', 'array'];

        // Media
        $rules['banner_image'] = ['nullable', 'image', 'mimes:jpeg,png,webp,svg', 'max:2048'];
        $rules['background_image'] = ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'];

        return $rules;
    }

    public static function messages(): array
    {
        return [
            'title.*.required' => 'The title is required.',
            'title.*.max' => 'The title must not exceed 255 characters.',
            'content.*.required' => 'The content is required.',
            'type.in' => 'The type must be either modal or banner.',
            'membership_levels.*.exists' => 'One or more membership levels do not exist.',
            'end_at.after_or_equal' => 'The end date must be after or equal to the start date.',
            'display_frequency.in' => 'The display frequency must be one of: once, daily, weekly, always.',
            'cooldown_hours.max' => 'The cooldown hours cannot exceed 1 year (8760 hours).',
            'conversion_rate.max' => 'The conversion rate cannot exceed 100%.',
            'priority.max' => 'The priority cannot exceed 999.',
            'button_url.url' => 'The button URL must be a valid URL.',
            'banner_image.mimes' => 'The banner image must be a JPEG, PNG, WebP, or SVG file.',
            'banner_image.max' => 'The banner image must not exceed 2MB.',
            'background_image.mimes' => 'The background image must be a JPEG, PNG, or WebP file.',
            'background_image.max' => 'The background image must not exceed 5MB.',
        ];
    }
}