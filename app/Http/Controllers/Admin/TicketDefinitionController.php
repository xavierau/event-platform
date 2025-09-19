<?php

namespace App\Http\Controllers\Admin;

use App\DataTransferObjects\TicketDefinitionData;
use App\Enums\RoleNameEnum;
use App\Enums\TicketDefinitionStatus;
use App\Http\Controllers\Controller;
use App\Models\EventOccurrence;
use App\Models\TicketDefinition;
use App\Modules\Membership\Models\MembershipLevel;
use App\Services\TicketDefinitionService;
use DateTimeZone;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TicketDefinitionController extends Controller
{
    public function __construct(protected TicketDefinitionService $ticketDefinitionService)
    {
        // Check authorization: only admins or users with organizer entity membership can access
        $this->middleware(function ($request, $next) {
            $user = Auth::user();
            if (!$user->hasRole(RoleNameEnum::ADMIN) && !$user->hasOrganizerMembership()) {
                abort(403, 'You do not have permission to manage ticket definitions.');
            }
            return $next($request);
        });
    }

    public function index(Request $request): \Inertia\Response
    {
        $user = Auth::user();
        $filters = $request->only(['search', 'status']); // Example filters
        $perPage = $request->input('perPage', 15);
        
        // For platform admins, get all ticket definitions
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            $ticketDefinitionsPaginator = $this->ticketDefinitionService->getAllTicketDefinitions($filters, (int)$perPage);
        } else {
            // For organizer members, only get ticket definitions for their organizers' events
            $userOrganizerIds = $user->getOrganizerIds();
            
            $ticketDefinitionsPaginator = TicketDefinition::whereHas('eventOccurrences.event', function ($query) use ($userOrganizerIds) {
                $query->whereIn('organizer_id', $userOrganizerIds);
            })->latest()->paginate((int)$perPage);
        }

        // Explicitly transform models to arrays before DTO hydration
        $dtoPaginator = $ticketDefinitionsPaginator->through(
            fn(TicketDefinition $definition) => TicketDefinitionData::fromModel($definition)
        );

        return inertia('Admin/TicketDefinitions/Index', [
            'ticketDefinitions' => $dtoPaginator,
            'filters' => $filters, // Pass current filters back to the view for UI state
        ]);
    }

    public function create(): InertiaResponse
    {
        $user = Auth::user();
        
        // Get available event occurrences for selection, filtered by organizer access
        $eventOccurrencesQuery = EventOccurrence::with('event:id,name')
            ->select('id', 'name', 'event_id', 'start_at', 'end_at')
            ->whereHas('event'); // Only include occurrences with valid events (fixes null event reference)
            
        // For organizer members, only show occurrences from their organizers' events
        if (!$user->hasRole(RoleNameEnum::ADMIN)) {
            $userOrganizerIds = $user->getOrganizerIds();
            
            $eventOccurrencesQuery->whereHas('event', function ($query) use ($userOrganizerIds) {
                $query->whereIn('organizer_id', $userOrganizerIds);
            });
        }
            
        $eventOccurrences = $eventOccurrencesQuery
            ->orderBy('start_at', 'asc')
            ->get()
            ->filter(function ($occurrence) {
                // Additional safety check to filter out any occurrences with null events
                return $occurrence->event !== null;
            })
            ->map(function ($occurrence) {
                return [
                    'id' => $occurrence->id,
                    'name' => $occurrence->getTranslation('name', app()->getLocale()),
                    'event_name' => $occurrence->event->getTranslation('name', app()->getLocale()),
                    'start_at' => $occurrence->start_at?->format('Y-m-d H:i'),
                    'end_at' => $occurrence->end_at?->format('Y-m-d H:i'),
                ];
            })
            ->values(); // Reset array keys after filtering

        // Get active membership levels for discount configuration
        $membershipLevels = MembershipLevel::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'is_active'])
            ->map(function ($level) {
                return [
                    'id' => $level->id,
                    'name' => $level->name,
                    'slug' => $level->slug,
                    'is_active' => $level->is_active,
                ];
            });

        return Inertia::render('Admin/TicketDefinitions/Create', [
            'statuses' => collect(TicketDefinitionStatus::cases())->map(fn($status) => ['value' => $status->value, 'label' => $status->getLabel()]),
            'availableLocales' => config('app.available_locales', ['en' => 'English']),
            'timezones' => DateTimeZone::listIdentifiers(DateTimeZone::ALL),
            'eventOccurrences' => $eventOccurrences,
            'membershipLevels' => $membershipLevels,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        logger()->info('TicketDefinition store request data:', $request->all());
        
        try {
            $ticketDefinitionData = TicketDefinitionData::from($request->all());
            $this->ticketDefinitionService->createTicketDefinition($ticketDefinitionData);

            return redirect()->route('admin.ticket-definitions.index')->with('success', 'Ticket definition created successfully.');
        } catch (\Spatie\LaravelData\Exceptions\InvalidDataClass $e) {
            logger()->error('DTO Validation Error in TicketDefinition: ' . $e->getMessage(), [
                'payload' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        } catch (Exception $e) {
            // Log the exception
            logger()->error('Error creating ticket definition: ' . $e->getMessage(), ['exception' => $e]);
            return back()->with('error', 'Error creating ticket definition. Please try again. Details: ' . $e->getMessage());
        }
    }

    public function edit(TicketDefinition $ticketDefinition): InertiaResponse
    {
        $user = Auth::user();
        
        // Get available event occurrences for selection, filtered by organizer access
        $eventOccurrencesQuery = EventOccurrence::with('event:id,name')
            ->select('id', 'name', 'event_id', 'start_at', 'end_at')
            ->whereHas('event'); // Only include occurrences with valid events (fixes null event reference)
            
        // For organizer members, only show occurrences from their organizers' events
        if (!$user->hasRole(RoleNameEnum::ADMIN)) {
            $userOrganizerIds = $user->getOrganizerIds();
            
            $eventOccurrencesQuery->whereHas('event', function ($query) use ($userOrganizerIds) {
                $query->whereIn('organizer_id', $userOrganizerIds);
            });
        }
            
        $eventOccurrences = $eventOccurrencesQuery
            ->orderBy('start_at', 'asc')
            ->get()
            ->filter(function ($occurrence) {
                // Additional safety check to filter out any occurrences with null events
                return $occurrence->event !== null;
            })
            ->map(function ($occurrence) {
                return [
                    'id' => $occurrence->id,
                    'name' => $occurrence->getTranslation('name', app()->getLocale()),
                    'event_name' => $occurrence->event->getTranslation('name', app()->getLocale()),
                    'start_at' => $occurrence->start_at?->format('Y-m-d H:i'),
                    'end_at' => $occurrence->end_at?->format('Y-m-d H:i'),
                ];
            })
            ->values(); // Reset array keys after filtering

        // Get active membership levels for discount configuration
        $membershipLevels = MembershipLevel::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'is_active'])
            ->map(function ($level) {
                return [
                    'id' => $level->id,
                    'name' => $level->name,
                    'slug' => $level->slug,
                    'is_active' => $level->is_active,
                ];
            });

        // Get existing membership discounts for this ticket
        $membershipDiscounts = \Illuminate\Support\Facades\DB::table('ticket_definition_membership_discounts')
            ->where('ticket_definition_id', $ticketDefinition->id)
            ->select('membership_level_id', 'discount_type', 'discount_value')
            ->get()
            ->map(function ($discount) {
                return [
                    'membership_level_id' => $discount->membership_level_id,
                    'discount_type' => $discount->discount_type,
                    'discount_value' => $discount->discount_value,
                ];
            })
            ->toArray();

        return Inertia::render('Admin/TicketDefinitions/Edit', [
            'ticketDefinition' => TicketDefinitionData::fromModel($ticketDefinition),
            'statuses' => collect(TicketDefinitionStatus::cases())->map(fn($status) => ['value' => $status->value, 'label' => $status->getLabel()]),
            'availableLocales' => config('app.available_locales', ['en' => 'English']),
            'timezones' => DateTimeZone::listIdentifiers(DateTimeZone::ALL),
            'eventOccurrences' => $eventOccurrences,
            'membershipLevels' => $membershipLevels,
            'membershipDiscounts' => $membershipDiscounts,
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
