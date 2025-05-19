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
        $query = Event::query()->with(['category', 'organizer', 'tags', 'eventOccurrences']);

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

        // Date range filter for event occurrences (UTC)
        if (isset($filters['start_date_utc']) || isset($filters['end_date_utc'])) {
            $start = $filters['start_date_utc'] ?? null;
            $end = $filters['end_date_utc'] ?? null;
            $query->whereHas('eventOccurrences', function ($q) use ($start, $end) {
                if ($start) {
                    $q->where('start_at_utc', '>=', $start);
                }
                if ($end) {
                    $q->where('start_at_utc', '<=', $end);
                }
                $q->where('status', 'scheduled');
            });
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

    /**
     * Get upcoming events for the homepage.
     *
     * @param int $limit Number of events to return.
     * @return array
     */
    public function getUpcomingEventsForHomepage(int $limit = 5, ?\Carbon\Carbon $startDate = null, ?\Carbon\Carbon $endDate = null)
    {
        // Default date range is today if not specified
        $startDate = $startDate ?? now()->startOfDay();
        $endDate = $endDate ?? now()->endOfDay();

        return Event::query()
            ->where('event_status', 'published')
            ->with([
                'category',
                'media',
                'eventOccurrences' => function ($query) {
                    $query->orderBy('start_at_utc', 'asc'); // Get occurrences in order
                },
                'eventOccurrences.ticketDefinitions' => function ($query) {
                    $query->whereNull('availability_window_start_utc')
                        ->orWhere('availability_window_start_utc', '<=', now());
                }
            ])
            // Filter events that have at least one occurrence today
            ->whereHas('eventOccurrences', function ($query) use ($startDate, $endDate) {
                $query->whereDate('start_at_utc', '>=', $startDate)
                    ->whereDate('start_at_utc', '<=', $endDate)
                    ->where('status', 'scheduled');
            })
            ->orderBy(function ($query) {
                return $query->select('start_at')
                    ->from('event_occurrences')
                    ->whereColumn('event_id', 'events.id')
                    ->where('start_at_utc', '>=', now())
                    ->orderBy('start_at_utc')
                    ->limit(1);
            })
            ->take($limit)
            ->get()
            ->map(function (Event $event) {
                // Transform event for frontend needs, e.g., getting the first image URL
                // and soonest occurrence details.
                $firstOccurrence = $event->eventOccurrences->first();
                return [
                    'id' => $event->id,
                    'name' => $event->name, // Translatable
                    'href' => route('events.show', $event->id), // Assuming route exists
                    'image_url' => $event->getFirstMediaUrl('portrait_poster') ?: 'https://via.placeholder.com/400x300.png?text=Event', // Placeholder image
                    'price_from' => $event->eventOccurrences->flatMap(function ($occurrence) {
                        return $occurrence->ticketDefinitions->pluck('price');
                    })->min() / 100 ?? null,
                    'date_short' => $firstOccurrence ? $this->formatDateShort($firstOccurrence->start_at) : null,
                    'category_name' => $event->category ? $event->category->name : null, // Translatable
                ];
            });
    }

    /**
     * Helper to format date for display (e.g., JUL 15).
     *
     * @param \Carbon\Carbon|string $date
     * @return string|null
     */
    private function formatDateShort($date): ?string
    {
        if (!$date) return null;
        return strtoupper(carbonSafeParse($date)->translatedFormat('M d')); // Uses Carbon's localization if set
    }

    /**
     * Get more events for the homepage, potentially different from upcoming.
     *
     * @param int $limit
     * @param array $excludeIds IDs of events to exclude (e.g., those already in upcoming)
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function getMoreEventsForHomepage(int $limit = 4, array $excludeIds = [])
    {
        return Event::query()
            ->where('event_status', 'published')
            ->with([
                'category',
                'media',
                'eventOccurrences' => function ($query) {
                    $query->orderBy('start_at_utc', 'asc');
                },
                'eventOccurrences.ticketDefinitions' => function ($query) {
                    $query->whereNull('availability_window_start_utc')
                        ->orWhere('availability_window_start_utc', '<=', now());
                }
            ])
            ->whereHas('eventOccurrences', function ($query) {
                $query->where('start_at_utc', '>=', now())
                    ->where('status', 'scheduled');
            })
            // ->when(!empty($excludeIds), function ($query) use ($excludeIds) {
            //     return $query->whereNotIn('id', $excludeIds);
            //     return  $query->whereIn('id', $excludeIds ?? []);
            // })
            ->orderBy(function ($query) {
                return $query->select('start_at')
                    ->from('event_occurrences')
                    ->whereColumn('event_id', 'events.id')
                    ->where('start_at_utc', '>=', now())
                    ->orderBy('start_at_utc')
                    ->limit(1);
            }) // Order by the earliest upcoming occurrence start date
            // ->take($limit)
            ->get()
            ->map(function (Event $event) {
                $firstOccurrence = $event->eventOccurrences->first();
                $lastOccurrence = $event->eventOccurrences->last();

                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'href' => route('events.show', $event->id),
                    'image_url' => $event->getFirstMediaUrl('portrait_poster') ?: $event->getFirstMediaUrl('event_thumbnail') ?: 'https://via.placeholder.com/300x400.png?text=Event', // Try poster, then thumbnail
                    'price_from' => $event->eventOccurrences->flatMap(function ($occurrence) {
                        return $occurrence->ticketDefinitions->pluck('price');
                    })->min() / 100 ?? null,
                    'date_range' => $this->formatDateRange($firstOccurrence ? $firstOccurrence->start_at : null, $lastOccurrence ? $lastOccurrence->start_at : null, $event->eventOccurrences->count()),
                    'venue_name' => $firstOccurrence && $firstOccurrence->venue ? $firstOccurrence->venue->name : ($event->primaryVenue ? $event->primaryVenue->name : null), // Assuming occurrences have a venue, or event has a primaryVenue relationship/attribute
                    'category_name' => $event->category ? $event->category->name : null,
                ];
            });
    }

    /**
     * Helper to format a date range.
     *
     * @param \Carbon\Carbon|string|null $startDate
     * @param \Carbon\Carbon|string|null $endDate
     * @param int $occurrenceCount
     * @return string|null
     */
    private function formatDateRange($startDate, $endDate, int $occurrenceCount = 1): ?string
    {
        if (!$startDate) return null;
        $start = carbonSafeParse($startDate);

        if ($occurrenceCount === 1 || !$endDate || $start->isSameDay(carbonSafeParse($endDate))) {
            return $start->translatedFormat('Y.m.d'); // Single date
        }
        $end = carbonSafeParse($endDate);
        if ($start->isSameMonth($end)) {
            return $start->translatedFormat('Y.m.d') . '-' . $end->translatedFormat('d'); // e.g., 2025.06.13-15
        }
        return $start->translatedFormat('Y.m.d') . '-' . $end->translatedFormat('Y.m.d'); // e.g., 2025.06.13-2025.07.15
    }
}

// Helper function for safe Carbon parsing (add to a helper file or base service class if used widely)
if (!function_exists('carbonSafeParse')) {
    function carbonSafeParse($date, $timezone = null)
    {
        if ($date instanceof \Carbon\Carbon) {
            return $date;
        }
        try {
            return \Carbon\Carbon::parse($date, $timezone);
        } catch (\Exception $e) {
            // Log error or handle appropriately
            return now(); // Fallback or throw error
        }
    }
}
