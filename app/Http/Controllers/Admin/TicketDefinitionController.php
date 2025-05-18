<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TicketDefinition;
use App\Services\TicketDefinitionService;
use App\DataTransferObjects\TicketDefinitionData;
use App\Enums\TicketDefinitionStatus;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;
use Exception;

class TicketDefinitionController extends Controller
{
    public function __construct(protected TicketDefinitionService $ticketDefinitionService)
    {
        // Permissions can be added here later, e.g.:
        // $this->authorizeResource(TicketDefinition::class, 'ticket_definition');
    }

    public function index(): InertiaResponse
    {
        return Inertia::render('Admin/TicketDefinitions/Index', [
            'ticketDefinitions' => TicketDefinitionData::collect($this->ticketDefinitionService->getAllTicketDefinitions()),
        ]);
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('Admin/TicketDefinitions/Create', [
            'statuses' => collect(TicketDefinitionStatus::cases())->map(fn($status) => ['value' => $status->value, 'label' => $status->getLabel()]),
            'availableLocales' => config('app.available_locales', ['en' => 'English']),
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
        return Inertia::render('Admin/TicketDefinitions/Edit', [
            'ticketDefinition' => TicketDefinitionData::from($ticketDefinition->toArray()),
            'statuses' => collect(TicketDefinitionStatus::cases())->map(fn($status) => ['value' => $status->value, 'label' => $status->getLabel()]),
            'availableLocales' => config('app.available_locales', ['en' => 'English']),
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
