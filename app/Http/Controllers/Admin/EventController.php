<?php

namespace App\Http\Controllers\Admin;

use App\DataTransferObjects\EventData;
use App\Enums\CommentConfigEnum;
use App\Enums\RoleNameEnum;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Tag;
use App\Models\Venue;
use App\Modules\Membership\Models\MembershipLevel;
use App\Services\EventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

// For fetching categories

// For fetching tags

// For fetching organizers (if selectable)

// For fetching venues

// Added for comment config options

class EventController extends Controller
{
    protected EventService $eventService;

    public function __construct(EventService $eventService)
    {
        $this->eventService = $eventService;
        // Use the EventPolicy for authorization
        $this->authorizeResource(Event::class, 'event');
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
            'filters' => $filters
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): InertiaResponse
    {

        $authUser = auth()->user();
        $authUser->load('roles', 'organizers'); // Eager load roles and organizers relationship
        $isAdmin = $authUser->hasRole(RoleNameEnum::ADMIN->value);

        $locale = app()->getLocale();

        $venues_data_for_view = Venue::active()
            ->with(['state', 'country']) // Eager load relations
            ->orderBy('name->' . app()->getLocale())
            ->when(!$isAdmin, fn($query) => $query->whereIn('organizer_id', $authUser->organizers->pluck('id'))
                ->orWhere('organizer_id', null))
            ->get()
            ->map(fn($venue) => [
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
            ]
            );

        $categories = Category::orderBy('name->' . app()->getLocale())->get()->map(fn($cat) => ['value' => $cat->id, 'label' => $cat->name]);

        // if user has role of Platform Admin then load all organizers, otherwise load only organizers that the user has access to
        $organizers = Organizer::when($isAdmin,
            fn($query) => $query->orderBy('name'),
            fn($query) => $query->whereIn('id', $authUser->organizers->pluck('id'))->orderBy('name'))
            ->get()->map(fn($organizer) => ['value' => $organizer->id, 'label' => $organizer->name]);

        $tags = Tag::orderBy('name->' . app()->getLocale())->get()->map(fn($tag) => ['value' => $tag->id, 'label' => $tag->name]);

        $membershipLevels = MembershipLevel::orderBy('id')->get()->map(fn($level) => [
            'value' => $level->id,
            'label' => $level->getTranslation('name', app()->getLocale()) ?: $level->getTranslation('name', 'en') ?: 'Level ' . $level->id
        ]);

        return Inertia::render('Admin/Events/Create', [
            // Pass necessary data for form selects, e.g.:
            'categories' => $categories,
            'tags' => $tags,
            'organizers' => $organizers,
            'eventStatuses' => collect(Event::EVENT_STATUSES ?? [])->map(fn($status) => ['value' => $status, 'label' => ucfirst(str_replace('_', ' ', $status))])->values(),
            'visibilities' => collect(Event::VISIBILITIES ?? [])->map(fn($status) => ['value' => $status, 'label' => ucfirst(str_replace('_', ' ', $status))])->values(),
            'venues' => $venues_data_for_view, // Use the prepared data
            'membershipLevels' => $membershipLevels,
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

        $categories = Category::orderBy('name->' . app()->getLocale())->get()->map(fn($cat) => ['value' => $cat->id, 'label' => $cat->name]);
        $tags = Tag::orderBy('name->' . app()->getLocale())->get()->map(fn($tag) => ['value' => $tag->id, 'label' => $tag->name]);
        $organizers = Organizer::orderBy('name')->get()->map(fn($organizer) => ['value' => $organizer->id, 'label' => $organizer->name]);
        $venues = Venue::active()
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
            });

        $membershipLevels = MembershipLevel::orderBy('id')->get()->map(fn($level) => [
            'value' => $level->id,
            'label' => $level->getTranslation('name', app()->getLocale()) ?: $level->getTranslation('name', 'en') ?: 'Level ' . $level->id
        ]);

        $viewData = [
            'event' => array_merge(
                $eventDataArray,
                [
                    'portrait_poster_url' => $portraitPosterUrl,
                    'landscape_poster_url' => $landscapePosterUrl,
                    'gallery_items' => $galleryItems,
                    'tag_ids' => $tagIds, // Ensure tag_ids are in the top level for form binding
                ]
            ),
            'commentConfigOptions' => collect(CommentConfigEnum::cases())->map(fn($case) => ['value' => $case->value, 'label' => ucfirst($case->value)])->values()->toArray(),
            'availableLocales' => $availableLocalesForView, // Pass the locales to the view
            'categories' => $categories,
            'tags' => $tags,
            'organizers' => $organizers,
            'eventStatuses' => collect(Event::EVENT_STATUSES ?? [])->map(fn($status) => ['value' => $status, 'label' => ucfirst(str_replace('_', ' ', $status))])->values(),
            'visibilities' => collect(Event::VISIBILITIES ?? [])->map(fn($status) => ['value' => $status, 'label' => ucfirst(str_replace('_', ' ', $status))])->values(),
            'membershipLevels' => $membershipLevels,
            'venues' => $venues,
        ];
        return Inertia::render('Admin/Events/Edit', $viewData);
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
