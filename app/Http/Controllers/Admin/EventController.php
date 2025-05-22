<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\EventService;
use App\DataTransferObjects\EventData;
use App\Models\Category; // For fetching categories
use App\Models\Tag;       // For fetching tags
use App\Models\User;      // For fetching organizers (if selectable)
use App\Models\Venue;     // For fetching venues
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Illuminate\Http\RedirectResponse;

class EventController extends Controller
{
    protected EventService $eventService;

    public function __construct(EventService $eventService)
    {
        $this->eventService = $eventService;
        // Optional: Add middleware for authorization (e.g., using Spatie/laravel-permission)
        // $this->middleware('can:view events')->only(['index', 'show']);
        // $this->middleware('can:create events')->only(['create', 'store']);
        // $this->middleware('can:edit events')->only(['edit', 'update']);
        // $this->middleware('can:delete events')->only(['destroy']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): InertiaResponse
    {
        $filters = $request->only(['search_name', 'organizer_id', 'category_id', 'event_status']);
        $events = $this->eventService->getAllEvents(
            $filters,
            $request->input('per_page', 15),
            $request->input('order_by', 'created_at'),
            $request->input('direction', 'desc')
        );

        return Inertia::render('Admin/Events/Index', [
            'pageTitle' => 'Events Management',
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Events'] // Current page
            ],
            'events' => $events,
            'filters' => $filters,
            // Pass other necessary data for filtering UI if needed
            // 'categories' => Category::orderBy('name')->get()->map(fn($cat) => ['id' => $cat->id, 'name' => $cat->getTranslation('name', app()->getLocale())]),
            // 'organizers' => User::whereHas('roles', fn($q) => $q->where('name', 'Organizer'))->orderBy('name')->get(['id', 'name']),
            // Add eventStatuses if they are to be dynamic from backend
            // 'eventStatuses' => collect(Event::EVENT_STATUSES)->map(fn($status) => ['value' => $status, 'label' => ucfirst(str_replace('_', ' ', $status))])->values(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): InertiaResponse
    {
        $venues_data_for_view = Venue::where('is_active', true)
            ->with(['state', 'country']) // Eager load relations
            ->orderBy('name->' . app()->getLocale())
            ->get()
            ->map(function ($venue) {
                $locale = app()->getLocale();
                return [
                    'value' => $venue->id,
                    'label' => $venue->getTranslation('name', $locale),
                    'address_line_1' => $venue->getTranslation('address_line_1', $locale),
                    'address_line_2' => $venue->getTranslation('address_line_2', $locale, false), // non-required
                    'city' => $venue->getTranslation('city', $locale),
                    'postal_code' => $venue->postal_code,
                    'state_province' => $venue->state?->name, // Assuming 'name' is the field in State model
                    'country' => $venue->country?->name,     // Assuming 'name' is the field in Country model
                    'latitude' => $venue->latitude,
                    'longitude' => $venue->longitude,
                ];
            });

        return Inertia::render('Admin/Events/Create', [
            // Pass necessary data for form selects, e.g.:
            'categories' => Category::orderBy('name->' . app()->getLocale())->get()->map(fn($cat) => ['value' => $cat->id, 'label' => $cat->name]),
            'tags' => Tag::orderBy('name->' . app()->getLocale())->get()->map(fn($tag) => ['value' => $tag->id, 'label' => $tag->name]),
            'organizers' => User::whereHas('roles', fn($q) => $q->whereIn('name', ['Organizer', 'Platform Admin']))->orderBy('name')->get(['id', 'name'])
                ->map(fn($user) => ['value' => $user->id, 'label' => $user->name]),
            'eventStatuses' => collect(Event::EVENT_STATUSES ?? [])->map(fn($status) => ['value' => $status, 'label' => ucfirst(str_replace('_', ' ', $status))])->values(),
            'visibilities' => collect(Event::VISIBILITIES ?? [])->map(fn($status) => ['value' => $status, 'label' => ucfirst(str_replace('_', ' ', $status))])->values(),
            'venues' => $venues_data_for_view, // Use the prepared data
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse // Or use a FormRequest for validation
    {
        // If not using FormRequest, validate here or rely on DTO validation
        // $validatedData = $request->validate(EventData::rules());
        // $eventData = EventData::from($validatedData);

        $eventData = EventData::from($request->all());
        $this->eventService->createEvent($eventData);

        return redirect()->route('admin.events.index')->with('success', 'Event created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Event $event): InertiaResponse // Route model binding
    {
        // Typically, for admin CRUD, an explicit show page might not be needed if edit page shows all details.
        // If needed, it would be similar to edit().
        $loadedEvent = $event->load(['category', 'organizer', 'tags']);
        return Inertia::render('Admin/Events/Show', [
            'event' => EventData::from($loadedEvent->toArray()) // Pass as DTO using toArray()
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Event $event): InertiaResponse // Route model binding
    {
        $event->load(['category', 'organizer', 'tags']); // Ensure relations are loaded

        // Use $event->toArray() to ensure translatable fields are passed as arrays to EventData
        $eventDataArray = EventData::from($event->toArray())->toArray();

        // Prepare media URLs/data
        $portraitPosterUrl = $event->getFirstMediaUrl('portrait_poster', 'medium'); // Assuming 'medium' conversion exists
        $landscapePosterUrl = $event->getFirstMediaUrl('landscape_poster', 'medium');
        $galleryItems = $event->getMedia('gallery')->map(fn($media) => [
            'id' => $media->id,
            'url' => $media->getUrl(), // Use original URL for testing
            'name' => $media->name,
            'order_column' => $media->order_column // If using ordering
        ])->sortBy('order_column')->values()->toArray();

        // Ensure tag_ids are correctly plucked if not already perfectly handled by EventData::from()->toArray()
        // EventData should handle this if $casts['tags'] or a relation is correctly set up for DTO transformation
        // However, explicitly plucking here ensures it for the view if needed.
        $tagIds = $event->tags->pluck('id')->toArray();

        $configuredLocales = config('app.available_locales', ['en' => 'English']); // Default matches expected structure
        $availableLocalesForView = collect($configuredLocales)->map(function ($nameInConfig, $codeInConfig) {
            return [
                'code' => $codeInConfig,
                'name' => $nameInConfig, // Directly use the name from config
            ];
        })->values()->toArray(); // Ensure it's a numerically indexed array of objects for Vue

        return Inertia::render('Admin/Events/Edit', [
            'event' => array_merge(
                $eventDataArray,
                [
                    'portrait_poster_url' => $portraitPosterUrl,
                    'landscape_poster_url' => $landscapePosterUrl,
                    'gallery_items' => $galleryItems,
                    'tag_ids' => $tagIds, // Ensure tag_ids are in the top level for form binding
                ]
            ),
            'availableLocales' => $availableLocalesForView, // Pass the locales to the view
            'categories' => Category::orderBy('name->' . app()->getLocale())->get()->map(fn($cat) => ['value' => $cat->id, 'label' => $cat->name]),
            'tags' => Tag::orderBy('name->' . app()->getLocale())->get()->map(fn($tag) => ['value' => $tag->id, 'label' => $tag->name]),
            'organizers' => User::whereHas('roles', fn($q) => $q->whereIn('name', ['Organizer', 'Platform Admin']))
                ->orderBy('name')->get(['id', 'name'])
                ->map(fn($user) => ['value' => $user->id, 'label' => $user->name]),
            'eventStatuses' => collect(Event::EVENT_STATUSES ?? [])->map(fn($status) => ['value' => $status, 'label' => ucfirst(str_replace('_', ' ', $status))])->values(),
            'visibilities' => collect(Event::VISIBILITIES ?? [])->map(fn($status) => ['value' => $status, 'label' => ucfirst(str_replace('_', ' ', $status))])->values(),
            'venues' => Venue::where('is_active', true)
                ->with(['state', 'country']) // Eager load relations
                ->orderBy('name->' . app()->getLocale())
                ->get()
                ->map(function ($venue) {
                    $locale = app()->getLocale();
                    return [
                        'value' => $venue->id,
                        'label' => $venue->getTranslation('name', $locale),
                        'address_line_1' => $venue->getTranslation('address_line_1', $locale),
                        'address_line_2' => $venue->getTranslation('address_line_2', $locale, false),
                        'city' => $venue->getTranslation('city', $locale),
                        'postal_code' => $venue->postal_code,
                        'state_province' => $venue->state?->name,
                        'country' => $venue->country?->name,
                        'latitude' => $venue->latitude,
                        'longitude' => $venue->longitude,
                    ];
                }),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Event $event): RedirectResponse // Or use a FormRequest
    {
        // $validatedData = $request->validate(EventData::rules());
        // $eventData = EventData::from(array_merge($validatedData, ['id' => $event->id]));

        // Ensure the event ID is part of the data for the DTO and service
        $eventData = EventData::from(array_merge($request->all(), ['id' => $event->id]));
        $this->eventService->updateEvent($eventData);

        return redirect()->route('admin.events.index')->with('success', 'Event updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Event $event): RedirectResponse
    {
        $this->eventService->deleteEvent($event);
        return redirect()->route('admin.events.index')->with('success', 'Event deleted successfully.');
    }
}
