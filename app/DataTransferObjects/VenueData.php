<?php

namespace App\DataTransferObjects;

use Spatie\LaravelData\Data;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

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

        return [
            'id' => 'nullable|integer',
            'name' => 'required|array',
            'name.en' => 'required_without_all:name.zh_TW,name.zh_CN|nullable|string|max:255',
            'name.zh_TW' => 'required_without_all:name.en,name.zh_CN|nullable|string|max:255',
            'name.zh_CN' => 'required_without_all:name.en,name.zh_TW|nullable|string|max:255',
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('venues', 'slug')->ignore($venueId),
            ],
            'description' => 'nullable|array',
            'description.en' => 'nullable|string',
            'description.zh_TW' => 'nullable|string',
            'description.zh_CN' => 'nullable|string',
            'address_line_1' => 'required|array',
            'address_line_1.en' => 'required_without_all:address_line_1.zh_TW,address_line_1.zh_CN|nullable|string|max:255',
            'address_line_1.zh_TW' => 'required_without_all:address_line_1.en,address_line_1.zh_CN|nullable|string|max:255',
            'address_line_1.zh_CN' => 'required_without_all:address_line_1.en,address_line_1.zh_TW|nullable|string|max:255',
            'address_line_2' => 'nullable|array',
            'address_line_2.en' => 'nullable|string|max:255',
            'address_line_2.zh_TW' => 'nullable|string|max:255',
            'address_line_2.zh_CN' => 'nullable|string|max:255',
            'city' => 'required|array',
            'city.en' => 'required_without_all:city.zh_TW,city.zh_CN|nullable|string|max:255',
            'city.zh_TW' => 'required_without_all:city.en,city.zh_CN|nullable|string|max:255',
            'city.zh_CN' => 'required_without_all:city.en,city.zh_TW|nullable|string|max:255',
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
            'organizer_id' => 'nullable|integer|exists:users,id',
            'uploaded_main_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'removed_main_image_id' => 'nullable|integer|exists:media,id',
            'uploaded_gallery_images' => 'nullable|array',
            'uploaded_gallery_images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'removed_gallery_image_ids' => 'nullable|array',
            'removed_gallery_image_ids.*' => 'integer|exists:media,id',
        ];
    }
}
