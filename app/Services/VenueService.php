<?php

namespace App\Services;

use App\Actions\Venues\UpsertVenueAction;
use App\DataTransferObjects\VenueData;
use App\Models\Venue;

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

        $updateData = VenueData::from($dataArray);
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
        // Basic pagination, can be customized further
        // Example: Venue::with($with)->filter($filters)->orderBy($orderBy, $direction)->paginate();
        return Venue::with($with)->orderBy($orderBy, $direction)->paginate();
    }
}
