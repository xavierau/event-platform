<?php

namespace App\DataTransferObjects\Organizer;

use App\Casts\SocialMediaLinksCaster;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;
use App\Rules\RequiredTranslation;
use App\Rules\EnsureAtLeastOneTranslationIsFilled;

class OrganizerData extends Data
{
    public function __construct(
        public readonly array $name,
        public readonly string $slug,
        public readonly ?array $description,
        public readonly ?string $contact_email,
        public readonly ?string $contact_phone,
        public readonly ?string $website_url,
        #[WithCast(SocialMediaLinksCaster::class)]
        public readonly ?array $social_media_links,
        public readonly ?string $address_line_1,
        public readonly ?string $address_line_2,
        public readonly ?string $city,
        public readonly ?string $state,
        public readonly ?string $postal_code,
        public readonly ?int $country_id,
        public readonly ?int $state_id,
        public readonly bool $is_active,
        public readonly ?array $contract_details,
        public readonly ?int $created_by,
        public readonly ?UploadedFile $logo_upload,
        public readonly ?int $id,
    ) {}

    public static function rules(): array
    {
        return [
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:255'],
            'name.zh-TW' => ['nullable', 'string', 'max:255'],
            'name.zh-CN' => ['nullable', 'string', 'max:255'],

            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/'],

            'description' => ['nullable', 'array'],
            'description.en' => ['nullable', 'string'],
            'description.zh-TW' => ['nullable', 'string'],
            'description.zh-CN' => ['nullable', 'string'],

            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50', 'regex:/^[\+]?[0-9\s\-\(\)]+$/'],
            'website_url' => ['nullable', 'url', 'max:500'],

            'social_media_links' => ['nullable', 'array'],
            'social_media_links.facebook' => ['nullable', 'url', 'max:500'],
            'social_media_links.twitter' => ['nullable', 'url', 'max:500'],
            'social_media_links.instagram' => ['nullable', 'url', 'max:500'],
            'social_media_links.linkedin' => ['nullable', 'url', 'max:500'],
            'social_media_links.youtube' => ['nullable', 'url', 'max:500'],
            'social_media_links.website' => ['nullable', 'url', 'max:500'],

            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],

            'is_active' => ['required', 'boolean'],

            'contract_details' => ['nullable', 'array'],
            'contract_details.terms' => ['nullable', 'string'],
            'contract_details.rate_structure' => ['nullable', 'string'],
            'contract_details.payment_terms' => ['nullable', 'string'],
            'contract_details.cancellation_policy' => ['nullable', 'string'],
            'contract_details.effective_date' => ['nullable', 'date'],
            'contract_details.expiry_date' => ['nullable', 'date', 'after:contract_details.effective_date'],

            'created_by' => ['required', 'integer', 'exists:users,id'],

            'logo_upload' => [
                'nullable',
                'file',
                'image',
                'mimes:jpeg,png,jpg,gif,webp',
                'max:2048', // 2MB max
                'dimensions:min_width=100,min_height=100,max_width=2000,max_height=2000'
            ],

            'id' => ['nullable', 'integer', 'exists:organizers,id'],
        ];
    }

    /**
     * Transform the DTO for update operations (excludes unique validation on slug for existing records)
     */
    public function forUpdate(int $organizerId): self
    {
        return new self(
            name: $this->name,
            slug: $this->slug,
            description: $this->description,
            contact_email: $this->contact_email,
            contact_phone: $this->contact_phone,
            website_url: $this->website_url,
            social_media_links: $this->social_media_links,
            address_line_1: $this->address_line_1,
            address_line_2: $this->address_line_2,
            city: $this->city,
            state: $this->state,
            postal_code: $this->postal_code,
            country_id: $this->country_id,
            state_id: $this->state_id,
            is_active: $this->is_active,
            contract_details: $this->contract_details,
            created_by: $this->created_by,
            logo_upload: $this->logo_upload,
            id: $organizerId,
        );
    }

    /**
     * Get rules for update operations (excludes unique validation on slug for existing records)
     */
    public static function updateRules(int $organizerId): array
    {
        $rules = static::rules();
        $rules['slug'] = ['required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/', "unique:organizers,slug,$organizerId"];

        return $rules;
    }

    /**
     * Check if the organizer has complete address information
     */
    public function hasCompleteAddress(): bool
    {
        return !empty($this->address_line_1)
            && !empty($this->city)
            && !empty($this->country_id);
    }

    /**
     * Check if the organizer has contact information
     */
    public function hasContactInfo(): bool
    {
        return !empty($this->contact_email) || !empty($this->contact_phone);
    }

    /**
     * Get the organizer's primary contact method
     */
    public function getPrimaryContact(): ?string
    {
        return $this->contact_email ?: $this->contact_phone;
    }

    /**
     * Check if social media links are provided
     */
    public function hasSocialMediaLinks(): bool
    {
        return !empty($this->social_media_links) && count(array_filter($this->social_media_links)) > 0;
    }
}
