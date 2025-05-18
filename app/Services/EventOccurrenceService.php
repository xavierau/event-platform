<?php

namespace App\Services;

use App\Actions\EventOccurrence\UpsertEventOccurrenceAction;
use App\DataTransferObjects\EventOccurrenceData;
use App\Models\Event;
use App\Models\EventOccurrence;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class EventOccurrenceService
{
    protected UpsertEventOccurrenceAction $upsertEventOccurrenceAction;

    public function __construct(UpsertEventOccurrenceAction $upsertEventOccurrenceAction)
    {
        $this->upsertEventOccurrenceAction = $upsertEventOccurrenceAction;
    }

    public function getAllOccurrencesForEvent(int $eventId, int $perPage = 15, array $with = []): LengthAwarePaginator
    {
        Log::info("Service: Fetching occurrences for event ID: {$eventId} with relations: ", $with);
        return EventOccurrence::where('event_id', $eventId)
            ->with($with)
            ->latest()
            ->paginate($perPage);
    }

    public function findOccurrenceById(int $id, array $with = []): ?EventOccurrence
    {
        return EventOccurrence::with($with)->find($id);
    }

    public function createOccurrence(int $eventId, EventOccurrenceData $occurrenceData): EventOccurrence
    {
        // Ensure event_id is part of the data passed to the action, or handle it in the action
        // For now, assuming the action can take event_id separately or DTO is updated to include it if not already.
        return $this->upsertEventOccurrenceAction->execute($occurrenceData, null, $eventId);
    }

    public function updateOccurrence(int $occurrenceId, EventOccurrenceData $occurrenceData): EventOccurrence
    {
        return $this->upsertEventOccurrenceAction->execute($occurrenceData, $occurrenceId);
    }

    public function deleteOccurrence(int $occurrenceId): void
    {
        $occurrence = EventOccurrence::findOrFail($occurrenceId);
        // Add any related cleanup logic if necessary (e.g., deleting associated tickets)
        $occurrence->delete();
    }

    // You might add other methods here, e.g., for fetching occurrences based on different criteria
    // or for handling ticket associations with occurrences.
}
