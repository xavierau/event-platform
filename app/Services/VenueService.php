<?php

namespace App\Services;

use App\Actions\Venues\UpsertVenueAction;
use App\DataTransferObjects\VenueData;
use App\Models\Venue;
use App\Enums\RoleNameEnum;
use Illuminate\Support\Facades\Auth;

class VenueService
{
    public function __construct(protected UpsertVenueAction $upsertVenueAction) {}

    public function createVenue(VenueData $venueData): Venue
    {
        // Ensure ID is null for creation context.
        // The `except` method creates a new DTO instance excluding specified properties.
        return $this->upsertVenueAction->execute($venueData->except('id'));
    }

    public function updateVenue(int $venueId, VenueData $venueData): Venue
    {
        // Create a new DTO instance, ensuring the ID from the path is used.
        $dataArray = $venueData->all(); // Get all properties as an array
        $dataArray['id'] = $venueId;    // Override the id

        $updateData = VenueData::validateAndCreate($dataArray);
        return $this->upsertVenueAction->execute($updateData);
    }

    public function deleteVenue(Venue $venue): ?bool
    {
        return $venue->delete();
    }

    // Basic query methods - can be expanded later
    public function findById(int $id): ?Venue
    {
        return Venue::find($id);
    }

    public function getAllVenues(array $filters = [], array $with = [], string $orderBy = 'created_at', string $direction = 'desc')
    {
        $query = Venue::with($with);

        // Apply user-based filtering
        $user = Auth::user();
        if (!$user->hasRole(RoleNameEnum::ADMIN)) {
            // Non-admin users can only see public venues and venues from their organizers
            $userOrganizerIds = $user->activeOrganizers()->pluck('id');
            
            $query->where(function ($q) use ($userOrganizerIds) {
                // Public venues (no organizer assigned)
                $q->where('is_public', true)
                  // OR venues belonging to user's organizers
                  ->orWhereIn('organizer_id', $userOrganizerIds);
            });
        }

        // Apply additional filters if provided
        if (!empty($filters['search'])) {
            $searchTerm = $filters['search'];
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name->' . app()->getLocale(), 'like', "%{$searchTerm}%")
                    ->orWhere('name->' . config('app.fallback_locale', 'en'), 'like', "%{$searchTerm}%")
                    ->orWhere('address', 'like', "%{$searchTerm}%");
            });
        }

        if (isset($filters['is_public']) && $filters['is_public'] !== '') {
            $query->where('is_public', filter_var($filters['is_public'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderBy($orderBy, $direction)->paginate();
    }
}
