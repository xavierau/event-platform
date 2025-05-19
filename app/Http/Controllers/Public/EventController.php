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
            return now();
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
        $categoryName = '全部活动';
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
                            $q->where('start_at_utc', '>', now());
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
}
