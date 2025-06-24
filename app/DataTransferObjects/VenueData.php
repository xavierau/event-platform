<?php

namespace App\DataTransferObjects;

use Spatie\LaravelData\Data;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Illuminate\Support\Facades\Log;

#[MapName(SnakeCaseMapper::class)]
class VenueData extends Data
{
    public function __construct(
        public readonly array $name,
        public readonly string $slug,
        public readonly int $country_id,
        public readonly array $address_line_1,
        public readonly array $city,
        public readonly ?array $description = null,
        public readonly ?array $address_line_2 = null,
        public readonly ?int $organizer_id = null,
        public readonly ?string $postal_code = null,
        public readonly ?int $state_id = null,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly ?string $contact_email = null,
        public readonly ?string $contact_phone = null,
        public readonly ?string $website_url = null,
        public readonly ?int $seating_capacity = null,
        public readonly bool $is_active = true,
        public readonly ?int $id = null,

        // Main Image
        public readonly ?UploadedFile $uploaded_main_image = null,
        public readonly ?object $existing_main_image = null,
        public readonly ?int $removed_main_image_id = null,

        // Gallery Images
        /** @var UploadedFile[]|null */
        public readonly ?array $uploaded_gallery_images = null,
        public readonly ?array $existing_gallery_images = null,
        /** @var int[]|null */
        public readonly ?array $removed_gallery_image_ids = null,
    ) {}

    public static function rules(ValidationContext $context): array
    {
        $venueId = $context->payload['id'] ?? null;

        $rules = [
            'id' => 'nullable|integer',
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('venues', 'slug')->ignore($venueId),
            ],
            'postal_code' => 'nullable|string|max:20',
            'country_id' => 'required|integer|exists:countries,id',
            'state_id' => 'nullable|integer|exists:states,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'website_url' => 'nullable|url|max:255',
            'seating_capacity' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'organizer_id' => 'nullable|integer|exists:organizers,id',
            'uploaded_main_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'removed_main_image_id' => 'nullable|integer|exists:media,id',
            'uploaded_gallery_images' => 'nullable|array',
            'uploaded_gallery_images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'removed_gallery_image_ids' => 'nullable|array',
            'removed_gallery_image_ids.*' => 'integer|exists:media,id',
        ];

        // Define locales
        // Ensure 'en' is always present if config is missing, matching EventData's fallback.
        $configuredLocales = config('app.available_locales', ['en' => 'English']);
        if (is_object($configuredLocales) && method_exists($configuredLocales, 'toArray')) {
            $configuredLocales = $configuredLocales->toArray();
        }
        $availableLocales = array_keys($configuredLocales);
        if (empty($availableLocales)) { // Fallback if array_keys returns empty
            $availableLocales = ['en'];
        }

        $primaryLocale = config('app.locale', 'en');
        // Ensure primaryLocale is part of availableLocales for safety
        if (!in_array($primaryLocale, $availableLocales)) {
            $primaryLocale = $availableLocales[0] ?? 'en';
        }

        // Translatable fields definitions
        // For fields like 'name', 'address_line_1', 'city' (non-nullable array properties):
        // - primary locale is required
        // - other locales are nullable
        // For fields like 'description', 'address_line_2' (nullable array properties):
        // - all locales are nullable

        $translatableFieldsConfig = [
            'name' => ['is_property_nullable' => false, 'max' => 255],
            'description' => ['is_property_nullable' => true, 'max' => 65535], // Assuming larger max for description
            'address_line_1' => ['is_property_nullable' => false, 'max' => 255],
            'address_line_2' => ['is_property_nullable' => true, 'max' => 255],
            'city' => ['is_property_nullable' => false, 'max' => 255],
        ];

        foreach ($translatableFieldsConfig as $field => $config) {
            // Add base array rule if the property itself is required (non-nullable array)
            // This ensures the key is present and is an array if the DTO property is `array $field`
            // For `?array $field`, this is not needed as the field itself can be null.
            // Spatie/laravel-data validates types based on property typehints if no explicit rule is set.
            // If `array $field` is provided as non-array, it'll fail type casting.
            // If `array $field` is missing, per-locale 'required' rules handle it.
            // Let's omit general 'array' rule for the field name itself to strictly follow EventData,
            // which also doesn't have e.g. 'name' => ['array'] in its rules method.

            foreach ($availableLocales as $locale) {
                $isPrimary = ($locale === $primaryLocale);
                $localeRules = [];

                if ($config['is_property_nullable']) {
                    $localeRules[] = 'nullable';
                } else {
                    if ($isPrimary) {
                        $localeRules[] = 'required';
                    } else {
                        $localeRules[] = 'nullable';
                    }
                }
                $localeRules[] = 'string';
                if (isset($config['max'])) {
                    $localeRules[] = 'max:' . $config['max'];
                }
                $rules["{$field}.{$locale}"] = $localeRules;
            }
        }
        return $rules;
    }
}
