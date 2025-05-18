<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\Venue; // For venue selection
use App\Services\EventOccurrenceService; // Assuming this service will be created
use App\DataTransferObjects\EventOccurrenceData; // Assuming this DTO will be created
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;
use App\Enums\OccurrenceStatus; // Import the enum
use Spatie\LaravelData\Exceptions\InvalidDataClass;
use Illuminate\Validation\ValidationException;

class EventOccurrenceController extends Controller
{
    protected EventOccurrenceService $eventOccurrenceService;

    public function __construct(EventOccurrenceService $eventOccurrenceService)
    {
        $this->eventOccurrenceService = $eventOccurrenceService;
        // $this->authorizeResource(EventOccurrence::class, 'occurrence'); // TODO: Setup policy
    }

    /**
     * Display a listing of the occurrences for a specific event.
     */
    public function index(Event $event): InertiaResponse
    {
        $occurrences = $this->eventOccurrenceService->getAllOccurrencesForEvent($event->id, 15, ['venue']);
        return Inertia::render('Admin/EventOccurrences/Index', [
            'event' => [
                'id' => $event->id,
                'name' => $event->getTranslation('name', app()->getLocale()),
            ],
            'occurrences' => $occurrences,
            'pageTitle' => 'Occurrences for: ' . $event->getTranslation('name', app()->getLocale()),
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Events', 'href' => route('admin.events.index')],
                ['text' => $event->getTranslation('name', app()->getLocale()), 'href' => route('admin.events.edit', $event->id)],
                ['text' => 'Occurrences']
            ],
        ]);
    }

    /**
     * Show the form for creating a new event occurrence for a specific event.
     */
    public function create(Event $event): InertiaResponse
    {
        $venues = Venue::select('id', 'name')->get()->map(function ($venue) {
            return [
                'id' => $venue->id,
                'name' => $venue->name,
            ];
        });

        $availableLocales = config('app.available_locales', ['en' => 'English']);
        $occurrenceStatuses = array_map(
            fn(OccurrenceStatus $case) => ['value' => $case->value, 'label' => $case->label()],
            OccurrenceStatus::cases()
        );

        return Inertia::render('Admin/EventOccurrences/Create', [
            'event' => [
                'id' => $event->id,
                'name' => $event->getTranslation('name', app()->getLocale()),
            ],
            'venues' => $venues,
            'availableLocales' => $availableLocales,
            'occurrenceStatuses' => $occurrenceStatuses,
            'pageTitle' => 'Create Occurrence for: ' . $event->getTranslation('name', app()->getLocale()),
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Events', 'href' => route('admin.events.index')],
                ['text' => $event->getTranslation('name', app()->getLocale()), 'href' => route('admin.events.edit', $event->id)],
                ['text' => 'Occurrences', 'href' => route('admin.events.occurrences.index', $event->id)],
                ['text' => 'Create']
            ],
        ]);
    }

    /**
     * Store a newly created event occurrence in storage.
     */
    public function store(Request $request, Event $event): RedirectResponse
    {
        Log::info('EventOccurrenceController@store request data: ', $request->all());

        try {
            $validatedData = EventOccurrenceData::from($request->all());
            Log::info('Validated DTO: ', $validatedData->toArray());

            $this->eventOccurrenceService->createOccurrence($event->id, $validatedData);

            return Redirect::route('admin.events.occurrences.index', $event->id)
                ->with('success', 'Event occurrence created successfully.');
        } catch (InvalidDataClass $e) {
            Log::error('DTO Validation Error: ' . $e->getMessage(), $e->errors);
            return back()->withErrors($e->errors)->withInput();
        } catch (ValidationException $e) {
            Log::error('Laravel Validation Error: ' . $e->getMessage(), $e->errors());
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Error creating event occurrence: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return back()->with('error', 'Error creating event occurrence: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Show the form for editing the specified event occurrence.
     */
    public function edit(EventOccurrence $occurrence): InertiaResponse // Route model binding (shallow)
    {
        $event = $occurrence->event; // Get parent event
        $occurrence->load(['venue']); // Load venue if relationship exists

        $venues = Venue::select('id', 'name')->get()->map(function ($venue) {
            return [
                'id' => $venue->id,
                'name' => $venue->name,
            ];
        });
        $availableLocales = config('app.available_locales', ['en' => 'English']);
        $occurrenceStatusesFormatted = array_map(
            fn(OccurrenceStatus $case) => ['value' => $case->value, 'label' => $case->label()],
            OccurrenceStatus::cases()
        );

        return Inertia::render('Admin/EventOccurrences/Edit', [
            'event' => [
                'id' => $event->id,
                'name' => $event->getTranslation('name', app()->getLocale()),
            ],
            'occurrence' => EventOccurrenceData::from($occurrence->load('venue')->toArray()), // Pass as DTO
            'venues' => $venues,
            'availableLocales' => $availableLocales,
            'occurrenceStatuses' => $occurrenceStatusesFormatted,
            'pageTitle' => 'Edit Occurrence for: ' . $event->getTranslation('name', app()->getLocale()),
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Events', 'href' => route('admin.events.index')],
                ['text' => $event->getTranslation('name', app()->getLocale()), 'href' => route('admin.events.edit', $event->id)],
                ['text' => 'Occurrences', 'href' => route('admin.events.occurrences.index', $event->id)],
                ['text' => 'Edit']
            ],
        ]);
    }

    /**
     * Update the specified event occurrence in storage.
     */
    public function update(Request $request, EventOccurrence $occurrence): RedirectResponse // Route model binding (shallow)
    {
        try {
            $validatedData = EventOccurrenceData::from($request->all());
            $this->eventOccurrenceService->updateOccurrence($occurrence->id, $validatedData);
            return Redirect::route('admin.events.occurrences.index', $occurrence->event_id)
                ->with('success', 'Event occurrence updated successfully.');
        } catch (InvalidDataClass $e) {
            return back()->withErrors($e->errors)->withInput();
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Error updating event occurrence: ' . $e->getMessage());
            return back()->with('error', 'Error updating event occurrence: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified event occurrence from storage.
     */
    public function destroy(EventOccurrence $occurrence): RedirectResponse // Route model binding (shallow)
    {
        try {
            $eventId = $occurrence->event_id;
            $this->eventOccurrenceService->deleteOccurrence($occurrence->id);
            return Redirect::route('admin.events.occurrences.index', $eventId)
                ->with('success', 'Event occurrence deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Error deleting event occurrence: ' . $e->getMessage());
            return back()->with('error', 'Error deleting event occurrence.');
        }
    }
}
