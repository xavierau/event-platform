<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventOccurrence;
use Carbon\Carbon;
use App\Models\TicketDefinition;
use App\Enums\CommentStatusEnum;

class PublicEventDisplayService
{
    protected EventService $eventService;

    protected CategoryService $categoryService;

    public function __construct(EventService $eventService, CategoryService $categoryService)
    {
        $this->eventService = $eventService;
        $this->categoryService = $categoryService;
    }

    /**
     * Get events for the public listing page
     */
    public function getEventsForListing(?string $categorySlug = null, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $category = null;
        $categoryName = __('messages.All Events');
        $posterUrl = null;

        if ($categorySlug) {
            $category = $this->categoryService->getCategoryBySlug(
                $categorySlug,
                ['events' => function ($query) {
                    $query->where('event_status', 'published')
                        ->whereHas('eventOccurrences', function ($q) {
                            $q->where('start_at_utc', '>', now()->utc());
                        });
                }]
            );

            if ($category) {
                $categoryName = $category->name;
                $posterUrl = $category->getFirstMediaUrl('category_landscape_poster') ?: null;
            }
        }

        // Get events using EventService
        $eventsQuery = $this->eventService->getPublishedEventsWithFutureOccurrences(20, [], $startDate, $endDate);

        if ($category) {
            $eventsQuery->where('category_id', $category->id);
        }

        $events = $eventsQuery->get();

        return [
            'title' => $categoryName,
            'poster_url' => $posterUrl,
            'events' => $this->mapEventsForListing($events),
        ];
    }

    /**
     * Map events to the structure expected by EventListItem component
     */
    public function mapEventsForListing($events): array
    {
        return $events->map(function ($event) {
            return $this->mapEventForListing($event);
        })->toArray();
    }

    /**
     * Map a single event for listing display
     */
    public function mapEventForListing(Event $event): array
    {
        $firstOccurrence = $event->eventOccurrences->first();
        $lastOccurrence = $event->eventOccurrences->last();

        return [
            'id' => $event->id,
            'name' => $event->name,
            'href' => route('events.show', $event->id),
            'image_url' => $this->getEventImageUrl($event),
            'price_from' => $this->calculateMinimumPrice($event),
            'date_range' => $this->formatDateRange(
                $firstOccurrence?->start_at_utc,
                $lastOccurrence?->start_at_utc,
                $event->eventOccurrences->count()
            ),
            'venue_name' => $this->getEventVenueName($event, $firstOccurrence),
            'category_name' => $event->category?->name,
        ];
    }

    /**
     * Get event detail data for the show page
     */
    public function getEventDetailData(string|int $eventIdentifier): array
    {
        // Load the published event with all necessary relationships
        $event = Event::findPublishedByIdentifier($eventIdentifier, [
            'category',
            'eventOccurrences' => function ($query) {
                $query->orderBy('start_at_utc', 'asc');
            },
            'eventOccurrences.venue',
            'eventOccurrences.ticketDefinitions' => function ($query) {
                // Apply the same availability filtering as EventService
                $this->applyTicketAvailabilityFilter($query);
            },
        ]);

        if (!$event) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Event not found with identifier: {$eventIdentifier}");
        }

        // Load approved comments with user relationship, ordered by latest
        $comments = $event->comments()
            ->where('status', 'APPROVED')
            ->with(['user' => fn($query) => $query->select('id', 'name')])
            ->latest()
            ->get();

        // Calculate price range using model method
        $priceRange = $event->getPriceRange();

        // Get primary venue using model method
        $primaryVenue = $event->getPrimaryVenue();

        return [
            'id' => $event->id,
            'name' => $event->name,
            'category_tag' => $event->category?->name,
            'price_range' => $priceRange,
            'main_poster_url' => $event->getFirstMediaUrl('portrait_poster'),
            'thumbnail_url' => $event->getFirstMediaUrl('portrait_poster', 'thumb'),
            'landscape_poster_url' => $event->getFirstMediaUrl('landscape_poster'),
            'description_html' => $event->description,
            'venue_name' => $primaryVenue?->name,
            'venue_address' => $primaryVenue?->address,
            'occurrences' => $this->mapEventOccurrences($event->eventOccurrences),
            'comments' => $comments->toArray(),
            'comment_config' => $event->comment_config,
        ];
    }

    /**
     * Apply ticket availability window filtering to a query.
     * This duplicates the logic from EventService to ensure consistency.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Carbon\Carbon|null $currentTime
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function applyTicketAvailabilityFilter($query, ?\Carbon\Carbon $currentTime = null)
    {
        $nowUtc = $currentTime ? $currentTime->utc() : now()->utc();

        return $query->where(function ($q) use ($nowUtc) {
            // Case 1: No availability window (both start and end are null)
            $q->where(function ($subQ) {
                $subQ->whereNull('availability_window_start_utc')
                    ->whereNull('availability_window_end_utc');
            })
                // Case 2: Only start time is set (available from start time onwards)
                ->orWhere(function ($subQ) use ($nowUtc) {
                    $subQ->whereNotNull('availability_window_start_utc')
                        ->where('availability_window_start_utc', '<=', $nowUtc)
                        ->whereNull('availability_window_end_utc');
                })
                // Case 3: Only end time is set (available until end time)
                ->orWhere(function ($subQ) use ($nowUtc) {
                    $subQ->whereNull('availability_window_start_utc')
                        ->whereNotNull('availability_window_end_utc')
                        ->where('availability_window_end_utc', '>=', $nowUtc);
                })
                // Case 4: Both start and end times are set (within availability window)
                ->orWhere(function ($subQ) use ($nowUtc) {
                    $subQ->whereNotNull('availability_window_start_utc')
                        ->where('availability_window_start_utc', '<=', $nowUtc)
                        ->whereNotNull('availability_window_end_utc')
                        ->where('availability_window_end_utc', '>=', $nowUtc);
                });
        });
    }

    /**
     * Map event occurrences for detail display
     */
    protected function mapEventOccurrences($occurrences): array
    {
        return $occurrences->map(function ($occurrence) {
            $publicData = $occurrence->getPublicData();
            // Now, explicitly map the ticket definitions using the service's own method
            // $publicData['tickets'] already contains the collection from $occurrence->ticketDefinitions
            $publicData['tickets'] = $this->mapTicketDefinitions($publicData['tickets']);
            return $publicData;
        })->toArray();
    }

    /**
     * Map ticket definitions for display
     */
    protected function mapTicketDefinitions($ticketDefinitions): array
    {
        return $ticketDefinitions->map(function (TicketDefinition $ticket) {
            return $ticket->getPublicData();
        })->toArray();
    }

    /**
     * Format date range for display
     */
    public function formatDateRange($startDate, $endDate, int $occurrenceCount = 1): ?string
    {
        if (! $startDate) {
            return null;
        }

        $start = $this->carbonSafeParse($startDate);

        if ($occurrenceCount === 1 || ! $endDate || $start->isSameDay($this->carbonSafeParse($endDate))) {
            return $start->translatedFormat('Y.m.d');
        }

        $end = $this->carbonSafeParse($endDate);

        if ($start->isSameMonth($end)) {
            return $start->translatedFormat('Y.m.d') . '-' . $end->translatedFormat('d');
        }

        return $start->translatedFormat('Y.m.d') . '-' . $end->translatedFormat('Y.m.d');
    }

    /**
     * Format full date time for display
     */
    protected function formatFullDateTime(?Carbon $localStartTime): string
    {
        if (! $localStartTime) {
            return '';
        }

        return $localStartTime->format('Y.m.d') . ' ' .
            $localStartTime->locale(app()->getLocale())->isoFormat('dddd') . ' ' .
            $localStartTime->format('H:i');
    }

    /**
     * Get event image URL with fallbacks
     */
    protected function getEventImageUrl(Event $event): string
    {
        return $event->getFirstMediaUrl('portrait_poster') ?:
            $event->getFirstMediaUrl('event_thumbnail') ?:
            'https://via.placeholder.com/300x400.png?text=Event';
    }

    /**
     * Calculate minimum price from all event occurrences
     * Uses the Event model's getPriceRange method which applies availability filtering
     *
     * @param Event $event
     * @return int|null
     */
    protected function calculateMinimumPrice(Event $event): ?int
    {
        $priceRange = $event->getPriceRange();

        if (! $priceRange) {
            return null;
        }

        // More robust regex to find the first number (integer or decimal)
        if (preg_match('/[\d,]+(?:\.\d+)?/', $priceRange, $matches)) {
            // Remove commas, convert to float, then to int (to handle cents correctly)
            $numericValue = floatval(str_replace(',', '', $matches[0]));
            return intval($numericValue);
        }

        return null;
    }

    /**
     * Get venue name for event listing
     */
    protected function getEventVenueName(Event $event, ?EventOccurrence $firstOccurrence): ?string
    {
        if ($firstOccurrence && $firstOccurrence->venue) {
            // Cast array to string since name is translatable
            return (string) $firstOccurrence->venue->name;
        }

        return (string) $event->getPrimaryVenue()?->name;
    }

    /**
     * Safe Carbon parsing with fallback
     */
    protected function carbonSafeParse($date, $timezone = null): Carbon
    {
        if ($date instanceof Carbon) {
            return $date;
        }

        try {
            return Carbon::parse($date, $timezone);
        } catch (\Exception $e) {
            return now()->utc(); // Fallback to UTC for consistency
        }
    }
}
