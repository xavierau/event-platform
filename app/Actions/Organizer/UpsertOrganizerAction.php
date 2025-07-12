<?php

namespace App\Actions\Organizer;

use App\DataTransferObjects\Organizer\OrganizerData;
use App\Models\Organizer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpsertOrganizerAction
{
    public function execute(OrganizerData $organizerData): Organizer
    {
        return DB::transaction(function () use ($organizerData) {
            $dataToUpdate = [
                'name' => $organizerData->name,
                'slug' => $this->generateSlugIfNeeded($organizerData),
                'description' => $organizerData->description,
                'contact_email' => $organizerData->contact_email,
                'contact_phone' => $organizerData->contact_phone,
                'website_url' => $organizerData->website_url,
                'social_media_links' => $organizerData->social_media_links,
                'address_line_1' => $organizerData->address_line_1,
                'address_line_2' => $organizerData->address_line_2,
                'city' => $organizerData->city,
                'postal_code' => $organizerData->postal_code,
                'country_id' => $organizerData->country_id,
                'state_id' => $organizerData->state_id,
                'is_active' => $organizerData->is_active,
                'contract_details' => $organizerData->contract_details,
            ];

            if ($organizerData->id) { // Update
                $organizer = Organizer::findOrFail($organizerData->id);
                $organizer->update($dataToUpdate);
            } else { // Create
                $dataToUpdate['created_by'] = $organizerData->created_by;
                $organizer = Organizer::create($dataToUpdate);
            }

            // Handle logo upload if present
            if ($organizerData->logo_upload) {
                if ($organizerData->id) {
                    $organizer->clearMediaCollection('logo');
                }
                $organizer->addMedia($organizerData->logo_upload)->toMediaCollection('logo');
            }

            return $organizer->refresh();
        });
    }

    /**
     * Generate slug from name if not provided, or validate existing slug
     */
    private function generateSlugIfNeeded(OrganizerData $organizerData): string
    {
        // If slug is provided and we're creating a new organizer, use it
        if (!$organizerData->id && !empty($organizerData->slug)) {
            return $organizerData->slug;
        }

        // If slug is provided and we're updating, use it
        if ($organizerData->id && !empty($organizerData->slug)) {
            return $organizerData->slug;
        }

        // Generate slug from name (use English name as primary)
        $baseName = $organizerData->name['en'] ?? $organizerData->name[array_key_first($organizerData->name)];
        $baseSlug = Str::slug($baseName);

        // Ensure slug is unique
        $slug = $baseSlug;
        $counter = 1;

        while (true) {
            $query = Organizer::where('slug', $slug);

            // If updating, exclude current organizer from uniqueness check
            if ($organizerData->id) {
                $query->where('id', '!=', $organizerData->id);
            }

            if (!$query->exists()) {
                break;
            }

            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Create a new organizer
     */
    public function create(OrganizerData $organizerData): Organizer
    {
        // Ensure this is a create operation
        $createData = new OrganizerData(
            name: $organizerData->name,
            slug: $organizerData->slug,
            description: $organizerData->description,
            contact_email: $organizerData->contact_email,
            contact_phone: $organizerData->contact_phone,
            website_url: $organizerData->website_url,
            social_media_links: $organizerData->social_media_links,
            address_line_1: $organizerData->address_line_1,
            address_line_2: $organizerData->address_line_2,
            city: $organizerData->city,
            state: $organizerData->state,
            postal_code: $organizerData->postal_code,
            country_id: $organizerData->country_id,
            state_id: $organizerData->state_id,
            is_active: $organizerData->is_active,
            contract_details: $organizerData->contract_details,
            created_by: $organizerData->created_by,
            logo_upload: $organizerData->logo_upload,
            id: null, // Force create operation
        );

        return $this->execute($createData);
    }

    /**
     * Update an existing organizer
     */
    public function update(int $organizerId, OrganizerData $organizerData): Organizer
    {
        // Ensure this is an update operation
        $updateData = new OrganizerData(
            name: $organizerData->name,
            slug: $organizerData->slug,
            description: $organizerData->description,
            contact_email: $organizerData->contact_email,
            contact_phone: $organizerData->contact_phone,
            website_url: $organizerData->website_url,
            social_media_links: $organizerData->social_media_links,
            address_line_1: $organizerData->address_line_1,
            address_line_2: $organizerData->address_line_2,
            city: $organizerData->city,
            state: $organizerData->state,
            postal_code: $organizerData->postal_code,
            country_id: $organizerData->country_id,
            state_id: $organizerData->state_id,
            is_active: $organizerData->is_active,
            contract_details: $organizerData->contract_details,
            created_by: $organizerData->created_by,
            logo_upload: $organizerData->logo_upload,
            id: $organizerId, // Force update operation
        );

        return $this->execute($updateData);
    }
}
