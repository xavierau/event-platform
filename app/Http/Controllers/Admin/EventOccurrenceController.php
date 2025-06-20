<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\Venue; // For venue selection
use App\Models\TicketDefinition; // Added for ticket definitions
use App\Services\EventOccurrenceService; // Assuming this service will be created
use App\DataTransferObjects\EventOccurrenceData; // Assuming this DTO will be created
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;
use App\Enums\OccurrenceStatus; // Import the enum
use App\Enums\TicketDefinitionStatus; // Added for ticket definition statuses
use Spatie\LaravelData\Exceptions\InvalidDataClass;
use Illuminate\Validation\ValidationException;

class EventOccurrenceController extends Controller
{
    protected EventOccurrenceService $eventOccurrenceService;

    public function __construct(EventOccurrenceService $eventOccurrenceService)
    {
        $this->eventOccurrenceService = $eventOccurrenceService;
        // Event occurrences are managed through the Event policy's manageOccurrences permission
        $this->middleware('auth');
    }

    /**
     * Display a listing of the occurrences for a specific event.
     */
    public function index(Event $event): InertiaResponse
    {
        $this->authorize('manageOccurrences', $event);

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
        $this->authorize('manageOccurrences', $event);

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
        $this->authorize('manageOccurrences', $event);

        Log::info('EventOccurrenceController@store request data: ', $request->all());

        try {
            $validatedData = EventOccurrenceData::from($request->all());
            Log::info('Validated DTO: ', $validatedData->toArray());

            $this->eventOccurrenceService->createOccurrence($event->id, $validatedData);

            return Redirect::route('admin.events.occurrences.index', $event->id)
                ->with('success', 'Event occurrence created successfully.');
        } catch (InvalidDataClass $e) {
            Log::error('DTO Validation Error: ' . $e->getMessage(), ['payload' => $request->all()]);
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
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
        $this->authorize('manageOccurrences', $event);
        $occurrence->load(['venue', 'ticketDefinitions']); // Load venue and existing ticket definitions

        $venues = Venue::select('id', 'name')->get()->map(function ($venue) {
            return [
                'id' => $venue->id,
                'name' => $venue->name, // Assuming name is translatable or simple string
            ];
        });
        $availableLocales = config('app.available_locales', ['en' => 'English']);
        $occurrenceStatusesFormatted = array_map(
            fn(OccurrenceStatus $case) => ['value' => $case->value, 'label' => $case->label()],
            OccurrenceStatus::cases()
        );

        // Fetch all available ticket definitions (consider scoping by event or status if needed)
        $allAvailableTicketDefinitions = TicketDefinition::where('status', TicketDefinitionStatus::ACTIVE->value) // Example: only active
            ->orderBy('name->en') // Order by English name
            ->get()
            ->map(function ($ticketDef) {
                return [
                    'id' => $ticketDef->id,
                    'name' => $ticketDef->getTranslation('name', app()->getLocale()), // Use current locale
                    'price' => $ticketDef->price,
                    'currency_code' => $ticketDef->currency, // Fixed: use 'currency' field from model
                    // Add any other fields needed by the selector or display
                ];
            });

        // Format ticket definition statuses
        $ticketDefinitionStatusesFormatted = array_map(
            fn(TicketDefinitionStatus $case) => ['value' => $case->value, 'label' => $case->getLabel()],
            TicketDefinitionStatus::cases()
        );

        // Format currently assigned tickets for the frontend
        $assignedTicketsData = $occurrence->ticketDefinitions->map(function ($ticketDef) {
            return [
                'ticket_definition_id' => $ticketDef->id,
                'name' => $ticketDef->getTranslation('name', app()->getLocale()),
                'original_price' => $ticketDef->price,
                'original_currency_code' => $ticketDef->currency, // Fixed: use 'currency' field from model
                'quantity_for_occurrence' => $ticketDef->pivot->quantity_for_occurrence,
                'price_override' => $ticketDef->pivot->price_override,
                // 'availability_status_for_occurrence' => $ticketDef->pivot->availability_status, // If you have this
            ];
        });

        // Prepare the occurrence data, converting to DTO and adding assigned tickets
        $occurrenceData = EventOccurrenceData::from($occurrence->toArray());
        // It's often better to pass related data separately than to try to shoehorn it into the main DTO
        // if the DTO doesn't naturally accommodate it. Here, we'll pass `assigned_tickets` separately.

        return Inertia::render('Admin/EventOccurrences/Edit', [
            'event' => [
                'id' => $event->id,
                'name' => $event->getTranslation('name', app()->getLocale()),
            ],
            'occurrence' => $occurrenceData, // Pass as DTO
            'venues' => $venues,
            'availableLocales' => $availableLocales,
            'occurrenceStatuses' => $occurrenceStatusesFormatted,
            'allAvailableTicketDefinitions' => $allAvailableTicketDefinitions, // Added
            'ticketDefinitionStatuses' => $ticketDefinitionStatusesFormatted, // Added
            'assignedTickets' => $assignedTicketsData, // Added for pre-populating the form
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
        $this->authorize('manageOccurrences', $occurrence->event);

        try {
            Log::info('EventOccurrenceController@update request data: ', $request->all());

            $validatedData = EventOccurrenceData::from($request->all());
            Log::info('Validated DTO: ', $validatedData->toArray());

            $this->eventOccurrenceService->updateOccurrence($occurrence->id, $validatedData);
            return Redirect::route('admin.events.occurrences.index', $occurrence->event_id)
                ->with('success', 'Event occurrence updated successfully.');
        } catch (InvalidDataClass $e) {
            Log::error('DTO Validation Error on update: ' . $e->getMessage(), ['payload' => $request->all()]);
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        } catch (ValidationException $e) {
            Log::error('Laravel Validation Error on update: ' . $e->getMessage(), $e->errors());
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
        $this->authorize('manageOccurrences', $occurrence->event);

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
