<?php

namespace App\Modules\TicketHold\Controllers\Admin;

use App\Enums\RoleNameEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\TicketHold\StoreTicketHoldRequest;
use App\Http\Requests\TicketHold\UpdateTicketHoldRequest;
use App\Models\EventOccurrence;
use App\Models\Organizer;
use App\Models\TicketDefinition;
use App\Modules\TicketHold\Actions\Holds\CreateTicketHoldAction;
use App\Modules\TicketHold\Actions\Holds\ReleaseTicketHoldAction;
use App\Modules\TicketHold\Actions\Holds\UpdateTicketHoldAction;
use App\Modules\TicketHold\DTOs\TicketAllocationData;
use App\Modules\TicketHold\DTOs\TicketHoldData;
use App\Modules\TicketHold\Enums\HoldStatusEnum;
use App\Modules\TicketHold\Enums\PricingModeEnum;
use App\Modules\TicketHold\Exceptions\InsufficientInventoryException;
use App\Modules\TicketHold\Models\TicketHold;
use App\Modules\TicketHold\Resources\TicketHoldFormResource;
use App\Modules\TicketHold\Resources\TicketHoldResource;
use App\Modules\TicketHold\Services\HoldAnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TicketHoldController extends Controller
{
    public function __construct(
        protected CreateTicketHoldAction $createAction,
        protected UpdateTicketHoldAction $updateAction,
        protected ReleaseTicketHoldAction $releaseAction,
        protected HoldAnalyticsService $analyticsService
    ) {
        $this->authorizeResource(TicketHold::class, 'ticket_hold');
    }

    public function index(Request $request): InertiaResponse
    {
        $holds = $this->buildHoldsQuery($request)->paginate(15)->withQueryString();

        return Inertia::render('Admin/TicketHolds/Index', [
            'pageTitle' => 'Ticket Holds',
            'breadcrumbs' => $this->getBreadcrumbs(),
            'holds' => TicketHoldResource::collection($holds),
            'organizers' => $this->getOrganizersForFilter(),
            'eventOccurrences' => $this->getOccurrencesForFilter(),
            'statusOptions' => $this->getStatusOptions(),
            'filters' => $request->only(['occurrence_id', 'organizer_id', 'status', 'search']),
        ]);
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('Admin/TicketHolds/Create', [
            'pageTitle' => 'Create Ticket Hold',
            'breadcrumbs' => $this->getBreadcrumbs('Create'),
            'organizers' => $this->getOrganizersForSelect(),
            'eventOccurrences' => $this->getOccurrencesForSelect(),
            'pricingModes' => $this->getPricingModeOptions(),
        ]);
    }

    public function store(StoreTicketHoldRequest $request): RedirectResponse
    {
        try {
            $holdData = $this->buildHoldDataFromRequest($request->validated());
            $this->createAction->execute($holdData, auth()->user());

            return redirect()->route('admin.ticket-holds.index')
                ->with('success', 'Ticket hold created successfully.');
        } catch (InsufficientInventoryException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(TicketHold $ticketHold): InertiaResponse
    {
        $ticketHold->load([
            'organizer',
            'eventOccurrence.event',
            'allocations.ticketDefinition',
            'purchaseLinks.assignedUser',
            'creator',
            'releasedByUser',
        ]);

        return Inertia::render('Admin/TicketHolds/Show', [
            'pageTitle' => "Ticket Hold: {$ticketHold->name}",
            'breadcrumbs' => $this->getBreadcrumbs($ticketHold->name),
            'ticketHold' => new TicketHoldResource($ticketHold),
            'analytics' => $this->analyticsService->getHoldAnalytics($ticketHold),
        ]);
    }

    public function edit(TicketHold $ticketHold): InertiaResponse
    {
        $ticketHold->load(['organizer', 'eventOccurrence.event', 'allocations.ticketDefinition']);

        return Inertia::render('Admin/TicketHolds/Edit', [
            'pageTitle' => 'Edit Ticket Hold',
            'breadcrumbs' => $this->getBreadcrumbs('Edit'),
            'ticketHold' => new TicketHoldFormResource($ticketHold),
            'organizers' => $this->getOrganizersForSelect(),
            'occurrences' => $this->getOccurrenceForEdit($ticketHold),
            'pricingModes' => $this->getPricingModeOptions(),
            'ticketDefinitions' => $this->getTicketDefinitionsForOccurrence($ticketHold->eventOccurrence),
        ]);
    }

    public function update(UpdateTicketHoldRequest $request, TicketHold $ticketHold): RedirectResponse
    {
        try {
            $holdData = $this->buildHoldDataForUpdate($request->validated(), $ticketHold);
            $this->updateAction->execute($ticketHold, $holdData);

            return redirect()->route('admin.ticket-holds.index')
                ->with('success', 'Ticket hold updated successfully.');
        } catch (InsufficientInventoryException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy(TicketHold $ticketHold): RedirectResponse
    {
        $ticketHold->delete();

        return redirect()->route('admin.ticket-holds.index')
            ->with('success', 'Ticket hold deleted successfully.');
    }

    public function release(TicketHold $ticketHold): RedirectResponse
    {
        $this->authorize('release', $ticketHold);
        $this->releaseAction->execute($ticketHold, auth()->user());

        return redirect()->route('admin.ticket-holds.show', $ticketHold)
            ->with('success', 'Ticket hold released successfully. All associated purchase links have been revoked.');
    }

    public function availableTickets(EventOccurrence $occurrence): JsonResponse
    {
        $this->authorize('viewAny', TicketHold::class);

        $eventName = $occurrence->event
            ? $occurrence->event->getTranslation('name', app()->getLocale())
            : 'Unknown Event';

        return response()->json([
            'ticket_definitions' => $this->getTicketDefinitionsForOccurrence($occurrence),
            'occurrence' => [
                'id' => $occurrence->id,
                'event_name' => $eventName,
                'start_at' => $occurrence->start_at?->toIso8601String(),
            ],
        ]);
    }

    private function buildHoldsQuery(Request $request)
    {
        return $this->filterHoldsForUser(
            TicketHold::query()
                ->withStats()
                ->with(['organizer', 'eventOccurrence.event', 'creator'])
        )
            ->when($request->input('occurrence_id'), fn ($q, $id) => $q->forOccurrence($id))
            ->when($request->input('organizer_id'), fn ($q, $id) => $q->forOrganizer($id))
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('search'), function ($q, $search) {
                $search = str_replace(['%', '_'], ['\%', '\_'], $search);

                return $q->where('name', 'like', "%{$search}%");
            })
            ->orderBy('created_at', 'desc');
    }

    private function filterHoldsForUser($query)
    {
        $user = auth()->user();
        if (! $user->hasRole(RoleNameEnum::ADMIN)) {
            $query->whereIn('organizer_id', $user->organizers->pluck('id'));
        }

        return $query;
    }

    private function getFilteredOrganizers()
    {
        $user = auth()->user();
        $query = Organizer::orderBy('name');

        if (! $user->hasRole(RoleNameEnum::ADMIN)) {
            $query->whereIn('id', $user->organizers->pluck('id'));
        }

        return $query->get(['id', 'name']);
    }

    private function getOrganizersForFilter(): array
    {
        return $this->getFilteredOrganizers()
            ->map(fn ($org) => ['id' => $org->id, 'name' => $org->name])
            ->toArray();
    }

    private function getOrganizersForSelect(): array
    {
        return $this->getFilteredOrganizers()
            ->map(fn ($org) => ['value' => $org->id, 'label' => $org->name])
            ->toArray();
    }

    private function getOccurrencesForFilter(): array
    {
        return $this->buildOccurrencesQuery()
            ->orderBy('start_at', 'desc')
            ->get()
            ->map(fn ($occ) => [
                'id' => $occ->id,
                'event' => [
                    'id' => $occ->event_id,
                    'name' => $occ->event->getTranslations('name'),
                ],
                'start_at' => $occ->start_at->toIso8601String(),
            ])
            ->toArray();
    }

    private function getOccurrencesForSelect(): array
    {
        return $this->buildOccurrencesQuery()
            ->where('start_at', '>', now())
            ->orderBy('start_at', 'asc')
            ->get()
            ->map(fn ($occ) => [
                'value' => $occ->id,
                'label' => $occ->event->getTranslation('name', app()->getLocale()).' - '.$occ->start_at->format('Y-m-d H:i'),
                'event_id' => $occ->event_id,
                'organizer_id' => $occ->event->organizer_id,
            ])
            ->toArray();
    }

    private function buildOccurrencesQuery()
    {
        $query = EventOccurrence::with('event')
            ->whereHas('event') // Filter out orphaned occurrences
            ->whereNotNull('start_at'); // Filter out occurrences with null start_at
        $user = auth()->user();

        if (! $user->hasRole(RoleNameEnum::ADMIN)) {
            $userOrganizerIds = $user->organizers->pluck('id');
            $query->whereHas('event', fn ($q) => $q->whereIn('organizer_id', $userOrganizerIds));
        }

        return $query;
    }

    private function getOccurrenceForEdit(TicketHold $ticketHold): array
    {
        $occ = $ticketHold->eventOccurrence;

        if (! $occ || ! $occ->event || ! $occ->start_at) {
            return [];
        }

        return [[
            'id' => $occ->id,
            'event' => [
                'id' => $occ->event_id,
                'name' => $occ->event->name,
            ],
            'start_at' => $occ->start_at->toIso8601String(),
        ]];
    }

    private function getTicketDefinitionsForOccurrence(EventOccurrence $occurrence): array
    {
        return TicketDefinition::where('event_id', $occurrence->event_id)
            ->get()
            ->map(fn ($td) => [
                'id' => $td->id,
                'value' => $td->id,
                'label' => $td->getTranslation('name', app()->getLocale()),
                'name' => $td->getTranslation('name', app()->getLocale()),
                'price' => $td->price,
                'quantity' => $td->quantity,
                'available' => $td->available_quantity ?? $td->quantity,
            ])
            ->toArray();
    }

    private function getStatusOptions(): array
    {
        return collect(HoldStatusEnum::cases())
            ->map(fn ($status) => ['value' => $status->value, 'label' => $status->label()])
            ->toArray();
    }

    private function getPricingModeOptions(): array
    {
        return collect(PricingModeEnum::cases())
            ->map(fn ($mode) => [
                'value' => $mode->value,
                'label' => $mode->label(),
                'requires_value' => $mode->requiresValue(),
            ])
            ->toArray();
    }

    private function getBreadcrumbs(?string $current = null): array
    {
        $breadcrumbs = [
            ['text' => 'Admin', 'href' => route('admin.dashboard')],
            ['text' => 'Ticket Holds'],
        ];

        if ($current) {
            $breadcrumbs[1]['href'] = route('admin.ticket-holds.index');
            $breadcrumbs[] = ['text' => $current];
        }

        return $breadcrumbs;
    }

    private function buildHoldDataFromRequest(array $validated): TicketHoldData
    {
        $allocations = array_map(
            fn ($alloc) => new TicketAllocationData(
                ticket_definition_id: $alloc['ticket_definition_id'],
                allocated_quantity: $alloc['allocated_quantity'],
                pricing_mode: PricingModeEnum::from($alloc['pricing_mode']),
                custom_price: $alloc['custom_price'] ?? null,
                discount_percentage: $alloc['discount_percentage'] ?? null,
            ),
            $validated['allocations']
        );

        return new TicketHoldData(
            event_occurrence_id: $validated['event_occurrence_id'],
            organizer_id: $validated['organizer_id'] ?? null,
            name: $validated['name'],
            description: $validated['description'] ?? null,
            internal_notes: $validated['internal_notes'] ?? null,
            allocations: $allocations,
            expires_at: isset($validated['expires_at']) ? Carbon::parse($validated['expires_at']) : null,
        );
    }

    private function buildHoldDataForUpdate(array $validated, TicketHold $ticketHold): TicketHoldData
    {
        $allocations = ! empty($validated['allocations'])
            ? array_map(
                fn ($alloc) => new TicketAllocationData(
                    ticket_definition_id: $alloc['ticket_definition_id'],
                    allocated_quantity: $alloc['allocated_quantity'],
                    pricing_mode: PricingModeEnum::from($alloc['pricing_mode']),
                    custom_price: $alloc['custom_price'] ?? null,
                    discount_percentage: $alloc['discount_percentage'] ?? null,
                ),
                $validated['allocations']
            )
            : $ticketHold->allocations->map(fn ($a) => new TicketAllocationData(
                ticket_definition_id: $a->ticket_definition_id,
                allocated_quantity: $a->allocated_quantity,
                pricing_mode: $a->pricing_mode,
                custom_price: $a->custom_price,
                discount_percentage: $a->discount_percentage,
            ))->all();

        return new TicketHoldData(
            event_occurrence_id: $ticketHold->event_occurrence_id,
            organizer_id: $ticketHold->organizer_id,
            name: $validated['name'] ?? $ticketHold->name,
            description: $validated['description'] ?? $ticketHold->description,
            internal_notes: $validated['internal_notes'] ?? $ticketHold->internal_notes,
            allocations: $allocations,
            expires_at: isset($validated['expires_at']) ? Carbon::parse($validated['expires_at']) : $ticketHold->expires_at,
        );
    }
}
