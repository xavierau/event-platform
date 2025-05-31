<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventOccurrence;
use Carbon\Carbon;
use App\Models\TicketDefinition;

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
            'eventOccurrences.ticketDefinitions',
        ]);

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
        ];
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
     *
     * TODO: This method currently includes all ticket definitions regardless of their availability status.
     * We should filter out tickets that are not currently available (e.g., sold out, not yet on sale, or past sale date)
     * to ensure we only show relevant pricing information to users.
     *
     * @param Event $event
     * @return int|null
     */
    protected function calculateMinimumPrice(Event $event): ?int
    {
        $minPrice = $event->eventOccurrences->flatMap(function ($occurrence) {
            return $occurrence->ticketDefinitions->pluck('price');
        })->min();

        return $minPrice ? intval($minPrice / 100) : null;
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
