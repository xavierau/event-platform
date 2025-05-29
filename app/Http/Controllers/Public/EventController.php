<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\PublicEventDisplayService;
use Illuminate\Http\Request;
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
        $eventData = $this->publicEventDisplayService->getEventDetailData($eventIdentifier);

        return Inertia::render('Public/EventDetail', [
            'event' => $eventData,
        ]);
    }
}
