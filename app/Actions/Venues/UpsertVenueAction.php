<?php

namespace App\Actions\Venues;

use App\DataTransferObjects\VenueData;
use App\Models\Venue;
use Illuminate\Support\Arr;
// It's good practice to use the Facade if you want to specify configurations,
// but the global `clean()` helper is often sufficient for default sanitization.
// use Mews\Purifier\Facades\Purifier;

class UpsertVenueAction
{
    public function execute(VenueData $venueData): Venue
    {
        $data = $venueData->toArray();

        // Sanitize translatable description fields
        if (isset($data['description']) && is_array($data['description'])) {
            $sanitizedDescriptions = [];
            foreach ($data['description'] as $locale => $htmlContent) {
                $sanitizedDescriptions[$locale] = is_string($htmlContent) ? clean($htmlContent) : $htmlContent;
            }
            $data['description'] = $sanitizedDescriptions;
        }

        $venueAttributes = Arr::except($data, [
            'uploaded_main_image',
            'existing_main_image',
            'removed_main_image_id',
            'uploaded_gallery_images',
            'existing_gallery_images',
            'removed_gallery_image_ids',
            // Old fields, just in case
            'uploaded_images',
            'images',
            'thumbnail_image_path'
        ]);

        $venue = Venue::updateOrCreate(
            ['id' => $venueData->id],
            $venueAttributes
        );

        // Handle Main Image Upload
        if ($venueData->uploaded_main_image instanceof \Illuminate\Http\UploadedFile) {
            $venue->addMedia($venueData->uploaded_main_image)->toMediaCollection('featured_image');
        }

        // Handle Main Image Removal
        if ($venueData->removed_main_image_id) {
            $mediaItem = $venue->getMedia('featured_image')->find($venueData->removed_main_image_id);
            if ($mediaItem) {
                $mediaItem->delete();
            }
        } elseif ($venueData->uploaded_main_image && $venue->getMedia('featured_image')->count() > 1) {
            // If a new main image was uploaded and it wasn't a replacement (somehow old one persisted),
            // clear older ones. This ensures singleFile behavior is enforced if an old one wasn't cleared by ID.
            // Note: addMedia(...)->toMediaCollection('featured_image') should handle replacement for singleFile collections.
            // This is an extra safeguard.
            $venue->clearMediaCollectionExcept('featured_image', $venue->getFirstMedia('featured_image'));
        }

        // Handle Gallery Image Uploads
        if ($venueData->uploaded_gallery_images && !empty($venueData->uploaded_gallery_images)) {
            foreach ($venueData->uploaded_gallery_images as $imageFile) {
                if ($imageFile instanceof \Illuminate\Http\UploadedFile) {
                    $venue->addMedia($imageFile)->toMediaCollection('gallery');
                }
            }
        }

        // Handle Gallery Image Removals
        if ($venueData->removed_gallery_image_ids && !empty($venueData->removed_gallery_image_ids)) {
            foreach ($venueData->removed_gallery_image_ids as $mediaId) {
                $mediaItem = $venue->getMedia('gallery')->find($mediaId);
                if ($mediaItem) {
                    $mediaItem->delete();
                }
            }
        }

        return $venue;
    }
}
