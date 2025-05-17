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
            // Merge sanitized descriptions back into the main data array
            $data['description'] = $sanitizedDescriptions;
        }

        // Ensure address_line_2 is handled correctly if it can be null.
        // If the DTO ensures it's an array (e.g. ['en' => null, ...]) or null,
        // and spatie/laravel-translatable handles null for a locale, this should be fine.
        // If address_line_2 from DTO is null and needs to be an empty array for setTranslations,
        // that logic would be here. For now, assume DTO provides a suitable structure or null.
        // Example: $data['address_line_2'] = $data['address_line_2'] ?? [];

        // The 'id' will be extracted by updateOrCreate for its first argument (attributes for query).
        // The rest of $data will be used for setting attributes on creation or update.
        // Spatie/laravel-translatable will handle the translatable fields (name, description, etc.)
        // correctly when they are passed as arrays.

        $venue = Venue::updateOrCreate(
            ['id' => $venueData->id], // Attributes to find the record
            $data                    // Attributes to update or create with
        );

        // The loop for setTranslations is no longer strictly necessary if $fillable
        // and $translatable are correctly set up in the Venue model, as updateOrCreate
        // will handle the direct assignment of translatable arrays.
        // However, if there's any special logic or if you want to be explicit,
        // it can be kept, but it might be redundant.
        // For instance, if a field was not in $fillable but needed to be set:
        // foreach ($translatableFields as $key => $value) {
        //     if ($value !== null) {
        //         $venue->setTranslations($key, $value);
        //     }
        // }
        // $venue->save(); // A final save might be needed if setTranslations was used after updateOrCreate

        return $venue;
    }
}
