<?php

namespace App\Services;

use App\Actions\Event\UpsertEventAction;
use App\DataTransferObjects\EventData;
use App\Models\Event;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth; // For setting created_by/updated_by if not handled in DTO/Action only

class EventService
{
    public function __construct(
        protected UpsertEventAction $upsertEventAction
    ) {}

    public function createEvent(EventData $eventData): Event
    {
        // Ensure created_by and updated_by are set if DTO allows null and action expects them
        // The current DTO has nullable created_by/updated_by, and Action sets them.
        // So, no need to set them explicitly here if Auth::id() is the source.
        // If they were to be passed from controller via DTO:
        // $eventData = $eventData->clone(created_by: Auth::id(), updated_by: Auth::id());
        return $this->upsertEventAction->execute($eventData);
    }

    public function updateEvent(EventData $eventData): Event
    {
        // The UpsertEventAction handles finding the event if $eventData->id is present.
        // It also sets updated_by.
        // $eventData = $eventData->clone(updated_by: Auth::id()); // Only if DTO needs to carry it explicitly
        if (is_null($eventData->id)) {
            throw new \InvalidArgumentException('Event ID must be provided for an update operation.');
        }
        return $this->upsertEventAction->execute($eventData);
    }

    public function findEventById(int $id): ?Event
    {
        return Event::with(['category', 'organizer', 'tags'])->find($id);
    }

    public function getAllEvents(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $direction = 'desc'): LengthAwarePaginator
    {
        $query = Event::query()->with(['category', 'organizer', 'tags']);

        // Example filter: by organizer_id
        if (isset($filters['organizer_id'])) {
            $query->where('organizer_id', $filters['organizer_id']);
        }

        // Example filter: by category_id
        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // Example filter: by event_status
        if (isset($filters['event_status'])) {
            $query->where('event_status', $filters['event_status']);
        }

        // Example search: by name (translatable)
        if (isset($filters['search_name']) && !empty($filters['search_name'])) {
            $searchTerm = $filters['search_name'];
            $query->where(function ($q) use ($searchTerm) {
                // Search in current locale and fallback locale for the name
                $q->whereJsonContains("name->" . app()->getLocale(), $searchTerm)
                    ->orWhereJsonContains("name->" . config('app.fallback_locale'), $searchTerm);
            });
        }

        $query->orderBy($orderBy, $direction);

        return $query->paginate($perPage);
    }

    public function deleteEvent(Event $event): bool
    {
        // Business logic before deletion, e.g., cannot delete if it has active bookings etc.
        // For now, direct deletion:
        return $event->delete(); // Uses SoftDeletes
    }
}
