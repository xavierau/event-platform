<?php

namespace App\DataTransferObjects;

use Spatie\LaravelData\Data;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class VenueData extends Data
{
    public function __construct(
        public readonly array $name,
        public readonly string $slug,
        public readonly int $country_id,
        public readonly array $address_line_1,
        public readonly array $city,
        public readonly ?array $description = null,
        public readonly ?int $organizer_id = null,
        public readonly ?array $address_line_2 = null,
        public readonly ?string $postal_code = null,
        public readonly ?int $state_id = null,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly ?string $contact_email = null,
        public readonly ?string $contact_phone = null,
        public readonly ?string $website_url = null,
        public readonly ?int $seating_capacity = null,
        public readonly ?array $images = null,
        public readonly ?string $thumbnail_image_path = null,
        public readonly bool $is_active = true,
        public readonly ?int $id = null,
    ) {}

    public static function rules(ValidationContext $context): array
    {
        $venueId = $context->payload['id'] ?? null;

        if (!$venueId && isset($context->data['id'])) {
            $venueId = $context->data['id'];
        }

        return [
            'id' => 'nullable|integer',
            'name' => 'required|array',
            'name.en' => 'required|string|max:255',
            'name.zh-TW' => 'nullable|string|max:255',
            'name.zh-CN' => 'nullable|string|max:255',

            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('venues', 'slug')->ignore($venueId),
            ],

            'description' => 'nullable|array',
            'description.en' => 'nullable|string',
            'description.zh-TW' => 'nullable|string',
            'description.zh-CN' => 'nullable|string',

            'address_line_1' => 'required|array',
            'address_line_1.en' => 'required|string|max:255',
            'address_line_1.zh-TW' => 'nullable|string|max:255',
            'address_line_1.zh-CN' => 'nullable|string|max:255',

            'address_line_2' => 'nullable|array',
            'address_line_2.en' => 'nullable|string|max:255',
            'address_line_2.zh-TW' => 'nullable|string|max:255',
            'address_line_2.zh-CN' => 'nullable|string|max:255',

            'city' => 'required|array',
            'city.en' => 'required|string|max:255',
            'city.zh-TW' => 'nullable|string|max:255',
            'city.zh-CN' => 'nullable|string|max:255',

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
        ];
    }
}
