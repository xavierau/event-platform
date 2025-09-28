<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\PublicEventDisplayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    protected PublicEventDisplayService $publicEventDisplayService;

    public function __construct(PublicEventDisplayService $publicEventDisplayService)
    {
        $this->publicEventDisplayService = $publicEventDisplayService;
    }

    /**
     * Show a list of events, optionally filtered by category.
     */
    public function index(Request $request): Response
    {
        $categorySlug = $request->query('category');

        // Parse date range filters
        $startDate = $request->query('start') ?
            \Carbon\Carbon::parse($request->query('start'), 'UTC')->startOfDay() : null;
        $endDate = $request->query('end') ?
            \Carbon\Carbon::parse($request->query('end'), 'UTC')->endOfDay() : null;

        // Get events data from service
        $data = $this->publicEventDisplayService->getEventsForListing($categorySlug, $startDate, $endDate);

        return Inertia::render('Public/EventsByCategory', $data);
    }

    /**
     * Show event details.
     */
    public function show($eventIdentifier): Response
    {
        // First, check if this is a slug and detect the locale
        if (!is_numeric($eventIdentifier)) {
            // Find the event by slug to detect locale
            $event = Event::findPublishedByIdentifier($eventIdentifier);

            if ($event) {
                $detectedLocale = $event->getLocaleBySlug($eventIdentifier);

                if ($detectedLocale) {
                    // Set the application locale
                    app()->setLocale($detectedLocale);

                    // Save to session for persistence
                    Session::put('locale', $detectedLocale);
                }
            }
        }

        $eventData = $this->publicEventDisplayService->getEventDetailData($eventIdentifier);

        return Inertia::render('Public/EventDetail', [
            'event' => $eventData,
        ]);
    }
}
