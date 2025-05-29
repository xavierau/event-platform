<?php

namespace App\Services;

use App\Actions\Event\UpsertEventAction;
use App\DataTransferObjects\EventData;
use App\Models\Event;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth; // For setting created_by/updated_by if not handled in DTO/Action only

class EventService
{
    /**
     * Default number of days for upcoming events window
     */
    public const DEFAULT_UPCOMING_EVENTS_WINDOW_DAYS = 30;

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
        $withSubQuery = [
            'ticketDefinitions' => fn($subQuery) => $this->applyTicketAvailabilityFilter($subQuery)
        ];

        $with = [
            'category',
            'organizer',
            'tags',
            'eventOccurrences' => fn($query) => $query->with($withSubQuery)->where('status', 'scheduled')
        ];
        return Event::with($with)->find($id);
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
     * Get published events with future occurrences.
     *
     * @param int $limit
     * @param array $excludeIds
     * @param ?\Carbon\Carbon $startDate
     * @param ?\Carbon\Carbon $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getPublishedEventsWithFutureOccurrences(int $limit = null, array $excludeIds = [], ?\Carbon\Carbon $startDate = null, ?\Carbon\Carbon $endDate = null)
    {
        // Ensure all dates are in UTC for consistent comparison with start_at_utc fields
        $startDate = $startDate ? $startDate->utc() : now()->addYears(-3)->utc();
        $endDate = $endDate ? $endDate->utc() : now()->addYears(6)->utc();

        $eventStatuses = ['published', 'completed'];
        $occurrenceStatuses = ['active', 'scheduled', 'completed'];

        $query = $this->buildBaseEventQuery($eventStatuses, $startDate, $endDate, $occurrenceStatuses, true);

        $this->applyOccurrenceFilter($query, $startDate, $endDate, $occurrenceStatuses);

        // Exclude specific event IDs if provided
        $query->when(!empty($excludeIds), fn($q) => $q->whereNotIn('id', $excludeIds));

        $this->applyEarliestOccurrenceOrdering($query, $startDate, $endDate, $occurrenceStatuses);

        if ($limit) {
            $query->take($limit);
        }

        return $query;
    }

    /**
     * Get upcoming events for the homepage.
     *
     * @param int $limit Number of events to return.
     * @param ?\Carbon\Carbon $startDate Start date for filtering (defaults to now)
     * @param ?\Carbon\Carbon $endDate End date for filtering (defaults to 30 days from now)
     * @return array
     */
    public function getUpcomingEventsForHomepage(int $limit = 5, ?\Carbon\Carbon $startDate = null, ?\Carbon\Carbon $endDate = null)
    {
        // Ensure all dates are in UTC for consistent comparison with start_at_utc fields
        // Default startDate to the beginning of today for broader inclusion
        $queryStartDate = $startDate ? $startDate->utc()->startOfDay() : now()->utc()->startOfDay();

        // Get the upcoming events window from environment variable with fallback to constant
        $upcomingWindowDays = config('app.upcoming_events_window_days', self::DEFAULT_UPCOMING_EVENTS_WINDOW_DAYS);
        $queryEndDate = $endDate ? $endDate->utc() : now()->addDays($upcomingWindowDays)->utc()->endOfDay();

        $eventStatuses = ['published'];
        $occurrenceStatuses = ['active', 'scheduled'];

        $query = $this->buildBaseEventQuery($eventStatuses, $queryStartDate, $queryEndDate, $occurrenceStatuses);

        $this->applyOccurrenceFilter($query, $queryStartDate, $queryEndDate, $occurrenceStatuses);

        $this->applyEarliestOccurrenceOrdering($query, $queryStartDate, $queryEndDate, $occurrenceStatuses);

        return $query->take($limit)
            ->get()
            ->map(function (Event $event) {
                $firstOccurrence = $event->eventOccurrences->first();
                $ticketData = $this->extractTicketData($event->eventOccurrences);

                return $this->mapEventToArray($event, $ticketData, [
                    'date_short' => $firstOccurrence ? $this->formatDateShort($firstOccurrence->start_at_utc) : null,
                ]);
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
        $nowUtc = now()->utc(); // Ensure UTC for consistent comparison
        $endDate = now()->addYears(6)->utc(); // Far future date for broad inclusion

        $eventStatuses = ['published'];
        $occurrenceStatuses = ['active', 'scheduled'];

        $query = $this->buildBaseEventQuery($eventStatuses, $nowUtc, $endDate, $occurrenceStatuses);

        $this->applyOccurrenceFilter($query, $nowUtc, $endDate, $occurrenceStatuses);

        // Exclude events that are already shown in upcoming section
        $query->when(!empty($excludeIds), fn($q) => $q->whereNotIn('id', $excludeIds));

        $this->applyEarliestOccurrenceOrdering($query, $nowUtc, $endDate, $occurrenceStatuses);

        return $query->take($limit)
            ->get()
            ->map(function (Event $event) {
                $firstOccurrence = $event->eventOccurrences->first();
                $lastOccurrence = $event->eventOccurrences->last();
                $ticketData = $this->extractTicketData($event->eventOccurrences);

                return $this->mapEventToArray($event, $ticketData, [
                    'image_url' => $event->getFirstMediaUrl('portrait_poster') ?:
                        $event->getFirstMediaUrl('event_thumbnail') ?:
                        'https://via.placeholder.com/300x400.png?text=Event',
                    'date_range' => $this->formatDateRange(
                        $firstOccurrence ? $firstOccurrence->start_at_utc : null,
                        $lastOccurrence ? $lastOccurrence->start_at_utc : null,
                        $event->eventOccurrences->count()
                    ),
                    'venue_name' => $firstOccurrence && $firstOccurrence->venue
                        ? $firstOccurrence->venue->name
                        : ($event->getPrimaryVenue() ? $event->getPrimaryVenue()->name : null),
                ]);
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

    /**
     * Get events happening today.
     *
     * @param int $limit
     * @return array
     */
    public function getEventsToday(int $limit = 10): array
    {
        $todayStart = now()->utc()->startOfDay(); // Ensure UTC for consistent comparison
        $todayEnd = now()->utc()->endOfDay(); // Ensure UTC for consistent comparison

        $eventStatuses = ['published', 'completed'];
        $occurrenceStatuses = ['active', 'scheduled', 'completed'];

        $query = $this->buildBaseEventQuery($eventStatuses, $todayStart, $todayEnd, $occurrenceStatuses);

        $this->applyOccurrenceFilter($query, $todayStart, $todayEnd, $occurrenceStatuses);

        $this->applyEarliestOccurrenceOrdering($query, $todayStart, $todayEnd, $occurrenceStatuses);

        $events = $query->take($limit)
            ->get()
            ->map(function (Event $event) use ($todayStart, $todayEnd) {
                // Re-filter occurrences to be absolutely sure only today's are considered for mapping
                $todaysOccurrences = $event->eventOccurrences
                    ->where('start_at_utc', '>=', $todayStart)
                    ->where('start_at_utc', '<=', $todayEnd)
                    ->sortBy('start_at_utc');

                $firstOccurrence = $todaysOccurrences->first();
                $ticketData = $this->extractTicketData($todaysOccurrences);

                return $this->mapEventToArray($event, $ticketData, [
                    'start_time' => $firstOccurrence ? $firstOccurrence->start_at_utc->format('H:i') : null,
                    'venue_name' => $firstOccurrence && $firstOccurrence->venue ? $firstOccurrence->venue->name : null,
                ]);
            })
            ->toArray();

        return $events;
    }

    /**
     * Get events by category with future occurrences.
     *
     * @param int $categoryId
     * @param int $limit
     * @param array $excludeIds
     * @return array
     */
    public function getEventsByCategory(int $categoryId, int $limit = 20, array $excludeIds = []): array
    {
        $startDate = now()->addYears(-3)->utc();
        $endDate = now()->addYears(6)->utc();
        $eventStatuses = ['published', 'completed'];
        $occurrenceStatuses = ['active', 'scheduled', 'completed'];

        $query = $this->buildBaseEventQuery($eventStatuses, $startDate, $endDate, $occurrenceStatuses);

        $this->applyOccurrenceFilter($query, $startDate, $endDate, $occurrenceStatuses);

        $query->where('category_id', $categoryId)
            ->when(!empty($excludeIds), fn($q) => $q->whereNotIn('id', $excludeIds));

        $this->applyEarliestOccurrenceOrdering($query, $startDate, $endDate, $occurrenceStatuses);

        return $query->take($limit)
            ->get()
            ->map(function (Event $event) {
                $firstOccurrence = $event->eventOccurrences->first();
                $lastOccurrence = $event->eventOccurrences->last();
                $ticketData = $this->extractTicketData($event->eventOccurrences);

                return $this->mapEventToArray($event, $ticketData, [
                    'date_range' => $this->formatDateRange(
                        $firstOccurrence ? $firstOccurrence->start_at_utc : null,
                        $lastOccurrence ? $lastOccurrence->start_at_utc : null,
                        $event->eventOccurrences->count()
                    ),
                    'venue_name' => $firstOccurrence && $firstOccurrence->venue ?
                        $firstOccurrence->venue->name : ($event->getPrimaryVenue() ? $event->getPrimaryVenue()->name : null),
                ]);
            })->toArray();
    }

    /**
     * Apply ticket availability window filtering to a query.
     *
     * Tickets are considered available if:
     * 1. No availability window is set (both start and end are null), OR
     * 2. Current time is within the availability window (after start AND before end)
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Carbon\Carbon|null $currentTime
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function applyTicketAvailabilityFilter($query, ?\Carbon\Carbon $currentTime = null)
    {
        $nowUtc = $currentTime ? $currentTime->utc() : now()->utc();

        return $query->where(function ($q) use ($nowUtc) {
            // Case 1: No availability window (both start and end are null)
            $q->whereNull('availability_window_start_utc')
                ->whereNull('availability_window_end_utc');
        })->orWhere(function ($q) use ($nowUtc) {
            // Case 2: Within availability window (after start AND before end)
            $q->where('availability_window_start_utc', '<=', $nowUtc)
                ->where('availability_window_end_utc', '>=', $nowUtc);
        });
    }

    /**
     * Build base event query with common eager loading relationships.
     *
     * @param array $eventStatuses
     * @param \Carbon\Carbon $startDate
     * @param \Carbon\Carbon $endDate
     * @param array $occurrenceStatuses
     * @param bool $includeVenue
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function buildBaseEventQuery(
        array $eventStatuses,
        \Carbon\Carbon $startDate,
        \Carbon\Carbon $endDate,
        array $occurrenceStatuses,
        bool $includeVenue = false
    ) {
        $with = [
            'category',
            'media',
            'eventOccurrences' => fn($query) => $query->where('start_at_utc', '>=', $startDate)
                ->where('start_at_utc', '<=', $endDate)
                ->whereIn('status', $occurrenceStatuses)
                ->orderBy('start_at_utc', 'asc'),
            'eventOccurrences.ticketDefinitions' => fn($query) => $this->applyTicketAvailabilityFilter($query)
        ];

        if ($includeVenue) {
            $with['eventOccurrences.venue'] = fn($query) => $query;
        }

        return Event::query()
            ->whereIn('event_status', $eventStatuses)
            ->with($with);
    }

    /**
     * Apply occurrence filtering to event query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Carbon\Carbon $startDate
     * @param \Carbon\Carbon $endDate
     * @param array $occurrenceStatuses
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function applyOccurrenceFilter(
        $query,
        \Carbon\Carbon $startDate,
        \Carbon\Carbon $endDate,
        array $occurrenceStatuses
    ) {
        return $query->whereHas('eventOccurrences', fn($q) => $q->where('start_at_utc', '>=', $startDate)
            ->where('start_at_utc', '<=', $endDate)
            ->whereIn('status', $occurrenceStatuses));
    }

    /**
     * Apply ordering by earliest occurrence within date range.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Carbon\Carbon $startDate
     * @param \Carbon\Carbon $endDate
     * @param array $occurrenceStatuses
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function applyEarliestOccurrenceOrdering(
        $query,
        \Carbon\Carbon $startDate,
        \Carbon\Carbon $endDate,
        array $occurrenceStatuses
    ) {
        return $query->orderBy(fn($subQuery) => $subQuery->select('start_at_utc')
            ->from('event_occurrences')
            ->whereColumn('event_id', 'events.id')
            ->where('start_at_utc', '>=', $startDate)
            ->where('start_at_utc', '<=', $endDate)
            ->whereIn('status', $occurrenceStatuses)
            ->orderBy('start_at_utc')
            ->limit(1));
    }

    /**
     * Extract ticket data from event occurrences.
     *
     * @param \Illuminate\Support\Collection $occurrences
     * @return array ['prices' => Collection, 'currency' => string]
     */
    private function extractTicketData($occurrences): array
    {
        $ticketData = $occurrences->flatMap(function ($occurrence) {
            return $occurrence->ticketDefinitions->map(function ($ticket) {
                return [
                    'price' => $ticket->price,
                    'currency' => $ticket->currency
                ];
            });
        });

        $prices = $ticketData->pluck('price');
        $currency = $ticketData->first()['currency'] ?? 'HKD';

        return [
            'prices' => $prices,
            'currency' => $currency
        ];
    }

    /**
     * Map event to basic array format with common fields.
     *
     * @param Event $event
     * @param array $ticketData
     * @param array $additionalFields
     * @return array
     */
    private function mapEventToArray(Event $event, array $ticketData, array $additionalFields = []): array
    {
        $baseData = [
            'id' => $event->id,
            'name' => $event->name,
            'href' => route('events.show', $event->id),
            'image_url' => $event->getFirstMediaUrl('portrait_poster') ?: 'https://via.placeholder.com/400x300.png?text=Event',
            'price_from' => $ticketData['prices']->min() / 100 ?? null,
            'price_to' => $ticketData['prices']->max() / 100 ?? null,
            'currency' => $ticketData['currency'],
            'category_name' => $event->category ? $event->category->name : null,
        ];

        return array_merge($baseData, $additionalFields);
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
            return now()->utc(); // Fallback to UTC for consistency
        }
    }
}
