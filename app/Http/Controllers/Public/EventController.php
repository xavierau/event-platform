<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\EventService;
use App\Services\CategoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

if (!function_exists('carbonSafeParse')) {
    function carbonSafeParse($date, $timezone = null)
    {
        if ($date instanceof \Carbon\Carbon) {
            return $date;
        }
        try {
            return \Carbon\Carbon::parse($date, $timezone);
        } catch (\Exception $e) {
            return now()->utc(); // Fallback to UTC for consistency
        }
    }
}

class EventController extends Controller
{
    protected EventService $eventService;
    protected CategoryService $categoryService;

    public function __construct(EventService $eventService, CategoryService $categoryService)
    {
        $this->eventService = $eventService;
        $this->categoryService = $categoryService;
    }

    /**
     * Show a list of events, optionally filtered by category.
     */
    public function index(Request $request): Response
    {
        $categorySlug = $request->query('category');
        $category = null;
        $categoryName = __('全部活动');
        $posterUrl = null;
        $events = collect();

        // Date range filtering
        $start = $request->query('start');
        $end = $request->query('end');
        $startDate = $start ? \Carbon\Carbon::parse($start, 'UTC')->startOfDay() : null;
        $endDate = $end ? \Carbon\Carbon::parse($end, 'UTC')->endOfDay() : null;

        $eventFilters = ['event_status' => 'published'];
        if ($startDate) {
            $eventFilters['start_date_utc'] = $startDate;
        }
        if ($endDate) {
            $eventFilters['end_date_utc'] = $endDate;
        }

        if ($categorySlug) {
            $category = $this->categoryService->getAllCategories(
                ['slug' => $categorySlug],
                ['events' => function ($query) {
                    $query->where('event_status', 'published')
                        ->whereHas('eventOccurrences', function ($q) {
                            $q->where('start_at_utc', '>', now()->utc());
                        });
                }]
            )->first();
            if ($category) {
                $categoryName = $category->name;
                $posterUrl = $category->getFirstMediaUrl('category_landscape_poster') ?: null;
                $eventFilters['category_id'] = $category->id;
                $events = $this->eventService->getAllEvents($eventFilters, 20);
            }
        }
        if (!$category) {
            // No category filter or not found, show all events
            $events = $this->eventService->getAllEvents($eventFilters, 20);
        }

        // Map events to the structure expected by EventListItem
        $eventList = $events->map(function ($event) {
            $firstOccurrence = $event->eventOccurrences->first();
            $lastOccurrence = $event->eventOccurrences->last();
            return [
                'id' => $event->id,
                'name' => $event->name,
                'href' => route('events.show', $event->id),
                'image_url' => $event->getFirstMediaUrl('portrait_poster') ?: $event->getFirstMediaUrl('event_thumbnail') ?: 'https://via.placeholder.com/300x400.png?text=Event',
                'price_from' => $event->eventOccurrences->flatMap(function ($occurrence) {
                    return $occurrence->ticketDefinitions->pluck('price');
                })->min() / 100 ?? null,
                'date_range' => $this->formatDateRange($firstOccurrence ? $firstOccurrence->start_at : null, $lastOccurrence ? $lastOccurrence->start_at : null, $event->eventOccurrences->count()),
                'venue_name' => $firstOccurrence && $firstOccurrence->venue ? $firstOccurrence->venue->name : ($event->primaryVenue ? $event->primaryVenue->name : null),
                'category_name' => $event->category ? $event->category->name : null,
            ];
        });

        return Inertia::render('Public/EventsByCategory', [
            'title' => $categoryName,
            'poster_url' => $posterUrl,
            'events' => $eventList,
        ]);
    }

    private function formatDateRange($startDate, $endDate, int $occurrenceCount = 1): ?string
    {
        if (!$startDate) return null;
        $start = carbonSafeParse($startDate);
        if ($occurrenceCount === 1 || !$endDate || $start->isSameDay(carbonSafeParse($endDate))) {
            return $start->translatedFormat('Y.m.d');
        }
        $end = carbonSafeParse($endDate);
        if ($start->isSameMonth($end)) {
            return $start->translatedFormat('Y.m.d') . '-' . $end->translatedFormat('d');
        }
        return $start->translatedFormat('Y.m.d') . '-' . $end->translatedFormat('Y.m.d');
    }

    public function show($eventIdentifier)
    {
        // Load the published event with all necessary relationships
        $event = \App\Models\Event::findPublishedByIdentifier($eventIdentifier, [
            'category',
            'eventOccurrences' => function ($query) {
                $query->orderBy('start_at_utc', 'asc');
            },
            'eventOccurrences.venue',
            'eventOccurrences.ticketDefinitions'
        ]);

        // Calculate price range using model method
        $priceRange = $event->getPriceRange();

        // Get primary venue using model method
        $primaryVenue = $event->getPrimaryVenue();

        // Transform the event data to match the expected format
        $eventData = [
            'id' => $event->id,
            'name' => $event->getTranslation('name', app()->getLocale()),
            'category_tag' => $event->category?->getTranslation('name', app()->getLocale()),
            'duration_info' => $event->duration_info,
            'price_range' => $priceRange,
            'discount_info' => $event->discount_info,
            'main_poster_url' => $event->getFirstMediaUrl('portrait_poster'),
            'thumbnail_url' => $event->getFirstMediaUrl('portrait_poster', 'thumb'),
            'landscape_poster_url' => $event->getFirstMediaUrl('landscape_poster'),
            'description_html' => $event->getTranslation('description', app()->getLocale()),
            'venue_name' => $primaryVenue?->getTranslation('name', app()->getLocale()),
            'venue_address' => $primaryVenue?->address,
            'occurrences' => $event->eventOccurrences->map(function ($occurrence) {
                return [
                    'id' => $occurrence->id,
                    'name' => $occurrence->getTranslation('name', app()->getLocale()) ?: 'Event Occurrence',
                    'date_short' => $occurrence->start_at_utc?->format('m.d'),
                    'full_date_time' => $occurrence->start_at_utc?->format('Y.m.d') . ' ' .
                        $occurrence->start_at_utc?->locale(app()->getLocale())->isoFormat('dddd') . ' ' .
                        $occurrence->start_at_utc?->format('H:i'),
                    'status_tag' => $occurrence->status,
                    'venue_name' => $occurrence->venue?->getTranslation('name', app()->getLocale()),
                    'venue_address' => $occurrence->venue?->address,
                    'tickets' => $occurrence->ticketDefinitions->map(function ($ticket) {
                        // Calculate effective price (use override if available)
                        $effectivePrice = $ticket->pivot->price_override ?? $ticket->price;

                        // Calculate available quantity for this occurrence
                        $quantityForOccurrence = $ticket->pivot->quantity_for_occurrence;
                        $availableQuantity = $quantityForOccurrence ?? $ticket->quantity_available;

                        return [
                            'id' => $ticket->id,
                            'name' => $ticket->getTranslation('name', app()->getLocale()),
                            'description' => $ticket->getTranslation('description', app()->getLocale()),
                            'currency' => $ticket->currency,
                            'price' => $effectivePrice / 100, // Convert from cents to currency units
                            'max_per_order' => $ticket->max_per_order,
                            'min_per_order' => $ticket->min_per_order,
                            'quantity_available' => $availableQuantity
                        ];
                    })->toArray()
                ];
            })->toArray()
        ];

        return \Inertia\Inertia::render('Public/EventDetail', [
            'event' => $eventData,
        ]);
    }
}
