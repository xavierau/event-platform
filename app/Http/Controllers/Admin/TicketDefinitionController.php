<?php

namespace App\Http\Controllers\Admin;

use App\DataTransferObjects\TicketDefinitionData;
use App\Enums\TicketDefinitionStatus;
use App\Http\Controllers\Controller;
use App\Models\EventOccurrence;
use App\Models\TicketDefinition;
use App\Services\TicketDefinitionService;
use DateTimeZone;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TicketDefinitionController extends Controller
{
    public function __construct(protected TicketDefinitionService $ticketDefinitionService)
    {
        // Permissions can be added here later, e.g.:
        // $this->authorizeResource(TicketDefinition::class, 'ticket_definition');
    }

    public function index(Request $request): \Inertia\Response
    {
        $filters = $request->only(['search', 'status']); // Example filters
        $perPage = $request->input('perPage', 15);
        $ticketDefinitionsPaginator = $this->ticketDefinitionService->getAllTicketDefinitions($filters, (int)$perPage);

        // Explicitly transform models to arrays before DTO hydration
        $dtoPaginator = $ticketDefinitionsPaginator->through(
        // fn(TicketDefinition $definition) => dd($definition->toArray())
        // );
            fn(TicketDefinition $definition) => TicketDefinitionData::fromModel($definition)
        );

        return inertia('Admin/TicketDefinitions/Index', [
            'ticketDefinitions' => $dtoPaginator,
            'filters' => $filters, // Pass current filters back to the view for UI state
        ]);
    }

    public function create(): InertiaResponse
    {
        // Get available event occurrences for selection
        $eventOccurrences = EventOccurrence::with('event:id,name')
            ->select('id', 'name', 'event_id', 'start_at', 'end_at')
            ->orderBy('start_at', 'asc')
            ->get()
            ->map(function ($occurrence) {
                return [
                    'id' => $occurrence->id,
                    'name' => $occurrence->getTranslation('name', app()->getLocale()),
                    'event_name' => $occurrence->event->getTranslation('name', app()->getLocale()),
                    'start_at' => $occurrence->start_at?->format('Y-m-d H:i'),
                    'end_at' => $occurrence->end_at?->format('Y-m-d H:i'),
                ];
            });

        return Inertia::render('Admin/TicketDefinitions/Create', [
            'statuses' => collect(TicketDefinitionStatus::cases())->map(fn($status) => ['value' => $status->value, 'label' => $status->getLabel()]),
            'availableLocales' => config('app.available_locales', ['en' => 'English']),
            'timezones' => DateTimeZone::listIdentifiers(DateTimeZone::ALL),
            'eventOccurrences' => $eventOccurrences,
        ]);
    }

    public function store(TicketDefinitionData $ticketDefinitionData): RedirectResponse
    {
        try {
            $this->ticketDefinitionService->createTicketDefinition($ticketDefinitionData);

            return redirect()->route('admin.ticket-definitions.index')->with('success', 'Ticket definition created successfully.');
        } catch (Exception $e) {
            // Log the exception
            logger()->error('Error creating ticket definition: ' . $e->getMessage(), ['exception' => $e]);
            return back()->with('error', 'Error creating ticket definition. Please try again. Details: ' . $e->getMessage());
        }
    }

    public function edit(TicketDefinition $ticketDefinition): InertiaResponse
    {
        // Get available event occurrences for selection
        $eventOccurrences = EventOccurrence::with('event:id,name')
            ->select('id', 'name', 'event_id', 'start_at', 'end_at')
            ->orderBy('start_at', 'asc')
            ->get()
            ->map(function ($occurrence) {
                return [
                    'id' => $occurrence->id,
                    'name' => $occurrence->getTranslation('name', app()->getLocale()),
                    'event_name' => $occurrence->event->getTranslation('name', app()->getLocale()),
                    'start_at' => $occurrence->start_at?->format('Y-m-d H:i'),
                    'end_at' => $occurrence->end_at?->format('Y-m-d H:i'),
                ];
            });

        return Inertia::render('Admin/TicketDefinitions/Edit', [
            'ticketDefinition' => TicketDefinitionData::fromModel($ticketDefinition),
            'statuses' => collect(TicketDefinitionStatus::cases())->map(fn($status) => ['value' => $status->value, 'label' => $status->getLabel()]),
            'availableLocales' => config('app.available_locales', ['en' => 'English']),
            'timezones' => DateTimeZone::listIdentifiers(DateTimeZone::ALL),
            'eventOccurrences' => $eventOccurrences,
        ]);
    }

    public function update(TicketDefinitionData $ticketDefinitionData, TicketDefinition $ticketDefinition): RedirectResponse
    {
        try {
            $this->ticketDefinitionService->updateTicketDefinition($ticketDefinition->id, $ticketDefinitionData);

            return redirect()->route('admin.ticket-definitions.index')->with('success', 'Ticket definition updated successfully.');
        } catch (Exception $e) {
            logger()->error('Error updating ticket definition: ' . $e->getMessage(), ['exception' => $e, 'ticketDefinitionId' => $ticketDefinition->id]);
            return back()->with('error', 'Error updating ticket definition. Please try again. Details: ' . $e->getMessage());
        }
    }

    public function destroy(TicketDefinition $ticketDefinition): RedirectResponse
    {
        try {
            $this->ticketDefinitionService->deleteTicketDefinition($ticketDefinition->id);

            return redirect()->route('admin.ticket-definitions.index')->with('success', 'Ticket definition deleted successfully.');
        } catch (Exception $e) {
            logger()->error('Error deleting ticket definition: ' . $e->getMessage(), ['exception' => $e, 'ticketDefinitionId' => $ticketDefinition->id]);
            return back()->with('error', 'Error deleting ticket definition. Please try again. Details: ' . $e->getMessage());
        }
    }
}
